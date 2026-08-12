import { WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function OfflineBanner() {
    const [online, setOnline] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine));

    useEffect(() => {
        const markOnline = () => setOnline(true);
        const markOffline = () => setOnline(false);

        window.addEventListener('online', markOnline);
        window.addEventListener('offline', markOffline);

        return () => {
            window.removeEventListener('online', markOnline);
            window.removeEventListener('offline', markOffline);
        };
    }, []);

    if (online) return null;

    return (
        <div
            data-testid="offline-banner"
            className="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm font-medium text-amber-900 lg:px-8"
            role="status"
            aria-live="polite"
        >
            <WifiOff size={16} aria-hidden="true" />
            <span>Offline. Keep typed changes safe and submit them when the connection returns.</span>
        </div>
    );
}
