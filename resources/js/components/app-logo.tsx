import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/**
 * Splits a CamelCase brand name ("OptiWorks") into ["Opti", "Works"] for
 * the two-tone wordmark. Falls back to a single tone when the name has
 * fewer than two capitals.
 */
function splitBrand(name: string): [string, string | null] {
    const capitals = [...name].reduce<number[]>(
        (acc, ch, i) => (/[A-Z]/.test(ch) ? [...acc, i] : acc),
        [],
    );

    if (capitals.length >= 2) {
        return [name.slice(0, capitals[1]), name.slice(capitals[1])];
    }

    return [name, null];
}

export default function AppLogo() {
    const { name } = usePage().props;
    const [head, tail] = splitBrand(name ?? '');

    return (
        <>
            <span className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-full bg-brand text-on-brand">
                <AppLogoIcon className="size-4 fill-current" />
            </span>
            <span className="ml-2 truncate text-base leading-tight font-semibold tracking-tight">
                {head}
                {tail && <span className="text-brand">{tail}</span>}
            </span>
        </>
    );
}
