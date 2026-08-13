import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { Toast, ToastClose, ToastDescription, ToastProvider, ToastTitle, ToastViewport } from '@/components/ui/toast';
import { toast as notify, useToast } from '@/components/ui/use-toast';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

const firstErrorMessage = (errors: Record<string, unknown>) =>
    Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .find((value): value is string => typeof value === 'string' && value.trim().length > 0);

const inferredSuccessTitle = (message: string): string => {
    const normalized = message.toLocaleLowerCase();
    const titleRules: Array<[RegExp, string]> = [
        [/\b(deleted|removed|revoked)\b/, 'Deleted'],
        [/\b(archived)\b/, 'Archived'],
        [/\b(updated|changed)\b/, 'Updated'],
        [/\b(created|added|registered)\b/, 'Created'],
        [/\b(recorded|received|captured)\b/, 'Recorded'],
        [/\b(imported)\b/, 'Imported'],
        [/\b(reversed)\b/, 'Reversed'],
        [/\b(scheduled)\b/, 'Scheduled'],
        [/\b(assigned)\b/, 'Assigned'],
        [/\b(disconnected)\b/, 'Disconnected'],
        [/\b(queued)\b/, 'Queued'],
        [/\b(completed|closed|reconciled)\b/, 'Completed'],
        [/\b(saved)\b/, 'Saved'],
    ];

    return titleRules.find(([pattern]) => pattern.test(normalized))?.[1] ?? 'Completed';
};

if (typeof window !== 'undefined') {
    const translateCurrent = (key: string) => createTranslator(document.documentElement.lang?.slice(0, 2) ?? 'en')(key);

    router.on('error', (event) => {
        const message = firstErrorMessage(event.detail.errors as Record<string, unknown>);

        if (message) {
            notify({
                title: translateCurrent('Please check the form'),
                description: translateCurrent(message),
                variant: 'destructive',
                duration: 8000,
            });
        }
    });

    router.on('httpException', (event) => {
        const status = event.detail.response.status;
        const messages: Record<number, string> = {
            401: 'Your session has expired. Sign in again to continue.',
            403: 'You do not have permission to perform this action.',
            404: 'The requested record could not be found.',
            419: 'Your session expired. Refresh the page and try again.',
        };
        const message = messages[status] ?? 'The server could not complete this action. Try again.';

        notify({
            title: translateCurrent('Request failed'),
            description: translateCurrent(message),
            variant: 'destructive',
            duration: 8000,
        });
    });

    router.on('networkError', (event) => {
        notify({
            title: translateCurrent('Connection problem'),
            description: translateCurrent(event.detail.error.message || 'Unable to reach the server. Try again.'),
            variant: 'destructive',
            duration: 8000,
        });
    });
}

export function Toaster() {
    const { toasts, toast, dismiss } = useToast();
    const { props } = usePage<PageProps>();
    const flash = props.flash;
    const t = createTranslator(props.app.locale);
    const lastFlashId = useRef<string | null>(null);

    useEffect(() => {
        if (!flash?.id || lastFlashId.current === flash.id) return;

        lastFlashId.current = flash.id;

        if (flash.success) {
            toast({
                title: t(flash.successTitle ?? inferredSuccessTitle(flash.success)),
                description: t(flash.success),
                duration: 5000,
            });
        }

        if (flash.error) {
            toast({
                title: t('Action could not be completed'),
                description: t(flash.error),
                variant: 'destructive',
                duration: 8000,
            });
        }
    }, [flash?.error, flash?.id, flash?.success, flash?.successTitle, t, toast]);

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
                        <div className="min-w-0 flex-1 space-y-1">
                            <ToastTitle>{title}</ToastTitle>
                            <ToastDescription>{description}</ToastDescription>
                        </div>
                        <ToastClose />
                    </Toast>
                );
            })}
            <ToastViewport />
        </ToastProvider>
    );
}
