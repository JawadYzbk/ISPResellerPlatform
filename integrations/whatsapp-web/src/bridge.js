import { mkdir, readdir, readFile, rename, rm, unlink, writeFile } from 'node:fs/promises';
import { createHash, createHmac, timingSafeEqual } from 'node:crypto';
import { join } from 'node:path';

export class BridgeNotReadyError extends Error {}

export class IdempotencyConflictError extends Error {}

export function isHealthyStatus(status) {
  return ['idle', 'qr', 'authenticated', 'ready'].includes(status);
}

const CHROMIUM_PROFILE_LOCKS = new Set(['SingletonCookie', 'SingletonLock', 'SingletonSocket']);

export async function clearStaleChromiumProfileLocks(directory) {
  const pending = [directory];
  let removed = 0;

  while (pending.length > 0) {
    const current = pending.pop();
    let entries;

    try {
      entries = await readdir(current, { withFileTypes: true });
    } catch (error) {
      if (error.code === 'ENOENT') {
        continue;
      }

      throw error;
    }

    for (const entry of entries) {
      const path = join(current, entry.name);
      if (entry.isDirectory()) {
        pending.push(path);
        continue;
      }
      if (!CHROMIUM_PROFILE_LOCKS.has(entry.name)) {
        continue;
      }

      try {
        await unlink(path);
        removed++;
      } catch (error) {
        if (error.code !== 'ENOENT') {
          throw error;
        }
      }
    }
  }

  return removed;
}

export function signPayload(body, secret) {
  return createHmac('sha256', secret).update(body).digest('hex');
}

export function hasValidSignature(body, signature, secret) {
  if (typeof signature !== 'string' || signature.length !== 64 || typeof secret !== 'string' || secret === '') {
    return false;
  }

  return timingSafeEqual(Buffer.from(signature), Buffer.from(signPayload(body, secret)));
}

export function bearerMatches(header, token) {
  if (typeof header !== 'string' || !header.startsWith('Bearer ') || typeof token !== 'string' || token === '') {
    return false;
  }

  const supplied = Buffer.from(header.slice(7));
  const expected = Buffer.from(token);

  return supplied.length === expected.length && timingSafeEqual(supplied, expected);
}

export class JsonIdempotencyStore {
  constructor(directory) {
    this.path = join(directory, '.outbound-idempotency.json');
    this.entries = new Map();
  }

  async load() {
    try {
      const contents = await readFile(this.path, 'utf8');
      const parsed = JSON.parse(contents);
      if (parsed && typeof parsed === 'object') {
        for (const [key, value] of Object.entries(parsed)) {
          if (value && typeof value === 'object') {
            this.entries.set(key, value);
          }
        }
      }
    } catch (error) {
      if (error.code !== 'ENOENT') {
        throw error;
      }
    }
  }

  get(key) {
    return this.entries.get(key);
  }

  async set(key, value) {
    this.entries.set(key, value);
    await mkdir(join(this.path, '..'), { recursive: true });
    const temporary = `${this.path}.tmp`;
    await writeFile(temporary, JSON.stringify(Object.fromEntries(this.entries)), { mode: 0o600 });
    await rename(temporary, this.path);
  }
}

export class WhatsAppBridge {
  constructor({
    client,
    store,
    accountId = null,
    webhookUrl = '',
    webhookSecret = '',
    fetcher = fetch,
    logger = console,
    beforeStart = async () => {},
  }) {
    this.client = client;
    this.store = store;
    this.accountId = accountId;
    this.webhookUrl = webhookUrl;
    this.webhookSecret = webhookSecret;
    this.fetcher = fetcher;
    this.logger = logger;
    this.beforeStart = beforeStart;
    this.state = { status: 'starting', qr: null, lastError: null, readyAt: null };
    this.pending = new Map();
  }

