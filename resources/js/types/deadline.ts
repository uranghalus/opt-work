/**
 * Proksimitas deadline Work Order — dihitung server-side
 * (WorkOrderController::deadlineState) agar rail/label tidak pernah
 * berbeda dengan data (pola Timeline, DESIGN.md).
 */
export type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';
