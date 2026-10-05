import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/** Wayfinding trail: current section only on phones, full path from sm up. */
export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    if (breadcrumbs.length === 0) {
        return null;
    }

    return (
        <Breadcrumb className="min-w-0">
            <BreadcrumbList className="flex-nowrap items-center gap-1.5 text-[0.8125rem]">
                {breadcrumbs.map((item, index) => {
                    const isLast = index === breadcrumbs.length - 1;

                    return (
                        <Fragment key={`${item.title}-${index}`}>
                            <BreadcrumbItem
                                className={cn('min-w-0', !isLast && 'hidden sm:inline-flex')}
                            >
                                {isLast ? (
                                    <BreadcrumbPage className="truncate font-semibold text-ink">
                                        {item.title}
                                    </BreadcrumbPage>
                                ) : item.href ? (
                                    <BreadcrumbLink
                                        asChild
                                        className="truncate text-ink-muted transition-colors duration-150 hover:text-ink"
                                    >
                                        <Link href={item.href}>
                                            {item.title}
                                        </Link>
                                    </BreadcrumbLink>
                                ) : (
                                    <span className="truncate text-ink-subtle">
                                        {item.title}
                                    </span>
                                )}
                            </BreadcrumbItem>
                            {!isLast && (
                                <BreadcrumbSeparator className="hidden sm:block" />
                            )}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
