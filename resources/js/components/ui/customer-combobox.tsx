import { Check, ChevronsUpDown, LoaderCircle } from 'lucide-react';
import { useMemo, useState } from 'react';

import { useMediaQuery } from '@/hooks/useMediaQuery';
import { formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';

import type { PageProps } from '@/types';

import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from './command';
import { Popover, PopoverContent, PopoverTrigger } from './popover';

export type CustomerOption = {
    id: string;
    code: string;
    name: string;
    phone: string | null;
    status: string;
    balance_amount: number;
    balance_currency: string;
};

type CustomerComboboxProps = {
    id?: string;
    value: string;
    customers: CustomerOption[];
    onChange: (value: string) => void;
    onSearch?: (query: string) => void;
    'aria-label'?: string;
    className?: string;
    disabled?: boolean;
    placeholder?: string;
    searchStatus?: 'idle' | 'loading' | 'error';
};

export default function CustomerCombobox({
    id,
    value,
    customers,
    onChange,
    onSearch,
    'aria-label': ariaLabel,
    className,
    disabled = false,
    placeholder,
    searchStatus = 'idle',
}: CustomerComboboxProps) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const isMobile = useMediaQuery('(max-width: 767px)');
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const orderedCustomers = useMemo(
        () =>
            [...customers].sort((left, right) =>
                `${left.name} ${left.code}`.localeCompare(`${right.name} ${right.code}`),
            ),
        [customers],
    );
    const selected = orderedCustomers.find((customer) => customer.id === value);
    const normalizedQuery = query.trim().toLowerCase();
    const filtered = normalizedQuery
        ? orderedCustomers.filter((customer) =>
              `${customer.name} ${customer.code} ${customer.phone ?? ''}`.toLowerCase().includes(normalizedQuery),
          )
        : orderedCustomers;

    const selectCustomer = (customerId: string) => {
        onChange(customerId);
        setOpen(false);
        setQuery('');
    };

    if (isMobile) {
        return (
            <select
                id={id}
                aria-label={ariaLabel}
                className={className}
                disabled={disabled}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">{placeholder ?? t('Select a customer')}</option>
                {orderedCustomers.map((customer) => (
                    <option key={customer.id} value={customer.id}>
                        {customer.name} · {customer.code}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <Popover
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);
                if (!nextOpen) setQuery('');
            }}
        >
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-label={ariaLabel}
                    aria-expanded={open}
                    aria-busy={searchStatus === 'loading'}
                    disabled={disabled}
                    className={cn('field flex items-center justify-between gap-2 text-start', className)}
                >
                    <span className="min-w-0 truncate">
                        {selected ? `${selected.name} · ${selected.code}` : (placeholder ?? t('Select a customer'))}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-60" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        value={query}
                        onValueChange={(nextQuery) => {
                            setQuery(nextQuery);
                            onSearch?.(nextQuery);
                        }}
                        placeholder={t('Search customers')}
                        aria-label={t('Search customers')}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {searchStatus === 'loading' ? t('Searching customers…') : t('No matching customers.')}
                        </CommandEmpty>
                        <CommandGroup>
                            {filtered.map((customer) => (
                                <CommandItem key={customer.id} value={customer.id} onSelect={selectCustomer}>
                                    <Check
                                        className={cn(
                                            'me-2 size-4',
                                            value === customer.id ? 'opacity-100' : 'opacity-0',
                                        )}
                                    />
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">{customer.name}</span>
                                        <span className="block truncate text-xs text-muted">
                                            {customer.code}
                                            {customer.phone ? ` · ${customer.phone}` : ''}
                                        </span>
                                        <span className="block truncate text-xs text-muted">
                                            {t(customer.status)} ·{' '}
                                            {formatMoney(customer.balance_amount, customer.balance_currency)}
                                        </span>
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                    {searchStatus === 'loading' && (
                        <p
                            className="flex items-center gap-2 border-t border-line px-3 py-2 text-xs text-muted"
                            role="status"
                        >
                            <LoaderCircle className="size-3.5 animate-spin" />
                            {t('Searching customers…')}
                        </p>
                    )}
                    {searchStatus === 'error' && (
                        <p className="border-t border-line px-3 py-2 text-xs text-danger" role="alert">
                            {t('Customer search is unavailable. Showing available customers.')}
                        </p>
                    )}
                </Command>
            </PopoverContent>
        </Popover>
    );
}