  async start() {
    await this.store.load();
    this.client.on('qr', (qr) => {
      this.state = { ...this.state, status: 'qr', qr, lastError: null };
    });
    this.client.on('authenticated', () => {
      this.state = { ...this.state, status: 'authenticated', qr: null, lastError: null };
    });
    this.client.on('ready', () => {
      this.state = {
        ...this.state,
        status: 'ready',
        qr: null,
        lastError: null,
        readyAt: new Date().toISOString(),
      };
    });
    this.client.on('auth_failure', (message) => {
      this.state = { ...this.state, status: 'auth_failure', lastError: String(message) };
    });
    this.client.on('disconnected', (reason) => {
      this.state = { ...this.state, status: 'disconnected', lastError: String(reason) };
    });
    this.client.on('message_ack', (message, ack) => {
      void this.handleAck(message, ack).catch((error) =>
        this.logger.error(`WhatsApp callback failed: ${error.message}`),
      );
    });
    await this.beforeStart();
    await this.client.initialize();
  }

  status() {
    const info = this.client.info;

    return {
      account_id: this.accountId,
      ...this.state,
      qr: this.state.status === 'qr' ? this.state.qr : null,
      phone: typeof info?.wid?.user === 'string' ? info.wid.user : null,
      push_name: typeof info?.pushname === 'string' ? info.pushname : null,
    };
  }

  async disconnect() {
    try {
      if (typeof this.client.logout === 'function') {
        await this.client.logout();
      }
    } catch (error) {
      this.logger.warn(`WhatsApp logout failed: ${error.message}`);
    }

    if (typeof this.client.destroy === 'function') {
      await this.client.destroy();
    }

    this.state = { ...this.state, status: 'disconnected', qr: null, lastError: null };
  }

  async send({ idempotencyKey, to, body }) {
    if (
      typeof idempotencyKey !== 'string' ||
      idempotencyKey.trim() === '' ||
      typeof body !== 'string' ||
      body.trim() === ''
    ) {
      throw new TypeError('idempotency_key and body are required.');
    }

    const payloadHash = createHash('sha256').update(JSON.stringify({ to, body })).digest('hex');
    const existing = this.store.get(idempotencyKey);
    if (existing) {
      if (existing.payload_hash !== payloadHash) {
        throw new IdempotencyConflictError('The idempotency key was already used for a different message.');
      }

      return { ...existing, replayed: true };
    }
    if (this.state.status !== 'ready') {
      throw new BridgeNotReadyError(`WhatsApp is not ready (status: ${this.state.status}).`);
    }

    const chatId = normalizeRecipient(to);
    if (!this.pending.has(idempotencyKey)) {
      this.pending.set(idempotencyKey, this.dispatch({ idempotencyKey, chatId, body, payloadHash }));
    }

    try {
      return await this.pending.get(idempotencyKey);
    } finally {
      this.pending.delete(idempotencyKey);
    }
  }

  async dispatch({ idempotencyKey, chatId, body, payloadHash }) {
    const message = await this.client.sendMessage(chatId, body);
    const providerMessageId = message?.id?._serialized ?? message?.id?.id ?? message?.id;
    if (typeof providerMessageId !== 'string' || providerMessageId === '') {
      throw new Error('WhatsApp did not return a provider message ID.');
    }

    const result = {
      idempotency_key: idempotencyKey,
      provider_message_id: providerMessageId,
      payload_hash: payloadHash,
    };
    await this.store.set(idempotencyKey, result);
    this.pending.set(providerMessageId, idempotencyKey);
    await this.notify({ id: providerMessageId, message_id: providerMessageId, status: 'sent' });

    return { ...result, replayed: false };
  }

  async handleAck(message, ack) {
    const providerMessageId = message?.id?._serialized ?? message?.id?.id;
    if (typeof providerMessageId !== 'string' || !this.pending.has(providerMessageId)) {
      return;
    }

    const status = ack === -1 ? 'failed' : ack >= 3 ? 'delivered' : ack >= 1 ? 'sent' : null;
    if (status === null) {
      return;
    }

    await this.notify({ id: providerMessageId, message_id: providerMessageId, status, ack });
  }

  async notify(payload) {
    if (!this.webhookUrl || !this.webhookSecret) {
      return;
    }

    const body = JSON.stringify(this.accountId === null ? payload : { account_id: this.accountId, ...payload });
    const response = await this.fetcher(this.webhookUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Signature': signPayload(body, this.webhookSecret),
      },
      body,
    });
    if (!response.ok) {
      this.logger.error(`WhatsApp webhook returned HTTP ${response.status}`);
    }
  }
}

