import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
    variant?: AppVariant;
};

export function AppContent({ variant = 'sidebar', children, ...props }: Props) {
    if (variant === 'header') {
        return (
            <main
                className="mx-auto flex h-full w-full max-w-[1280px] flex-1 flex-col gap-4 px-4 pt-10 pb-24 md:px-6 md:pb-12"
                {...props}
            >
                {children}
            </main>
        );
    }

    return <SidebarInset {...props}>{children}</SidebarInset>;
}
