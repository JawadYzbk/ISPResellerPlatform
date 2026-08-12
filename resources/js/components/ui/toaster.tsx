import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { Toast, ToastDescription, ToastProvider, ToastTitle, ToastViewport } from '@/components/ui/toast';
import { useToast } from '@/components/ui/use-toast';
import type { PageProps } from '@/types';

export function Toaster() {
    const { toasts, toast, dismiss } = useToast();
    const { props } = usePage<PageProps>();
    const flash = props.flash;
    const lastFlashId = useRef<string | null>(null);

    useEffect(() => {
        if (!flash?.id || lastFlashId.current === flash.id) return;

        lastFlashId.current = flash.id;

        if (flash.success) {
            toast({
                title: 'Saved',
                description: flash.success,
                duration: 5000,
            });
        }

        if (flash.error) {
            toast({
                title: 'Action could not be completed',
                description: flash.error,
                variant: 'destructive',
                duration: 8000,
            });
        }
    }, [flash?.error, flash?.id, flash?.success, toast]);

    return (
        <ToastProvider swipeDirection="right">
            {toasts.map(({ id, title, description, variant, ...toastProps }) => {
                const destructive = variant === 'destructive';
                const Icon = destructive ? CircleAlert : CheckCircle2;

                return (
                    <Toast
                        key={id}
                        {...toastProps}
                        variant={variant}
                        role={destructive ? 'alert' : 'status'}
                        data-testid="flash-toast"
                        onOpenChange={(open) => {
                            if (!open) dismiss(id);
                        }}
                    >
                        <Icon
                            className={destructive ? 'mt-0.5 shrink-0 text-coral' : 'mt-0.5 shrink-0 text-brand'}
                            size={20}
                            aria-hidden="true"
                        />
                        <div className="min-w-0 space-y-1">
                            <ToastTitle>{title}</ToastTitle>
                            <ToastDescription>{description}</ToastDescription>
                        </div>
                    </Toast>
                );
            })}
            <ToastViewport />
        </ToastProvider>
    );
}
