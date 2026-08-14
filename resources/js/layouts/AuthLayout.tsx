import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

import { Toaster } from '@/components/ui/toaster';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function AuthLayout({ children }: PropsWithChildren) {
    const page = usePage<PageProps>();
    const { app } = page.props;
    const t = createTranslator(page.props.app.locale);

    return (
        <div className="auth-root relative bg-canvas" dir={page.props.app.direction}>
            <Toaster />
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[110] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg"
            >
                {t('Skip to main content')}
            </a>
            <div data-testid="auth-shell" className="auth-shell grid lg:grid-cols-[1.1fr_0.9fr]">
                <section
                    data-testid="auth-brand-panel"
                    className="auth-brand-panel relative hidden overflow-hidden bg-brand p-12 text-white lg:flex lg:flex-col lg:justify-between"
                >
                    <div className="auth-ambient" aria-hidden="true">
                        <span className="auth-orbit auth-orbit-one" />
                        <span className="auth-orbit auth-orbit-two" />
                        <span className="auth-orb auth-orb-one" />
                        <span className="auth-orb auth-orb-two" />
                        <span className="auth-orb auth-orb-three" />
                        <span className="auth-signal auth-signal-one" />
                        <span className="auth-signal auth-signal-two" />
                    </div>
                    <div className="relative z-10 flex items-center gap-3">
                        <img src="/brand/nexa-isp.svg" alt="" className="size-10 rounded-xl" />
                        <span className="font-display text-lg font-bold">{app.name}</span>
                    </div>
                    <div className="relative z-10 max-w-lg">
                        <p className="mb-6 text-sm font-semibold uppercase tracking-[0.2em] text-white/60">
                            {t('The operations spine for local ISPs')}
                        </p>
                        <p className="font-display text-5xl font-semibold leading-[1.08] tracking-tight">
                            {t('Know what’s happening. Keep customers connected.')}
                        </p>
                        <p className="mt-6 max-w-md text-base leading-7 text-white/70">
                            {t(
                                'One desk for subscribers, cash collection, field work and the network actions that keep your business moving.',
                            )}
                        </p>
                    </div>
                    <p className="relative z-10 text-sm text-white/50">
                        {t('Built for operators who do more with less.')}
                    </p>
                </section>
                <main
                    data-testid="auth-form-panel"
                    id="main-content"
                    tabIndex={-1}
                    className="auth-form-panel flex items-center justify-center px-5 py-12 sm:px-10"
                >
                    <div className="w-full max-w-sm">{children}</div>
                </main>
            </div>
        </div>
    );
}
