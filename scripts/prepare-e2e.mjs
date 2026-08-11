import { createConnection } from 'node:net';
import { readFile, rm } from 'node:fs/promises';

const hotFile = new URL('../public/hot', import.meta.url);

async function isListening(url) {
    const parsed = new URL(url);
    const host = parsed.hostname === 'localhost' ? '127.0.0.1' : parsed.hostname;

    return new Promise((resolve) => {
        const socket = createConnection({ host, port: Number(parsed.port || 80) });
        const finish = (listening) => {
            socket.destroy();
            resolve(listening);
        };

        socket.setTimeout(500);
        socket.once('connect', () => finish(true));
        socket.once('timeout', () => finish(false));
        socket.once('error', () => finish(false));
    });
}

try {
    const hotUrl = (await readFile(hotFile, 'utf8')).trim();

    if (hotUrl && !(await isListening(hotUrl))) {
        await rm(hotFile, { force: true });
        console.log('Removed stale Vite hot marker; E2E will use the production build.');
    }
} catch (error) {
    if (error.code !== 'ENOENT') {
        throw error;
    }
}
