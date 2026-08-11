import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';

import { useMediaQuery } from '@/hooks/useMediaQuery';
import { cn } from '@/lib/utils';

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
};

export default function CurrencyCombobox({
    id,
    value,
    currencies,
    onChange,
    'aria-label': ariaLabel,
    className,
    disabled = false,
}: CurrencyComboboxProps) {
    const isMobile = useMediaQuery('(max-width: 767px)');
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = currencies.find((currency) => currency.code === value);
    const filtered = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        if (normalized === '') return currencies;

        return currencies.filter((currency) => `${currency.code} ${currency.name}`.toLowerCase().includes(normalized));
    }, [currencies, query]);

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
                {currencies.map((currency) => (
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
                        {selected ? `${selected.code} — ${selected.name}` : 'Select a currency'}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-60" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        value={query}
                        onValueChange={setQuery}
                        placeholder="Search code or currency"
                        aria-label="Search currencies"
                    />
                    <CommandList>
                        <CommandEmpty>No matching currencies.</CommandEmpty>
                        <CommandGroup>
                            {filtered.map((currency) => (
                                <CommandItem
                                    key={currency.code}
                                    value={currency.code}
                                    onPointerDown={(event) => {
                                        if (event.pointerType === 'mouse') {
                                            event.preventDefault();
                                            selectCurrency(currency.code);
                                        }
                                    }}
                                    onSelect={() => {
                                        selectCurrency(currency.code);
                                    }}
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
