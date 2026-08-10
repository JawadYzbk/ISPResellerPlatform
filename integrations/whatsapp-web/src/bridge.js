import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { createHash, createHmac, timingSafeEqual } from 'node:crypto';
import { join } from 'node:path';

export class BridgeNotReadyError extends Error {}

export class IdempotencyConflictError extends Error {}

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
  constructor({ client, store, webhookUrl = '', webhookSecret = '', fetcher = fetch, logger = console }) {
    this.client = client;
    this.store = store;
    this.webhookUrl = webhookUrl;
    this.webhookSecret = webhookSecret;
    this.fetcher = fetcher;
    this.logger = logger;
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
      this.state = { ...this.state, status: 'ready', qr: null, lastError: null, readyAt: new Date().toISOString() };
    });
    this.client.on('auth_failure', (message) => {
      this.state = { ...this.state, status: 'auth_failure', lastError: String(message) };
    });
    this.client.on('disconnected', (reason) => {
      this.state = { ...this.state, status: 'disconnected', lastError: String(reason) };
    });
    this.client.on('message_ack', (message, ack) => {
      void this.handleAck(message, ack).catch((error) => this.logger.error(`WhatsApp callback failed: ${error.message}`));
    });
    await this.client.initialize();
  }

  status() {
    return { ...this.state, qr: this.state.status === 'qr' ? this.state.qr : null };
  }

  async send({ idempotencyKey, to, body }) {
    if (typeof idempotencyKey !== 'string' || idempotencyKey.trim() === '' || typeof body !== 'string' || body.trim() === '') {
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

    const result = { idempotency_key: idempotencyKey, provider_message_id: providerMessageId, payload_hash: payloadHash };
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

    const body = JSON.stringify(payload);
    const response = await this.fetcher(this.webhookUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Webhook-Signature': signPayload(body, this.webhookSecret) },
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
