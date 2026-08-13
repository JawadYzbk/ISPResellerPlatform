import { Head, router, usePage } from '@inertiajs/react';
import { Monitor, Smartphone, Trash2 } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Session = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    current: boolean;
};

export default function Sessions({ sessions }: { sessions: Session[] }) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    return (
        <AppLayout>
            <Head title={t('Active sessions')} />
            <div className="max-w-3xl">
                <p className="eyebrow">{t('Security')}</p>
                <h1 className="page-title">{t('Active sessions')}</h1>
                <p className="page-subtitle">{t('security.sessions_description')}</p>
                <div className="card mt-8 divide-y divide-line overflow-hidden">
                    {sessions.map((session) => {
                        const mobile = /mobile|android|iphone/i.test(session.user_agent ?? '');
                        return (
                            <div key={session.id} className="flex items-center gap-4 p-5">
                                <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                    {mobile ? <Smartphone size={18} /> : <Monitor size={18} />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">
                                        {session.user_agent ?? t('security.unknown_device')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted">
                                        {session.ip_address ?? t('Unknown IP')}{' '}
                                        {session.current ? `· ${t('Current session')}` : ''}
                                    </p>
                                </div>
                                {!session.current && (
                                    <button
                                        type="button"
                                        onClick={() => router.delete(`/security/sessions/${session.id}`)}
                                        className="rounded-lg p-2 text-muted hover:bg-rose-50 hover:text-coral"
                                        title={t('security.revoke_session')}
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
