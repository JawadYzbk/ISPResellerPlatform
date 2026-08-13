import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { EventEmitter } from 'node:events';
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  clearStaleChromiumProfileLocks,
  JsonIdempotencyStore,
  removeSessionDirectory,
  WhatsAppBridge,
  WhatsAppBridgeManager,
  hasValidSignature,
  isHealthyStatus,
  normalizeRecipient,
  resourceSnapshot,
  signPayload,
} from '../src/bridge.js';

class FakeClient extends EventEmitter {
  constructor() {
    super();
    this.sent = [];
  }

  async initialize() {}

  async sendMessage(to, body) {
    this.sent.push({ to, body });
    return { id: { _serialized: `wamid-${this.sent.length}` } };
  }

  async logout() {
    this.loggedOut = true;
  }

  async destroy() {
    this.destroyed = true;
  }
}

test('sends once and replays a durable idempotency result', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  try {
    const client = new FakeClient();
    const bridge = new WhatsAppBridge({ client, store: new JsonIdempotencyStore(directory) });
    await bridge.start();
    client.emit('ready');

    const first = await bridge.send({ idempotencyKey: 'message-001', to: '+961 70 123 456', body: 'Hello' });
    const second = await bridge.send({ idempotencyKey: 'message-001', to: '+961 70 123 456', body: 'Hello' });

    assert.equal(first.provider_message_id, 'wamid-1');
    assert.equal(second.replayed, true);
    assert.equal(client.sent.length, 1);
    assert.deepEqual(client.sent[0], { to: '96170123456@c.us', body: 'Hello' });
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('serializes different outbound messages for one account', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  try {
    const client = new FakeClient();
    let inFlight = 0;
    let peakInFlight = 0;
    client.sendMessage = async (to, body) => {
      inFlight++;
      peakInFlight = Math.max(peakInFlight, inFlight);
      await new Promise((resolve) => setTimeout(resolve, 5));
      client.sent.push({ to, body });
      inFlight--;
      return { id: { _serialized: `wamid-${client.sent.length}` } };
    };
    const bridge = new WhatsAppBridge({
      client,
      store: new JsonIdempotencyStore(directory),
      minIntervalMs: 0,
      jitterMs: 0,
    });
    await bridge.start();
    client.emit('ready');

    await Promise.all([
      bridge.send({ idempotencyKey: 'message-a', to: '+961 70 123 456', body: 'A' }),
      bridge.send({ idempotencyKey: 'message-b', to: '+961 70 123 456', body: 'B' }),
    ]);

    assert.equal(peakInFlight, 1);
    assert.equal(client.sent.length, 2);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('signatures, recipients and readiness are enforced', async () => {
  const body = '{"ok":true}';
  assert.equal(hasValidSignature(body, signPayload(body, 'secret'), 'secret'), true);
  assert.equal(hasValidSignature(body, 'invalid', 'secret'), false);
  assert.equal(isHealthyStatus('qr'), true);
  assert.equal(isHealthyStatus('ready'), true);
  assert.equal(isHealthyStatus('disconnected'), false);
  assert.equal(normalizeRecipient('96170123456'), '96170123456@c.us');
  assert.throws(() => normalizeRecipient('123'), /international phone number/);

  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  try {
    const bridge = new WhatsAppBridge({ client: new FakeClient(), store: new JsonIdempotencyStore(directory) });
    await assert.rejects(
      () => bridge.send({ idempotencyKey: 'message-002', to: '96170123456', body: 'Hello' }),
      /not ready/,
    );
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('reports account and process resource counters without exposing session data', () => {
  assert.deepEqual(
    resourceSnapshot({
      statuses: [{ status: 'ready' }, { status: 'qr' }, { status: 'disconnected' }],
      memoryUsage: { rss: 157286400, heapUsed: 52428800, external: 1048576 },
      uptime: 91.9,
    }),
    {
      uptime_seconds: 91,
      active_accounts: 3,
      ready_accounts: 1,
      qr_accounts: 1,
      rss_mb: 150,
      heap_used_mb: 50,
      external_mb: 1,
    },
  );
});

test('clears stale Chromium profile locks before initialization', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  const profile = join(directory, 'session-isp-manager');
  try {
    await mkdir(profile, { recursive: true });
    await writeFile(join(profile, 'SingletonLock'), 'stale');
    await writeFile(join(profile, 'SingletonCookie'), 'stale');
    await writeFile(join(profile, 'preferences'), '{}');

    assert.equal(await clearStaleChromiumProfileLocks(directory), 2);
    await assert.rejects(() => readFile(join(profile, 'SingletonLock')), { code: 'ENOENT' });
    assert.equal(await readFile(join(profile, 'preferences'), 'utf8'), '{}');

    const client = new FakeClient();
    let prepared = false;
    const bridge = new WhatsAppBridge({
      client,
      store: new JsonIdempotencyStore(directory),
      beforeStart: async () => {
        prepared = (await clearStaleChromiumProfileLocks(directory)) === 0;
      },
    });
    await bridge.start();
    assert.equal(prepared, true);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('returns a starting status while an account client initializes', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  let releaseInitialization;
  const initialization = new Promise((resolve) => {
    releaseInitialization = resolve;
  });

  try {
    const manager = new WhatsAppBridgeManager({
      sessionPath: directory,
      clientFactory: () => {
        const client = new FakeClient();
        client.initialize = () => initialization;
        return client;
      },
    });

    const status = await manager.status('slow-account');
    assert.equal(status.status, 'starting');
    assert.equal(status.account_id, 'slow-account');

    const startup = manager.starting.get('slow-account');
    assert.ok(startup);
    releaseInitialization();
    await startup;
    assert.equal((await manager.status('slow-account')).status, 'starting');
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('removes an account while its client is still initializing', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  let releaseInitialization;
  const initialization = new Promise((resolve) => {
    releaseInitialization = resolve;
  });

  try {
    const clients = new Map();
    const manager = new WhatsAppBridgeManager({
      sessionPath: directory,
      clientFactory: (accountId) => {
        const client = new FakeClient();
        client.initialize = () => initialization;
        clients.set(accountId, client);
        return client;
      },
    });

    await manager.status('removing-account');
    const removed = await manager.remove('removing-account');

    assert.equal(removed.status, 'deleted');
    assert.equal(clients.get('removing-account').destroyed, true);
    releaseInitialization();
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('does not initialize after disconnect is requested during preparation', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  let releasePreparation;
  let preparationStarted;
  const preparation = new Promise((resolve) => {
    releasePreparation = resolve;
  });
  const preparationStartedPromise = new Promise((resolve) => {
    preparationStarted = resolve;
  });

  try {
    const client = new FakeClient();
    let initializeCalls = 0;
    client.initialize = async () => {
      initializeCalls++;
    };
    const bridge = new WhatsAppBridge({
      client,
      store: new JsonIdempotencyStore(directory),
      beforeStart: async () => {
        preparationStarted();
        await preparation;
      },
    });

    const startup = bridge.start();
    await preparationStartedPromise;
    await bridge.disconnect();
    releasePreparation();
    await startup;

    assert.equal(initializeCalls, 0);
    assert.equal(client.destroyed, true);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('terminates the browser when initialization fails', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  const exits = new EventEmitter();
  let killed = false;

  try {
    const client = new FakeClient();
    client.initialize = async () => {
      throw new Error('browser initialization failed');
    };
    client.pupBrowser = {
      process: () => ({
        pid: 0,
        killed: false,
        kill: () => {
          killed = true;
          exits.emit('exit');
        },
        once: (event, listener) => exits.once(event, listener),
      }),
    };
    const bridge = new WhatsAppBridge({ client, store: new JsonIdempotencyStore(directory) });

    await assert.rejects(() => bridge.start(), /browser initialization failed/);
    assert.equal(killed, true);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('retries session cleanup while Chromium releases profile locks', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  const profile = join(directory, 'session-locked-account');
  let attempts = 0;

  try {
    await mkdir(profile, { recursive: true });
    await writeFile(join(profile, 'lockfile'), 'busy');

    await removeSessionDirectory(profile, {
      removePath: async (path, options) => {
        attempts++;
        if (attempts < 3) {
          const error = new Error('profile is still locked');
          error.code = 'EBUSY';
          throw error;
        }

        return rm(path, options);
      },
      retries: 3,
      retryDelayMs: 0,
    });

    assert.equal(attempts, 3);
    await assert.rejects(() => readFile(profile), { code: 'ENOENT' });
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('terminates a browser process when a client operation times out', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  const exits = new EventEmitter();
  let killed = false;

  try {
    const client = new FakeClient();
    client.destroy = () => new Promise(() => {});
    client.pupBrowser = {
      process: () => ({
        killed: false,
        kill: () => {
          killed = true;
          exits.emit('exit');
        },
        once: (event, listener) => exits.once(event, listener),
      }),
    };
    const bridge = new WhatsAppBridge({ client, store: new JsonIdempotencyStore(directory) });

    await bridge.clientOperation('destroy');

    assert.equal(killed, true);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('restarts pairing without waiting for client initialization', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  let releaseInitialization;
  const initialization = new Promise((resolve) => {
    releaseInitialization = resolve;
  });

  try {
    const manager = new WhatsAppBridgeManager({
      sessionPath: directory,
      clientFactory: () => {
        const client = new FakeClient();
        client.initialize = () => initialization;
        return client;
      },
    });

    await manager.status('restarting-account');
    const restarted = await manager.disconnect('restarting-account');

    assert.equal(restarted.status, 'starting');
    releaseInitialization();
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('keeps multiple account sessions isolated and disconnects one account without affecting another', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'isp-whatsapp-'));
  const clients = new Map();
  try {
    const manager = new WhatsAppBridgeManager({
      sessionPath: directory,
      clientFactory: (accountId) => {
        const client = new FakeClient();
        clients.set(accountId, client);
        return client;
      },
    });

    const billing = await manager.ensure('billing-account');
    const support = await manager.ensure('support-account');
    clients.get('billing-account').emit('ready');
    clients.get('support-account').emit('ready');

    assert.equal(billing.status().account_id, 'billing-account');
    assert.equal(support.status().account_id, 'support-account');
    await manager.send('billing-account', {
      idempotencyKey: 'billing-001',
      to: '+961 70 123 456',
      body: 'Billing',
    });
    await manager.send('support-account', {
      idempotencyKey: 'support-001',
      to: '+961 71 123 456',
      body: 'Support',
    });
    assert.deepEqual(clients.get('billing-account').sent[0], { to: '96170123456@c.us', body: 'Billing' });
    assert.deepEqual(clients.get('support-account').sent[0], { to: '96171123456@c.us', body: 'Support' });

    const disconnected = await manager.disconnect('support-account', false);
    assert.equal(disconnected.status, 'disconnected');
    assert.equal(clients.get('support-account').destroyed, true);
    assert.equal((await manager.status('billing-account')).status, 'ready');

    const removed = await manager.remove('billing-account');
    assert.equal(removed.status, 'deleted');
    assert.equal(clients.get('billing-account').destroyed, true);
    assert.equal(manager.bridges.has('billing-account'), false);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});