export function normalizeRecipient(value) {
  if (typeof value !== 'string' || value.trim() === '') {
    throw new TypeError('Recipient is required.');
  }

  const trimmed = value.trim();
  if (/@(?:c|g)\.us$/.test(trimmed)) {
    return trimmed;
  }

  const digits = trimmed.replace(/\D/g, '');
  if (!/^\d{8,15}$/.test(digits)) {
    throw new TypeError('Recipient must be an international phone number.');
  }

  return `${digits}@c.us`;
}

export class WhatsAppBridgeManager {
  constructor({ clientFactory, sessionPath, webhookUrl = '', webhookSecret = '', logger = console }) {
    this.clientFactory = clientFactory;
    this.sessionPath = sessionPath;
    this.webhookUrl = webhookUrl;
    this.webhookSecret = webhookSecret;
    this.logger = logger;
    this.bridges = new Map();
    this.starting = new Map();
  }

  async ensure(accountId) {
    this.validateAccountId(accountId);
    const existing = this.bridges.get(accountId);
    if (existing) {
      return existing;
    }

    const pending = this.starting.get(accountId);
    if (pending) {
      return pending;
    }

    const start = (async () => {
      const profilePath = join(this.sessionPath, `session-${accountId}`);
      const bridge = new WhatsAppBridge({
        accountId,
        client: this.clientFactory(accountId),
        store: new JsonIdempotencyStore(join(this.sessionPath, `account-${accountId}`)),
        webhookUrl: this.webhookUrl,
        webhookSecret: this.webhookSecret,
        logger: this.logger,
        beforeStart: async () => {
          const removed = await clearStaleChromiumProfileLocks(profilePath);
          if (removed > 0) {
            this.logger.warn(`Removed ${removed} stale Chromium profile lock(s) for ${accountId}.`);
          }
        },
      });
      this.bridges.set(accountId, bridge);

      try {
        await bridge.start();
      } catch (error) {
        this.bridges.delete(accountId);
        throw error;
      }

      return bridge;
    })();

    this.starting.set(accountId, start);
    try {
      return await start;
    } finally {
      this.starting.delete(accountId);
    }
  }

  async send(accountId, payload) {
    const bridge = await this.ensure(accountId);

    return bridge.send(payload);
  }

  async disconnect(accountId, restart = true) {
    this.validateAccountId(accountId);
    const bridge = this.bridges.get(accountId);
    if (bridge) {
      await bridge.disconnect();
      this.bridges.delete(accountId);
    }

    return restart
      ? (await this.ensure(accountId)).status()
      : { account_id: accountId, status: 'disconnected', qr: null };
  }

  async remove(accountId) {
    this.validateAccountId(accountId);
    const pending = this.starting.get(accountId);
    if (pending) {
      await pending.catch(() => undefined);
    }

    const bridge = this.bridges.get(accountId);
    if (bridge) {
      await bridge.disconnect();
      this.bridges.delete(accountId);
    }

    await Promise.all([
      rm(join(this.sessionPath, `session-${accountId}`), { recursive: true, force: true }),
      rm(join(this.sessionPath, `account-${accountId}`), { recursive: true, force: true }),
    ]);

    return { account_id: accountId, status: 'deleted' };
  }

  status(accountId) {
    this.validateAccountId(accountId);
    const bridge = this.bridges.get(accountId);

    if (bridge) {
      return bridge.status();
    }

    if (!this.starting.has(accountId)) {
      void this.ensure(accountId).catch((error) => {
        this.logger.error(`WhatsApp account ${accountId} failed to start: ${error.message}`);
      });
    }

    return { account_id: accountId, status: 'starting', qr: null, lastError: null, readyAt: null };
  }

  statuses() {
    return [...this.bridges.values()].map((bridge) => bridge.status());
  }

  summary() {
    const statuses = this.statuses();
    if (statuses.length === 0) {
      return { status: 'idle', accounts: 0, qr: null };
    }

    const preferred =
      statuses.find((status) => status.status === 'ready') ??
      statuses.find((status) => status.status === 'qr') ??
      statuses[0];

    return { ...preferred, accounts: statuses.length };
  }

  validateAccountId(accountId) {
    if (typeof accountId !== 'string' || /^[A-Za-z0-9_-]{1,80}$/.test(accountId) === false) {
      throw new TypeError('A safe account_id is required.');
    }
  }
}
