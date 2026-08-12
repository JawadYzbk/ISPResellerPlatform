import { Activity } from 'lucide-react';
import type { PropsWithChildren } from 'react';

import { Toaster } from '@/components/ui/toaster';

export default function AuthLayout({ children }: PropsWithChildren) {
    return (
        <div className="relative min-h-screen bg-canvas">
            <Toaster />
            <div data-testid="auth-shell" className="grid min-h-screen lg:grid-cols-[1.1fr_0.9fr]">
                <section
                    data-testid="auth-brand-panel"
                    className="relative hidden min-h-screen overflow-hidden bg-brand p-12 text-white lg:flex lg:flex-col lg:justify-between"
                >
                    <div className="auth-ambient" aria-hidden="true">
                        <span className="auth-orbit auth-orbit-one" />
                        <span className="auth-orbit auth-orbit-two" />
                        <span className="auth-signal auth-signal-one" />
                        <span className="auth-signal auth-signal-two" />
                    </div>
                    <div className="relative z-10 flex items-center gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-white/15">
                            <Activity size={21} />
                        </div>
                        <span className="font-display text-lg font-bold">ISP Manager</span>
                    </div>
                    <div className="relative z-10 max-w-lg">
                        <p className="mb-6 text-sm font-semibold uppercase tracking-[0.2em] text-white/60">
                            The operations spine for local ISPs
                        </p>
                        <h1 className="font-display text-5xl font-semibold leading-[1.08] tracking-tight">
                            Know what’s happening. Keep customers connected.
                        </h1>
                        <p className="mt-6 max-w-md text-base leading-7 text-white/70">
                            One desk for subscribers, cash collection, field work and the network actions that keep your
                            business moving.
                        </p>
                    </div>
                    <p className="relative z-10 text-sm text-white/50">Built for operators who do more with less.</p>
                </section>
                <div data-testid="auth-form-panel" className="flex min-h-screen items-center justify-center px-5 py-12 sm:px-10">
                    <div className="w-full max-w-sm">{children}</div>
                </div>
            </div>
        </div>
    );
}
