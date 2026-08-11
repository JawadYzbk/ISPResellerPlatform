import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { EventEmitter } from 'node:events';
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  clearStaleChromiumProfileLocks,
  JsonIdempotencyStore,
  WhatsAppBridge,
  hasValidSignature,
  isHealthyStatus,
  normalizeRecipient,
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
    await assert.rejects(() => bridge.send({ idempotencyKey: 'message-002', to: '96170123456', body: 'Hello' }), /not ready/);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
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
