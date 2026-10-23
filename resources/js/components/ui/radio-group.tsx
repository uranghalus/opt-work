import * as React from 'react';
import { cn } from '@/lib/utils';

const RadioGroupContext = React.createContext<{
    value: string;
    onValueChange: (value: string) => void;
    name: string;
} | null>(null);

function useRadioGroupContext() {
    const context = React.useContext(RadioGroupContext);
    if (!context) {
        throw new Error('RadioGroup components must be used within RadioGroup');
    }
    return context;
}

interface RadioGroupProps {
    value: string;
    onValueChange: (value: string) => void;
    name: string;
    children: React.ReactNode;
    className?: string;
}

export function RadioGroup({
    value,
    onValueChange,
    name,
    children,
    className,
}: RadioGroupProps) {
    return (
        <RadioGroupContext.Provider value={{ value, onValueChange, name }}>
            <div className={cn('grid gap-3', className)} role="radiogroup">
                {children}
            </div>
        </RadioGroupContext.Provider>
    );
}

interface RadioGroupItemProps {
    value: string;
    className?: string;
    children?: React.ReactNode;
    disabled?: boolean;
}

export function RadioGroupItem({
    value,
    className,
    children,
    disabled = false,
}: RadioGroupItemProps) {
    const context = useRadioGroupContext();
    const checked = context.value === value;

    return (
        <label
            className={cn(
                'relative flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 transition-[border-color,box-shadow] hover:border-brand hover:shadow-raised data-[state=checked]:border-brand data-[state=checked]:bg-brand-subtle data-[state=checked]:shadow-raised focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
                checked && 'border-brand bg-brand-subtle shadow-raised',
                className,
            )}
        >
            <input
                type="radio"
                name={context.name}
                value={value}
                checked={checked}
                onChange={() => context.onValueChange(value)}
                disabled={disabled}
                className="sr-only"
            />
            {children}
        </label>
    );
}