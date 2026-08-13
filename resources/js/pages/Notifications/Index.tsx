import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, CircleAlert, TriangleAlert } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { AttentionQueueItem } from '@/types';
import type { PageProps } from '@/types';

type Props = { attentionQueue: AttentionQueueItem[] };

const severityStyles: Record<AttentionQueueItem['severity'], string> = {
    critical: 'bg-rose-50 text-coral',
    warning: 'bg-amber-50 text-amber-700',
    info: 'bg-brand-soft text-brand',
};

export default function NotificationsPage({ attentionQueue }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    return (
        <AppLayout>
            <Head title={t('Notifications')} />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to dashboard')}
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">{t('Workspace signals')}</p>
                <h1 className="page-title">{t('Notifications and attention')}</h1>
                <p className="page-subtitle">
                    {t(
                        'Current operational items needing follow-up. The list respects permissions and comes from the active workspace queue.',
                    )}
                </p>

                <div className="card mt-8 overflow-hidden">
                    {attentionQueue.length === 0 ? (
                        <div className="px-6 py-14 text-center">
                            <Bell size={26} className="mx-auto text-brand" />
                            <h2 className="mt-4 section-title">{t("You're all caught up")}</h2>
                            <p className="mt-2 text-sm text-muted">{t('No current notifications for your role.')}</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-line">
                            {attentionQueue.map((item) => (
                                <Link
                                    key={`${item.type}-${item.href}`}
                                    href={item.href}
                                    className="flex items-start gap-4 p-5 transition hover:bg-sand"
                                >
                                    <span
                                        className={`grid size-10 shrink-0 place-items-center rounded-xl ${severityStyles[item.severity]}`}
                                    >
                                        {item.severity === 'critical' ? (
                                            <CircleAlert size={18} />
                                        ) : (
                                            <TriangleAlert size={18} />
                                        )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-ink">{t(item.title)}</span>
                                        <span className="mt-1 block text-sm text-muted">{t(item.detail)}</span>
                                    </span>
                                    <span className="text-xs font-semibold text-brand">{t('Open')}</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
