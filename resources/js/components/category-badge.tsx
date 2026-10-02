import { cn } from '@/lib/utils';
import { ClipboardList, Megaphone, Siren, type LucideIcon } from 'lucide-react';

/**
 * Work Order category (PRD FR-1.1). Label is always visible —
 * tint alone never carries the meaning (DESIGN_BRIEF P1).
 */
export type WoCategory = 'normal' | 'accident' | 'owner';

const categorySpec: Record<
    WoCategory,
    { label: string; icon: LucideIcon; className: string }
> = {
    normal: {
        label: 'Normal',
        icon: ClipboardList,
        className: 'bg-info/10 text-info border-info/30',
    },
    accident: {
        label: 'Urgent · Kecelakaan',
        icon: Siren,
        className: 'bg-danger/10 text-danger border-danger/30',
    },
    owner: {
        label: 'Urgent · Owner',
        icon: Megaphone,
        className: 'bg-owner-urgent/10 text-owner-urgent border-owner-urgent/30',
    },
};

export function CategoryBadge({
    category,
    className,
}: {
    category: WoCategory;
    className?: string;
}) {
    const { label, icon: Icon, className: tone } = categorySpec[category];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                tone,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5" />
            {label}
        </span>
    );
}
