import * as React from 'react';

import { useMediaQuery } from '@/hooks/useMediaQuery';
import { cn } from '@/lib/utils';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './select';

const MOBILE_QUERY = '(max-width: 767px)';
const EMPTY_VALUE = '__responsive_select_empty__';

type ResponsiveSelectProps = Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'onChange' | 'value'> & {
    value?: string | number | readonly string[];
    onChange?: (event: React.ChangeEvent<HTMLSelectElement>) => void;
};

type OptionElement = React.ReactElement<React.OptionHTMLAttributes<HTMLOptionElement>>;

function isOptionElement(child: React.ReactNode): child is OptionElement {
    return React.isValidElement(child) && child.type === 'option';
}

function optionValue(option: OptionElement): string {
    return String(option.props.value ?? '');
}

function optionChildren(option: OptionElement | undefined): React.ReactNode {
    return option ? (option.props.children ?? optionValue(option)) : null;
}

export default function ResponsiveSelect({
    children,
    className,
    defaultValue,
    disabled,
    id,
    name,
    onChange,
    required,
    value,
    ...props
}: ResponsiveSelectProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const options = React.Children.toArray(children).filter(isOptionElement);
    const rawValue = String(value ?? defaultValue ?? '');
    const selectedOption = options.find((option) => optionValue(option) === rawValue);
    const selectValue = selectedOption ? rawValue : EMPTY_VALUE;
    const placeholder = optionChildren(options.find((option) => optionValue(option) === '') ?? options[0]);

    const handleValueChange = (nextValue: string) => {
        const nextRawValue = nextValue === EMPTY_VALUE ? '' : nextValue;

        onChange?.({
            target: { name: name ?? '', value: nextRawValue },
            currentTarget: { name: name ?? '', value: nextRawValue },
        } as React.ChangeEvent<HTMLSelectElement>);
    };

    if (isMobile) {
        return (
            <select
                {...props}
                className={className}
                defaultValue={defaultValue}
                disabled={disabled}
                id={id}
                name={name}
                onChange={onChange}
                required={required}
                value={value}
            >
                {children}
            </select>
        );
    }

    return (
        <>
            <Select disabled={disabled} value={selectValue} onValueChange={handleValueChange}>
                <SelectTrigger
                    id={id}
                    aria-label={props['aria-label']}
                    aria-describedby={props['aria-describedby']}
                    className={cn('w-full', className)}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option, index) => {
                        const rawOptionValue = optionValue(option);

                        return (
                            <SelectItem
                                key={option.key ?? `${rawOptionValue}-${index}`}
                                value={rawOptionValue || EMPTY_VALUE}
                                disabled={option.props.disabled}
                            >
                                {optionChildren(option)}
                            </SelectItem>
                        );
                    })}
                </SelectContent>
            </Select>
            {name && (
                <input
                    type="hidden"
                    name={name}
                    value={selectedOption ? rawValue : ''}
                    disabled={disabled}
                    required={required}
                />
            )}
        </>
    );
}
