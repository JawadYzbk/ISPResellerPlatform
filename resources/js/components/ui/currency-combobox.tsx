import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';

import { useMediaQuery } from '@/hooks/useMediaQuery';
import { createTranslator } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';

import type { PageProps } from '@/types';

import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from './command';
import { Popover, PopoverContent, PopoverTrigger } from './popover';

export type CurrencyOption = {
    code: string;
    name: string;
    decimal_digits: number;
};

type CurrencyComboboxProps = {
    id?: string;
    value: string;
    currencies: CurrencyOption[];
    onChange: (value: string) => void;
    'aria-label'?: string;
    className?: string;
    disabled?: boolean;
    emptyLabel?: string;
};

const PRIORITY_CODES = ['USD', 'LBP', 'EUR', 'AED'];

export default function CurrencyCombobox({
    id,
    value,
    currencies,
    onChange,
    'aria-label': ariaLabel,
    className,
    disabled = false,
    emptyLabel,
}: CurrencyComboboxProps) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const isMobile = useMediaQuery('(max-width: 767px)');
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const orderedCurrencies = useMemo(
        () =>
            [...currencies].sort((left, right) => {
                const leftPriority = PRIORITY_CODES.indexOf(left.code.toUpperCase());
                const rightPriority = PRIORITY_CODES.indexOf(right.code.toUpperCase());

                if (leftPriority !== -1 || rightPriority !== -1) {
                    return (
                        (leftPriority === -1 ? Number.MAX_SAFE_INTEGER : leftPriority) -
                        (rightPriority === -1 ? Number.MAX_SAFE_INTEGER : rightPriority)
                    );
                }

                return left.code.localeCompare(right.code);
            }),
        [currencies],
    );
    const selected = orderedCurrencies.find((currency) => currency.code === value);
    const filtered = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        if (normalized === '') return orderedCurrencies;

        return orderedCurrencies.filter((currency) =>
            `${currency.code} ${currency.name}`.toLowerCase().includes(normalized),
        );
    }, [orderedCurrencies, query]);

    const selectCurrency = (code: string) => {
        onChange(code);
        setOpen(false);
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
                {emptyLabel && <option value="">{emptyLabel}</option>}
                {orderedCurrencies.map((currency) => (
                    <option key={currency.code} value={currency.code}>
                        {currency.code} — {currency.name}
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
                    disabled={disabled}
                    className={cn('field flex items-center justify-between gap-2 text-start', className)}
                >
                    <span className="min-w-0 truncate">
                        {selected ? `${selected.code} — ${selected.name}` : (emptyLabel ?? t('Select a currency'))}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-60" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        value={query}
                        onValueChange={setQuery}
                        placeholder={t('Search code or currency')}
                        aria-label={t('Search currencies')}
                    />
                    <CommandList>
                        <CommandEmpty>{t('No matching currencies.')}</CommandEmpty>
                        <CommandGroup>
                            {emptyLabel && (
                                <CommandItem value="__empty__" onSelect={() => selectCurrency('')}>
                                    <Check className={cn('me-2 size-4', value === '' ? 'opacity-100' : 'opacity-0')} />
                                    {emptyLabel}
                                </CommandItem>
                            )}
                            {filtered.map((currency) => (
                                <CommandItem
                                    key={currency.code}
                                    value={currency.code}
                                    onSelect={() => selectCurrency(currency.code)}
                                >
                                    <Check
                                        className={cn(
                                            'me-2 size-4',
                                            value === currency.code ? 'opacity-100' : 'opacity-0',
                                        )}
                                    />
                                    <span className="font-semibold">{currency.code}</span>
                                    <span className="ms-2 truncate text-muted">{currency.name}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
