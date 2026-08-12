import assert from 'node:assert/strict';
import { createServer } from 'vite';

const allowedOrigins = ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000'];
const rejectedOrigins = ['http://127.0.0.1:5173', 'http://example.test:8000'];
const server = await createServer({
    configFile: 'vite.config.js',
    server: { port: 0, strictPort: false },
});

try {
    await server.listen();
    const address = server.httpServer?.address();
    assert.equal(typeof address, 'object');
    assert.ok(address && 'port' in address);

    for (const origin of allowedOrigins) {
        const response = await fetch(`http://127.0.0.1:${address.port}/@vite/client`, {
            headers: { Origin: origin },
        });

        assert.equal(response.status, 200, `Vite client should load for ${origin}`);
        assert.equal(response.headers.get('access-control-allow-origin'), origin);
    }

    for (const origin of rejectedOrigins) {
        const response = await fetch(`http://127.0.0.1:${address.port}/@vite/client`, {
            headers: { Origin: origin },
        });

        assert.equal(response.status, 200, `Vite client should still respond for ${origin}`);
        assert.equal(response.headers.get('access-control-allow-origin'), null);
    }

    console.log('Vite loopback CORS contract passed.');
} finally {
    await server.close();
}
