import * as ToastPrimitive from '@radix-ui/react-toast';
import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import * as React from 'react';

import { createTranslator } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

const ToastProvider = ToastPrimitive.Provider;

const ToastViewport = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Viewport>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Viewport>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Viewport
        ref={ref}
        className={cn(
            'fixed inset-x-0 top-0 z-[100] flex max-h-screen flex-col gap-3 p-4 sm:inset-x-auto sm:end-0 sm:bottom-0 sm:top-auto sm:w-full sm:max-w-[420px]',
            className,
        )}
        {...props}
    />
));
ToastViewport.displayName = ToastPrimitive.Viewport.displayName;

type ToastVariant = 'default' | 'destructive';

const Toast = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Root> & { variant?: ToastVariant }
>(({ className, variant = 'default', ...props }, ref) => (
    <ToastPrimitive.Root
        ref={ref}
        className={cn(
            'group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-2xl border bg-white p-4 pe-11 text-ink shadow-lg transition-all data-[state=closed]:animate-out data-[state=closed]:fade-out data-[state=closed]:slide-out-to-right-full data-[state=open]:animate-in data-[state=open]:fade-in data-[state=open]:slide-in-from-top-full sm:data-[state=open]:slide-in-from-bottom-full',
            variant === 'destructive' ? 'border-coral/35 bg-[#fff8f7]' : 'border-line',
            className,
        )}
        {...props}
    />
));
Toast.displayName = ToastPrimitive.Root.displayName;

const ToastTitle = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Title>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Title>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Title ref={ref} className={cn('break-words text-sm font-bold leading-5', className)} {...props} />
));
ToastTitle.displayName = ToastPrimitive.Title.displayName;

const ToastDescription = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Description>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Description>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Description
        ref={ref}
        className={cn('break-words text-sm leading-5 text-muted', className)}
        {...props}
    />
));
ToastDescription.displayName = ToastPrimitive.Description.displayName;

const ToastClose = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Close>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Close>
>(({ className, ...props }, ref) => {
    const { props: pageProps } = usePage<PageProps>();
    const t = createTranslator(pageProps.app.locale);

    return (
        <ToastPrimitive.Close
            ref={ref}
            className={cn(
                'absolute end-3 top-3 rounded-md p-1 text-muted opacity-0 transition-opacity hover:bg-sand hover:text-ink focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-brand/30 group-hover:opacity-100',
                className,
            )}
            toast-close=""
            {...props}
        >
            <X size={16} aria-hidden="true" />
            <span className="sr-only">{t('Dismiss notification')}</span>
        </ToastPrimitive.Close>
    );
});
ToastClose.displayName = ToastPrimitive.Close.displayName;

export { Toast, ToastClose, ToastDescription, ToastProvider, ToastTitle, ToastViewport };
