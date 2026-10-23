"use client";

import * as React from "react";
import { cn } from "@/lib/utils";

interface TabsProps extends React.ComponentPropsWithoutRef<"div"> {
    value: string;
    onValueChange: (value: string) => void;
    defaultValue?: string;
    orientation?: "horizontal" | "vertical";
}

const Tabs = React.forwardRef<HTMLDivElement, TabsProps>(
    ({ className, value, onValueChange, children, orientation = "horizontal", ...props }, ref) => {
        return (
            <div
                ref={ref}
                data-orientation={orientation}
                className={cn("flex", orientation === "vertical" ? "flex-col" : "flex-row", className)}
                {...props}
            >
                {children}
            </div>
        );
    }
);
Tabs.displayName = "Tabs";

interface TabsListProps extends React.ComponentPropsWithoutRef<"div"> {
    "aria-label"?: string;
}

const TabsList = React.forwardRef<HTMLDivElement, TabsListProps>(
    ({ className, children, ...props }, ref) => {
        return (
            <div
                ref={ref}
                role="tablist"
                aria-label={props["aria-label"]}
                className={cn(
                    "inline-flex h-10 items-center justify-center rounded-md bg-muted p-1 text-muted-foreground",
                    className
                )}
                {...props}
            >
                {children}
            </div>
        );
    }
);
TabsList.displayName = "TabsList";

interface TabsTriggerProps extends React.ComponentPropsWithoutRef<"button"> {
    value: string;
    disabled?: boolean;
}

const TabsTrigger = React.forwardRef<HTMLButtonElement, TabsTriggerProps>(
    ({ className, value, disabled, children, ...props }, ref) => {
        // Get value from context - we need to access parent Tabs value
        // For now, use a simple approach
        return (
            <button
                ref={ref}
                role="tab"
                aria-selected={false} // Will be controlled by parent
                aria-disabled={disabled}
                data-state="inactive"
                data-disabled={disabled ? "" : undefined}
                className={cn(
                    "inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
                    className
                )}
                {...props}
            >
                {children}
            </button>
        );
    }
);
TabsTrigger.displayName = "TabsTrigger";

interface TabsContentProps extends React.ComponentPropsWithoutRef<"div"> {
    value: string;
    forceMount?: boolean;
}

const TabsContent = React.forwardRef<HTMLDivElement, TabsContentProps>(
    ({ className, value, forceMount, children, ...props }, ref) => {
        return (
            <div
                ref={ref}
                role="tabpanel"
                data-state="inactive"
                data-orientation="horizontal"
                className={cn(
                    "mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                    className
                )}
                {...props}
            >
                {forceMount || false ? children : null}
            </div>
        );
    }
);
TabsContent.displayName = "TabsContent";

export { Tabs, TabsList, TabsTrigger, TabsContent };