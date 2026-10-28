import * as React from 'react';
import { cn } from '@/lib/utils';
import { CircleCheck, Clock, type LucideIcon } from 'lucide-react';

export type WorkflowStepStatus = 'pending' | 'active' | 'completed';

export interface WorkflowStep {
    label: string;
    status: WorkflowStepStatus;
}

interface WorkflowStepperProps {
    /** Current step index (1-based). */
    currentStep: number;
    steps: WorkflowStep[];
}

const statusIcon: Record<WorkflowStepStatus, LucideIcon> = {
    pending: Clock,
    active: Clock,
    completed: CircleCheck,
};

const statusToken: Record<WorkflowStepStatus, { bg: string; border: string; text: string }> = {
    pending: { bg: 'bg-border', border: 'border-border', text: 'text-ink-muted' },
    active: { bg: 'bg-primary', border: 'border-primary', text: 'text-primary' },
    completed: { bg: 'bg-success', border: 'border-success', text: 'text-success' },
};

/**
 * WorkflowStepper — shared presentational component showing the WO lifecycle
 * stage progress. Adapted to opti-works design tokens (stitch/ink scale).
 *
 * Pure presentational: no backend dependency. Used by SubmitResults (step 3)
 * and Verify (step 4) pages.
 */
export function WorkflowStepper({
    currentStep,
    steps,
}: WorkflowStepperProps): React.ReactElement {
    return (
        <div className="flex w-full flex-col gap-4">
            <ol
                className="flex w-full items-center justify-between relative"
                aria-label="Workflow status"
            >
                {steps.map((step, index) => {
                    const stepNumber = index + 1;
                    const isLast = index === steps.length - 1;
                    const token = statusToken[step.status];
                    const Icon = statusIcon[step.status];

                    // Connector line leading into this step (not the first).
                    // Shows completed colour when the destination step is done.
                    const lineCompleted =
                        index > 0 && steps[index - 1].status === 'completed';

                    return (
                        <li
                            key={step.label}
                            className="flex-1 flex flex-col items-center relative"
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute left-1/2 right-1/2 top-4 h-0.5 -z-10',
                                        lineCompleted
                                            ? 'bg-success'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                            <div
                                className={cn(
                                    'relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium transition-all',
                                    token.bg,
                                    token.border,
                                )}
                            >
                                {step.status === 'completed' ? (
                                    <Icon className="size-4 text-white" aria-hidden="true" />
                                ) : (
                                        <span className={cn(token.text)}>{stepNumber}</span>
                                    )}
                            </div>
                            <span
                                className={cn(
                                    'mt-1.5 text-xs font-medium text-center leading-none',
                                    token.text,
                                )}
                            >
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
            {/* Highlight the active step for screen readers / context. */}
            {steps[currentStep - 1] && (
                <p className="sr-only">
                    Current step: {currentStep} — {steps[currentStep - 1].label}
                </p>
            )}
        </div>
    );
}
