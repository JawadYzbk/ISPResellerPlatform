import http from 'node:http';
import whatsappWeb from 'whatsapp-web.js';
import { WhatsAppBridgeManager, bearerMatches, isHealthyStatus } from './bridge.js';

const { Client, LocalAuth } = whatsappWeb;

const config = {
  host: process.env.WHATSAPP_WEB_HOST || '0.0.0.0',
  port: Number.parseInt(process.env.WHATSAPP_WEB_PORT || '3001', 10),
  token: process.env.WHATSAPP_WEB_TOKEN || '',
  webhookUrl: process.env.WHATSAPP_WEBHOOK_URL || '',
  webhookSecret: process.env.WHATSAPP_WEBHOOK_SECRET || '',
  sessionPath: process.env.WHATSAPP_WEB_SESSION_PATH || './.wwebjs_auth',
  defaultAccountId: process.env.WHATSAPP_WEB_CLIENT_ID || 'isp-manager',
  headless: process.env.WHATSAPP_WEB_HEADLESS !== 'false',
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '',
};

if (!config.token) {
  throw new Error('WHATSAPP_WEB_TOKEN is required.');
}
if (config.webhookUrl && !config.webhookSecret) {
  throw new Error('WHATSAPP_WEBHOOK_SECRET is required when WHATSAPP_WEBHOOK_URL is set.');
}

const manager = new WhatsAppBridgeManager({
  sessionPath: config.sessionPath,
  webhookUrl: config.webhookUrl,
  webhookSecret: config.webhookSecret,
  clientFactory: (accountId) =>
    new Client({
      authStrategy: new LocalAuth({ clientId: accountId, dataPath: config.sessionPath }),
      puppeteer: {
        ...(config.executablePath ? { executablePath: config.executablePath } : {}),
        headless: config.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
      },
    }),
});

function json(response, status, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(status, { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) });
  response.end(body);
}

async function readJson(request) {
  let body = '';
  for await (const chunk of request) {
    body += chunk;
    if (body.length > 64 * 1024) {
      throw new Error('Request body too large.');
    }
  }

  return JSON.parse(body || '{}');
}

function accountPath(pathname) {
  const parts = pathname.split('/').filter(Boolean);
  if (parts[0] !== 'accounts' || typeof parts[1] !== 'string') {
    return null;
  }

  return { accountId: decodeURIComponent(parts[1]), action: parts[2] || 'status' };
}

const server = http.createServer(async (request, response) => {
  try {
    const url = new URL(request.url || '/', `http://${config.host}:${config.port}`);
    if (request.method === 'GET' && url.pathname === '/health') {
      const summary = manager.summary();
      return json(response, isHealthyStatus(summary.status) ? 200 : 503, {
        ok: isHealthyStatus(summary.status),
        service: 'whatsapp-web',
        status: summary.status,
        accounts: summary.accounts,
      });
    }
    if (!bearerMatches(request.headers.authorization, config.token)) {
      return json(response, 401, { error: 'unauthorized' });
    }
    if (request.method === 'GET' && url.pathname === '/status') {
      const accountId = url.searchParams.get('account_id');
      return json(response, 200, accountId ? await manager.status(accountId) : manager.summary());
    }
    if (request.method === 'GET' && url.pathname === '/accounts') {
      return json(response, 200, { accounts: manager.statuses() });
    }
    if (request.method === 'POST' && url.pathname === '/accounts') {
      const payload = await readJson(request);
      const status = await manager.status(payload.account_id);
      return json(response, 201, status);
    }
    if (request.method === 'GET' && url.pathname === '/qr') {
      const status = await manager.status(config.defaultAccountId);
      return json(
        response,
        status.status === 'qr' ? 200 : 404,
        status.status === 'qr' ? { qr: status.qr } : { error: 'qr_unavailable' },
      );
    }
    if (request.method === 'POST' && url.pathname === '/messages') {
      const payload = await readJson(request);
      const result = await manager.send(config.defaultAccountId, {
        idempotencyKey: payload.idempotency_key,
        to: payload.to,
        body: payload.body,
      });
      return json(response, result.replayed ? 200 : 201, result);
    }

    const account = accountPath(url.pathname);
    if (account && account.action === 'status' && request.method === 'GET') {
      return json(response, 200, await manager.status(account.accountId));
    }
    if (account && account.action === 'qr' && request.method === 'GET') {
      const status = await manager.status(account.accountId);
      return json(
        response,
        status.status === 'qr' ? 200 : 404,
        status.status === 'qr' ? { qr: status.qr } : { error: 'qr_unavailable' },
      );
    }
    if (account && account.action === 'disconnect' && request.method === 'POST') {
      const payload = await readJson(request);
      const restart = payload.restart !== false;
      return json(response, 200, await manager.disconnect(account.accountId, restart));
    }
    if (account && account.action === 'messages' && request.method === 'POST') {
      const payload = await readJson(request);
      const result = await manager.send(account.accountId, {
        idempotencyKey: payload.idempotency_key,
        to: payload.to,
        body: payload.body,
      });
      return json(response, result.replayed ? 200 : 201, result);
    }

    return json(response, 404, { error: 'not_found' });
  } catch (error) {
    const status = error.name === 'BridgeNotReadyError' ? 503 : error.name === 'IdempotencyConflictError' ? 409 : 422;
    return json(response, status, { error: error.message });
  }
});

server.listen(config.port, config.host, () => {
  console.log(`WhatsApp Web bridge listening on ${config.host}:${config.port}`);
});
