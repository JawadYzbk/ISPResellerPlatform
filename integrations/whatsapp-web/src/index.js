import http from 'node:http';
import { join } from 'node:path';
import whatsappWeb from 'whatsapp-web.js';
import {
    clearStaleChromiumProfileLocks,
    JsonIdempotencyStore,
    WhatsAppBridge,
    bearerMatches,
    isHealthyStatus,
} from './bridge.js';

const { Client, LocalAuth } = whatsappWeb;

const config = {
    host: process.env.WHATSAPP_WEB_HOST || '0.0.0.0',
    port: Number.parseInt(process.env.WHATSAPP_WEB_PORT || '3001', 10),
    token: process.env.WHATSAPP_WEB_TOKEN || '',
    webhookUrl: process.env.WHATSAPP_WEBHOOK_URL || '',
    webhookSecret: process.env.WHATSAPP_WEBHOOK_SECRET || '',
    sessionPath: process.env.WHATSAPP_WEB_SESSION_PATH || './.wwebjs_auth',
    clientId: process.env.WHATSAPP_WEB_CLIENT_ID || 'isp-manager',
    headless: process.env.WHATSAPP_WEB_HEADLESS !== 'false',
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '',
};
const sessionProfilePath = join(config.sessionPath, `session-${config.clientId}`);

if (!config.token) {
    throw new Error('WHATSAPP_WEB_TOKEN is required.');
}
if (config.webhookUrl && !config.webhookSecret) {
    throw new Error('WHATSAPP_WEBHOOK_SECRET is required when WHATSAPP_WEBHOOK_URL is set.');
}

const client = new Client({
    authStrategy: new LocalAuth({ clientId: config.clientId, dataPath: config.sessionPath }),
    puppeteer: {
        ...(config.executablePath ? { executablePath: config.executablePath } : {}),
        headless: config.headless,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    },
});
const bridge = new WhatsAppBridge({
    client,
    store: new JsonIdempotencyStore(config.sessionPath),
    webhookUrl: config.webhookUrl,
    webhookSecret: config.webhookSecret,
    beforeStart: async () => {
        const removed = await clearStaleChromiumProfileLocks(sessionProfilePath);
        if (removed > 0) {
            console.warn(`Removed ${removed} stale Chromium profile lock(s) before initialization.`);
        }
    },
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

const server = http.createServer(async (request, response) => {
    try {
        if (request.method === 'GET' && request.url === '/health') {
            const status = bridge.status();
            return json(response, isHealthyStatus(status.status) ? 200 : 503, {
                ok: isHealthyStatus(status.status),
                service: 'whatsapp-web',
                status: status.status,
            });
        }
        if (!bearerMatches(request.headers.authorization, config.token)) {
            return json(response, 401, { error: 'unauthorized' });
        }
        if (request.method === 'GET' && request.url === '/status') {
            return json(response, 200, bridge.status());
        }
        if (request.method === 'GET' && request.url === '/qr') {
            const status = bridge.status();
            return json(
                response,
                status.status === 'qr' ? 200 : 404,
                status.status === 'qr' ? { qr: status.qr } : { error: 'qr_unavailable' },
            );
        }
        if (request.method === 'POST' && request.url === '/messages') {
            const payload = await readJson(request);
            const result = await bridge.send({
                idempotencyKey: payload.idempotency_key,
                to: payload.to,
                body: payload.body,
            });
            return json(response, result.replayed ? 200 : 201, result);
        }

        return json(response, 404, { error: 'not_found' });
    } catch (error) {
        const status =
            error.name === 'BridgeNotReadyError' ? 503 : error.name === 'IdempotencyConflictError' ? 409 : 422;
        return json(response, status, { error: error.message });
    }
});

server.listen(config.port, config.host, () => {
    console.log(`WhatsApp Web bridge listening on ${config.host}:${config.port}`);
});

bridge.start().catch((error) => {
    console.error(`WhatsApp initialization failed: ${error.message}`);
    process.exitCode = 1;
});
