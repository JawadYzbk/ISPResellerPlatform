import { spawn } from 'node:child_process';

const child = spawn(process.execPath, ['integrations/whatsapp-web/src/index.js'], {
    cwd: process.cwd(),
    env: {
        ...process.env,
        WHATSAPP_WEB_SESSION_PATH: process.env.WHATSAPP_WEB_SESSION_PATH || 'integrations/whatsapp-web/.wwebjs_auth',
    },
    stdio: 'inherit',
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => child.kill(signal));
}

child.on('exit', (code, signal) => {
    if (signal) {
        process.kill(process.pid, signal);
    }

    process.exit(code ?? 1);
});
