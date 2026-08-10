import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type RealtimeEcho = Echo<'reverb'>;

let echo: RealtimeEcho | null | undefined;

export function getRealtime(): RealtimeEcho | null {
    if (echo !== undefined) {
        return echo;
    }

    const enabled = import.meta.env.VITE_REVERB_ENABLED === 'true';
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST;
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    if (!enabled || !key || !host) {
        echo = null;
        return echo;
    }

    const forceTLS = scheme === 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT) || (forceTLS ? 443 : 80);
    (window as Window & { Pusher?: typeof Pusher }).Pusher = Pusher;

    try {
        echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });
    } catch {
        echo = null;
    }

    return echo;
}
