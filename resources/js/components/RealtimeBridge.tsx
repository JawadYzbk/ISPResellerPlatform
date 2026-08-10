import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import { getRealtime } from '@/lib/realtime';
import type { PageProps } from '@/types';

export default function RealtimeBridge() {
    const { auth } = usePage<PageProps>().props;
    const tenantId = auth.tenant?.id;
    const userId = auth.user?.id;

    useEffect(() => {
        if (!userId || !tenantId) {
            return;
        }

        const echo = getRealtime();
        if (echo === null) {
            return;
        }

        const channelName = `tenant.${tenantId}`;
        let refreshTimer: number | null = null;
        const handleStatusChange = () => {
            if (refreshTimer !== null) {
                return;
            }

            refreshTimer = window.setTimeout(() => {
                refreshTimer = null;
                router.reload();
            }, 250);
        };
        const channel = echo.private(channelName);

        channel.listen('.service.status.changed', handleStatusChange);

        return () => {
            if (refreshTimer !== null) {
                window.clearTimeout(refreshTimer);
            }
            channel.stopListening('.service.status.changed', handleStatusChange);
            echo.leave(channelName);
        };
    }, [tenantId, userId]);

    return null;
}
