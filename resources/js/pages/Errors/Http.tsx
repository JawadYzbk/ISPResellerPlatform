import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';

import AuthLayout from '@/layouts/AuthLayout';
import type { PageProps } from '@/types';

type Props = {
    status: number;
    title: string;
    message: string;
};

export default function HttpError({ status, title, message }: Props) {
    const { auth } = usePage<PageProps>().props;
    const destination = auth.user === null ? '/login' : auth.isPlatformOperator ? '/admin/tenants' : '/dashboard';
    const destinationLabel =
        auth.user === null ? 'Go to sign in' : auth.isPlatformOperator ? 'Back to tenants' : 'Back to dashboard';

    return (
        <AuthLayout>
            <div className="space-y-8">
                <div>
                    <p className="eyebrow">Error {status}</p>
                    <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight">{title}</h1>
                    <p className="mt-4 text-sm leading-6 text-muted">{message}</p>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Link href={destination} className="button-primary">
                        <Home size={16} />
                        {destinationLabel}
                    </Link>
                    <button type="button" className="button-secondary" onClick={() => window.history.back()}>
                        <ArrowLeft size={16} />
                        Go back
                    </button>
                </div>
            </div>
        </AuthLayout>
    );
}
