import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { CircleAlert, Zap, UserRoundCheck } from 'lucide-react';

interface CategoryOption {
    value: string;
    label: string;
}

interface CategoryChoiceProps {
    value: string;
    onChange: (value: string) => void;
    categories: CategoryOption[];
    error?: string;
}

/** RadioCard category picker with consequence hint per option. */
export function WorkOrderCategoryChoice({
    value,
    onChange,
    categories,
    error,
}: CategoryChoiceProps) {
    const icons: Record<string, React.ComponentType<{ className?: string }>> = {
        accident: CircleAlert,
        normal: Zap,
        owner: UserRoundCheck,
    };

    const hints: Record<string, string> = {
        accident: 'HOD wajib eksekusi langsung — tidak bisa dijadwalkan.',
        normal: 'HOD boleh langsung atau menjadwalkan.',
        owner: 'Permintaan urgent atas nama owner.',
    };

    return (
        <div className="space-y-2">
            <Label className="block text-sm font-medium">Kategori</Label>

            <RadioGroup value={value} onValueChange={onChange} name="category" className="grid gap-3">
                {categories.map((option) => {
                    const Icon = icons[option.value] || Zap;
                    return (
                        <RadioGroupItem
                            key={option.value}
                            value={option.value}
                            className="relative flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 transition-[border-color,box-shadow] hover:border-brand hover:shadow-raised data-[state=checked]:border-brand data-[state=checked]:bg-brand-subtle data-[state=checked]:shadow-raised focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2"
                        >
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-raised text-brand">
                                <Icon className="h-5 w-5" aria-hidden />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-medium text-ink">{option.label}</p>
                                <p className="text-sm text-ink-muted mt-0.5">
                                    {hints[option.value] ?? ''}
                                </p>
                            </div>
                        </RadioGroupItem>
                    );
                })}
            </RadioGroup>

            {error && <p className="text-xs text-danger mt-1">{error}</p>}
        </div>
    );
}