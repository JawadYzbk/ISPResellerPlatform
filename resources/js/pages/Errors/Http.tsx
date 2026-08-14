import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, RefreshCw } from 'lucide-react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Props = {
    status: number;
    title: string;
    message: string;
};

export default function HttpError({ status, title, message }: Props) {
    const { props } = usePage<PageProps>();
    const auth = props.auth ?? { user: null, isPlatformOperator: false };
    const app = props.app ?? { locale: 'en', direction: 'ltr' as const };
    const t = createTranslator(app.locale);
    const destination = auth.user === null ? '/login' : auth.isPlatformOperator ? '/admin/tenants' : '/dashboard';
    const destinationLabel =
        auth.user === null
            ? t('error.go_to_sign_in')
            : auth.isPlatformOperator
              ? t('error.back_to_tenants')
              : t('Back to dashboard');

    return (
        <AuthLayout>
            <div className="space-y-8">
                <div>
                    <p className="eyebrow">
                        {t('error.title')} {status}
                    </p>
                    <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight">{t(title)}</h1>
                    <p className="mt-4 text-sm leading-6 text-muted">{t(message)}</p>
                </div>

                <div className="flex flex-wrap gap-3">
                    {status === 419 && (
                        <button type="button" className="button-primary" onClick={() => window.location.reload()}>
                            <RefreshCw size={16} />
                            {t('error.refresh')}
                        </button>
                    )}
                    <Link href={destination} className="button-primary">
                        <Home size={16} />
                        {destinationLabel}
                    </Link>
                    <button type="button" className="button-secondary" onClick={() => window.history.back()}>
                        <ArrowLeft size={16} />
                        {t('error.go_back')}
                    </button>
                </div>
            </div>
        </AuthLayout>
    );
}
