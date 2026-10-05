import type { ComponentType, ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

/** A page's primary action(s): a node, or a component that reads page props. */
export type AppActions = ReactNode | ComponentType;

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /** Section heading rendered by the shell. Omitted → no heading band. */
    title?: string;
    /** One line under the title (max ~65ch). */
    description?: string;
    /** Renders the time-aware greeting instead of `title`. */
    greeting?: boolean;
    /** Top-right actions of the section heading band. */
    actions?: AppActions;
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
