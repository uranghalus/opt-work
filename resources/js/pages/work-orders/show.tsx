import { Head, usePage, useForm, router } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import React, { useState } from 'react';
import {
    Ban,
    CalendarClock,
    CalendarRange,
    CircleCheck,
    ClipboardCheck,
    Eye,
    History,
    Hourglass,
    RotateCcw,
    Send,
    ShieldCheck,
    TriangleAlert,
    UserCheck,
} from 'lucide-react';
import { show as showAttachment } from '@/routes/work-orders/attachments';
import { assignSubmit, reject, review, submit, verifySubmit } from '@/routes/work-orders';
import {
    approve as approveRequestAction,
    cancel as cancelRequestAction,
    reject as rejectRequestAction,
    resubmit as resubmitRequestAction,
    update as updateRequestAction,
} from '@/routes/work-orders/request';
import workTime from '@/routes/work-orders/work-time';
import { LiveTimer } from '@/components/live-timer';
import {
    approve as approveScheduleExtend,
    reject as rejectScheduleExtend,
    update as updatePlanningDetails,
} from '@/routes/work-planning';
import {
    approve as approveExtendRequest,
    reject as rejectExtendRequest,
    store as storeExtendRequest,
} from '@/routes/work-orders/extend-requests';
import { CategoryBadge } from '@/components/category-badge';
import {
    StatusBadge,
    statusLabel,
    type WoStatus,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useRequiredTenant } from '@/lib/tenant';

type DeadlineState = 'on_track' | 'due_soon' | 'overdue' | 'escalated';

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    status: WoStatus;
    requested_schedule_date: string | null;
    location: string | null;
    target_department_id: string;
    hod_action: 'execute_immediately' | 'schedule' | null;
    scheduled_date: string | null;
    assigned_at: string | null;
    hod_notes: string | null;
    assigned_employees: string[] | null;
    assignment_notes: string | null;
    deadline_date: string | null;
    deadline_state: DeadlineState;
    deadline_label: string;
    extend_count: number;
    extend_reason: string | null;
    extended_at: string | null;
    request_rejection_reason: string | null;
    attachments: string[] | null;
    result_notes: string | null;
    result_attachments: string[] | null;
    requester: { name: string } | null;
    target_department: { nama_department: string | null } | null;
    created_at: string;
};

type EmployeeOption = { id: string; nama_employee: string };

/** One row of the WO audit trail (FR-1.9), newest first. */
type WoEvent = {
    id: string;
    action: string;
    from_status: string | null;
    to_status: string | null;
    meta: Record<string, unknown> | null;
    user_name: string | null;
    created_at: string;
};

/** One extend-deadline request row (FR-2.3), newest first. */
type ExtendRequestView = {
    id: string;
    days: number;
    reason: string;
    status: 'pending_team_leader' | 'pending_hod' | 'approved' | 'rejected';
    requester_name: string | null;
    created_at: string;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    workOrder: WorkOrder;
    employees: EmployeeOption[];
    assignees: EmployeeOption[];
    events: WoEvent[];
    extend_requests: ExtendRequestView[];
    /** Server-side mirror: boleh mengajukan extend (jendela telat/mendekati). */
    can_request_extend: boolean;
    /** Server-side mirror: user berada pada rantai tahap pengajuan pending. */
    can_decide_extend: boolean;
    /** FR-2.4: jadwal detail per WO Terjadwal (null untuk WO lain). */
    planning: PlanningView | null;
    /** Server-side mirror: HOD boleh memperbarui detail jadwal sekarang. */
    can_update_planning: boolean;
    /** Server-side mirror: user adalah DGM/GM untuk memutuskan extend jadwal. */
    can_decide_schedule: boolean;
    /** ADR-0011: gerbang approval — mirror server-side (service yang memaksa). */
    can_decide_request: boolean;
    /** R12: requester/approver dept pengaju boleh membatalkan sebelum rilis. */
    can_cancel_request: boolean;
    /** R12: requester boleh merevisi WO yang ditolak dept pengaju. */
    can_edit_request: boolean;
    /** R12: requester boleh mengajukan ulang WO yang ditolak dept pengaju. */
    can_resubmit_request: boolean;
    /** OQ-4b: reviewer dept tujuan boleh membatalkan DUPLIKAT saat review. */
    can_cancel_duplicate: boolean;
    /** R19: user saat ini adalah pemohon WO ini. */
    is_requester: boolean;
    /** OQ-4b: kandidat duplikat (id + nomor) untuk banner/dialog. */
    duplicate_candidates: { id: string; nomor_wo: string }[];
    /** R12: pilihan department aktif untuk dialog revisi. */
    departments: { id: string; nama_department: string | null }[];
    /** ADR-0011 R15–R17: user saat ini adalah pelaksana WO ini. */
    is_assignee: boolean;
    /** Timer milik pelaksana (server-computed), null bila belum ada. */
    my_time_log: {
        state: 'not_started' | 'accepted' | 'running' | 'paused' | 'finished';
        elapsed_seconds: number;
        accepted_at: string | null;
        started_at: string | null;
        finished_at: string | null;
    } | null;
    can_accept_time: boolean;
    can_start_time: boolean;
    can_pause_time: boolean;
    can_resume_time: boolean;
};

/** FR-2.4: Work Planning — jadwal detail + status extend schedule. */
type PlanningView = {
    id: string;
    tgl_jadwal: string | null;
    original_tgl_jadwal: string | null;
    proposed_tgl_jadwal: string | null;
    jam_mulai: string | null;
    jam_selesai: string | null;
    jenis_pekerjaan: string | null;
    lama_pekerjaan_hari: number | null;
    budget: number | null;
    status_jadwal: 'planned' | 'pending_extend_approval' | 'rescheduled';
    catatan: string | null;
    extend_count: number;
    extend_reason: string | null;
    requester_name: string | null;
    pending: boolean;
};

/** Statuses where the HOD review decision is still open. */
const REVIEWABLE: WoStatus[] = ['waiting_hod', 'hod_approved', 'scheduled'];

/** Statuses where personnel can be (re-)assigned. */
const ASSIGNABLE: WoStatus[] = ['hod_approved', 'scheduled', 'assigned'];

/** Statuses where the pelaksana may submit execution results (FR-1.7).
 *  in_progress masuk lewat timer durasi kerja (ADR-0011, R17). */
const SUBMITTABLE: WoStatus[] = ['assigned', 'in_progress', 'revision'];

/** Statuses where the HOD may verify the submitted result (FR-1.8). */
const VERIFYABLE: WoStatus[] = ['pending_verify'];

/** Human wording per recorded WorkOrderEvent action (FR-1.9). */
const actionLabel: Record<string, string> = {
    created: 'Work Order dibuat',
    request_approved: 'Persetujuan department pengaju — WO dirilis',
    request_rejected: 'Ditolak department pengaju',
    request_updated: 'Revisi permintaan oleh pemohon',
    resubmitted: 'Diajukan ulang oleh pemohon',
    cancelled: 'Work Order dibatalkan',
    accepted: 'Pelaksana menerima penugasan',
    time_started: 'Pelaksana mulai bekerja',
    time_paused: 'Timer dijeda',
    time_resumed: 'Timer dilanjutkan',
    reviewed: 'Keputusan review HOD',
    assigned: 'Penugasan pelaksana',
    submitted: 'Hasil diserahkan',
    verified: 'Hasil terverifikasi — WO ditutup',
    rejected: 'Hasil dikembalikan untuk revisi',
    extend_requested: 'Pengajuan extend deadline',
    extend_tl_approved: 'Team Leader menyetujui extend',
    extend_approved: 'Extend disetujui — deadline bergeser',
    extend_rejected: 'Pengajuan extend ditolak',
    schedule_extend_requested: 'Pengajuan extend jadwal',
    schedule_extended: 'Extend jadwal disetujui DGM/GM — jadwal bergeser',
    schedule_extend_rejected: 'Extend jadwal ditolak DGM/GM',
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const formatDateTime = (value: string): string =>
    new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

function DeadlineChip({ state, label }: { state: DeadlineState; label: string }) {
    const colors: Record<DeadlineState, string> = {
        on_track: 'bg-success-subtle text-success',
        due_soon: 'bg-warning-subtle text-warning-ink',
        overdue: 'bg-danger-subtle text-danger',
        escalated: 'bg-escalation-subtle text-escalation',
    };

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colors[state]}`}>
            {label}
        </span>
    );
}

/**
 * Detail Work Order (FR-1.x). Execution actions live in the HOD panel:
 * the review decision (immediate vs scheduled — Urgent by Accident has no
 * schedule option) and personnel assignment, both hidden without the
 * matching hak-akses verb. The server remains authoritative.
 */
export default function ShowWorkOrder(props: PageProps) {
    const { workOrder, employees, assignees, events } = props;
    const { auth } = usePage<PageProps>().props;
    const tenant = useRequiredTenant('work-orders.show');

    const canReview = auth.can?.['work-order.review'] ?? false;
    const canAssign = auth.can?.['work-order.assign'] ?? false;
    const canSubmit = auth.can?.['work-order.submit'] ?? false;
    const canVerify = auth.can?.['work-order.verify'] ?? false;
    const canExtend = auth.can?.['work-order.extend'] ?? false;
    const canExtendApprove =
        auth.can?.['work-order.extend-approve'] ?? false;
    const canRequestExtend = props.can_request_extend;
    const canDecideExtend = props.can_decide_extend;
    const isAccident = workOrder.category === 'accident';
    const reviewable = REVIEWABLE.includes(workOrder.status);
    const assignable = ASSIGNABLE.includes(workOrder.status);
    const submittable = SUBMITTABLE.includes(workOrder.status);
    const verifyable = VERIFYABLE.includes(workOrder.status);
    const isRequester = props.is_requester;

    /** Status groups for format-per-status emphasis panels. */
    const hodDecisionStatus = ['waiting_hod', 'hod_approved', 'scheduled'].includes(workOrder.status);
    const executionStatus = ['assigned', 'in_progress'].includes(workOrder.status);

    // FR-2.3: visibilitas panel extend dihitung server-side (jendela telat
    // ATAU mendekati telat ≤2 hari kerja, ceiling 3×, blokir pending
    // duplikat) — browser tidak menduplikasi aturan tersebut.
    const pendingExtend = props.extend_requests.find(
        (request) =>
            request.status === 'pending_team_leader' ||
            request.status === 'pending_hod',
    );

    return (
        <>
            <Head title={workOrder.nomor_wo} />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                {/* Hero header — WO number, status, urgent tag, deadline chip */}
                <section className="rounded-lg border bg-card p-4">
                    <div className="flex flex-wrap items-start justify-between gap-4 md:gap-6">
                        <div className="space-y-1">
                            <h1 className="text-2xl font-bold tracking-tight font-mono">
                                {workOrder.nomor_wo}
                            </h1>
                            <p className="text-sm text-ink-muted">
                                {workOrder.title}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <CategoryBadge category={workOrder.category} />
                            <StatusBadge status={workOrder.status} />
                            {isAccident && (
                                <span className="inline-flex items-center rounded-full border border-danger bg-danger/10 px-2.5 py-0.5 text-xs font-medium text-danger">
                                    Urgent by Accident
                                </span>
                            )}
                            <DeadlineChip
                                state={workOrder.deadline_state}
                                label={workOrder.deadline_label}
                            />
                        </div>
                    </div>
                </section>

                {isRequester && (
                    <RequestTracker status={workOrder.status} />
                )}

                {isRequester &&
                    workOrder.request_rejection_reason !== null && (
                        <div className="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3">
                            <TriangleAlert
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0 text-danger"
                            />
                            <div className="text-sm">
                                <p className="font-semibold text-danger">
                                    Ditolak department pengaju
                                </p>
                                <p className="text-ink-muted">
                                    {workOrder.request_rejection_reason}
                                </p>
                            </div>
                        </div>
                    )}

                {/* Status-based format emphasis — highlights differ per status */}
                {hodDecisionStatus && (
                    <section className="space-y-2 rounded-lg border bg-info/5 border-info/20 p-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                aria-hidden
                                className="size-4 text-info"
                            />
                            <h3 className="text-base font-semibold tracking-tight text-info">
                                Keputusan HOD dan Jadwal
                            </h3>
                        </div>
                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt className="text-ink-muted">Status</dt>
                            <dd className="text-ink font-medium">
                                <StatusBadge status={workOrder.status} />
                            </dd>
                            {workOrder.hod_action !== null && (
                                <>
                                    <dt className="text-ink-muted">
                                        Keputusan Eksekusi
                                    </dt>
                                    <dd className="text-ink font-medium">
                                        {workOrder.hod_action === 'schedule'
                                            ? 'Dijadwalkan'
                                            : 'Eksekusi Langsung'}
                                    </dd>
                                </>
                            )}
                            {workOrder.scheduled_date && (
                                <>
                                    <dt className="text-ink-muted">
                                        Tanggal Jadwal
                                    </dt>
                                    <dd className="text-ink tabular-nums">
                                        {formatDate(workOrder.scheduled_date)}
                                    </dd>
                                </>
                            )}
                            {workOrder.deadline_date && (
                                <>
                                    <dt className="text-ink-muted">Deadline</dt>
                                    <dd className="text-ink font-semibold tabular-nums">
                                        {formatDate(workOrder.deadline_date)}
                                    </dd>
                                </>
                            )}
                            {workOrder.extend_count > 0 && (
                                <>
                                    <dt className="text-ink-muted">
                                        Extend Deadline
                                    </dt>
                                    <dd className="text-ink text-sm">
                                        {workOrder.extend_count}×
                                    </dd>
                                </>
                            )}
                            {assignees.length > 0 && (
                                <>
                                    <dt className="text-ink-muted">
                                        Karyawan Pelaksana
                                    </dt>
                                    <dd className="text-ink text-sm">
                                        {assignees
                                            .map((a) => a.nama_employee)
                                            .join(', ')}
                                    </dd>
                                </>
                            )}
                        </dl>
                    </section>
                )}

                {executionStatus && (
                    <section className="space-y-2 rounded-lg border bg-info/5 border-info/20 p-4">
                        <div className="flex items-center gap-2">
                            <Hourglass
                                aria-hidden
                                className="size-4 text-info"
                            />
                            <h3 className="text-base font-semibold tracking-tight text-info">
                                Eksekusi dan Deadline
                            </h3>
                        </div>
                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt className="text-ink-muted">Status</dt>
                            <dd className="text-ink font-medium">
                                <StatusBadge status={workOrder.status} />
                            </dd>
                            {workOrder.deadline_date && (
                                <>
                                    <dt className="text-ink-muted">Deadline</dt>
                                    <dd className="text-ink font-semibold tabular-nums">
                                        {formatDate(workOrder.deadline_date)}
                                    </dd>
                                </>
                            )}
                            <dt className="text-ink-muted">
                                Deadline State
                            </dt>
                            <dd className="text-ink font-medium">
                                {workOrder.deadline_label}
                            </dd>
                            {workOrder.assignment_notes && (
                                <>
                                    <dt className="text-ink-muted">
                                        Catatan Penugasan
                                    </dt>
                                    <dd className="text-ink text-sm">
                                        {workOrder.assignment_notes}
                                    </dd>
                                </>
                            )}
                        </dl>
                        <p className="text-xs text-ink-muted">
                            Gunakan timer di panel{' '}
                            <b>Tugasan Saya</b> dan serahkan hasil melalui{' '}
                            <b>Serahkan Hasil Pekerjaan</b> di bawah halaman ini.
                        </p>
                    </section>
                )}

                {workOrder.status === 'pending_verify' && (
                    <section className="space-y-2 rounded-lg border bg-warning/5 border-warning/20 p-4">
                        <div className="flex items-center gap-2">
                            <Eye aria-hidden className="size-4 text-warning" />
                            <h3 className="text-base font-semibold tracking-tight text-warning">
                                Verifikasi Hasil
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Hasil pekerjaan telah diserahkan. Verifikasi untuk
                            menyetujui (menutup WO) atau menolak untuk revisi.
                        </p>
                    </section>
                )}

                {workOrder.status === 'closed' && (
                    <section className="space-y-2 rounded-lg border bg-success/5 border-success/20 p-4">
                        <div className="flex items-center gap-2">
                            <CircleCheck
                                aria-hidden
                                className="size-4 text-success"
                            />
                            <h3 className="text-base font-semibold tracking-tight text-success">
                                Selesai
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Work Order telah diverifikasi dan ditutup. Lihat hasil
                            pekerjaan di panel di bawah ini.
                        </p>
                    </section>
                )}

                {workOrder.status === 'revision' && (
                    <section className="space-y-2 rounded-lg border bg-warning/5 border-warning/20 p-4">
                        <div className="flex items-center gap-2">
                            <RotateCcw
                                aria-hidden
                                className="size-4 text-warning"
                            />
                            <h3 className="text-base font-semibold tracking-tight text-warning">
                                Perlu Revisi
                            </h3>
                        </div>
                        <p className="text-sm text-ink-muted">
                            Hasil pekerjaan dikembalikan untuk dikerjakan ulang.
                            Perbaiki dan serahkan hasil kembali melalui panel di
                            bawah ini.
                        </p>
                    </section>
                )}

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nomor Work Order
                        </dt>
                        <dd className="font-mono text-sm">
                            {workOrder.nomor_wo}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Department Tujuan
                        </dt>
                        <dd className="text-sm font-semibold">
                            {workOrder.target_department?.nama_department ??
                                '—'}
                        </dd>
                    </div>
                    {workOrder.location !== null && (
                        <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                            <dt className="text-sm text-muted-foreground">
                                Lokasi
                            </dt>
                            <dd className="text-sm font-semibold">
                                {workOrder.location}
                            </dd>
                        </div>
                    )}
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jadwal Diminta
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {workOrder.requested_schedule_date
                                ? formatDate(workOrder.requested_schedule_date)
                                : '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Dibuat
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {formatDate(workOrder.created_at)}
                        </dd>
                    </div>
                </dl>

                <section className="space-y-2">
                    <h3 className="text-base font-semibold tracking-tight">
                        Detail Permintaan
                    </h3>
                    <Separator />
                    <p className="text-sm leading-relaxed whitespace-pre-line text-ink-muted">
                        {workOrder.description}
                    </p>
                </section>

                {workOrder.attachments && workOrder.attachments.length > 0 && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Lampiran ({workOrder.attachments.length})
                        </h3>
                        <Separator />
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {workOrder.attachments.map((path, index) => (
                                <li key={path}>
                                    <a
                                        href={showAttachment.url({
                                            tenant,
                                            workOrder: workOrder.id,
                                            index,
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="block"
                                    >
                                        <img
                                            src={showAttachment.url({
                                                tenant,
                                                workOrder: workOrder.id,
                                                index,
                                            })}
                                            alt={`Lampiran ${index + 1} Work Order ${workOrder.nomor_wo}`}
                                            loading="lazy"
                                            className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {(workOrder.hod_action !== null ||
                    workOrder.assigned_at !== null) && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Keputusan HOD
                        </h3>
                        <Separator />
                        <dl className="overflow-hidden rounded-lg border bg-card">
                            <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                <dt className="text-sm text-muted-foreground">
                                    Keputusan Eksekusi
                                </dt>
                                <dd className="text-sm font-semibold">
                                    {workOrder.hod_action === 'schedule'
                                        ? 'Dijadwalkan'
                                        : 'Eksekusi Langsung'}
                                </dd>
                            </div>
                            {workOrder.scheduled_date && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Tanggal Jadwal
                                    </dt>
                                    <dd className="text-sm tabular-nums">
                                        {formatDate(workOrder.scheduled_date)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.assigned_at && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Tanggal Assign
                                    </dt>
                                    <dd className="text-sm tabular-nums">
                                        {formatDate(workOrder.assigned_at)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.deadline_date && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Deadline
                                    </dt>
                                    <dd className="text-sm font-semibold tabular-nums">
                                        {formatDate(workOrder.deadline_date)}
                                    </dd>
                                </div>
                            )}
                            {workOrder.extend_count > 0 && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Extend Deadline
                                    </dt>
                                    <dd className="text-sm">
                                        {workOrder.extend_count}
                                        {'×'}
                                        {workOrder.extend_reason !== null && (
                                            <span className="text-muted-foreground">
                                                {' — '}
                                                {workOrder.extend_reason}
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            )}
                            {workOrder.assignment_notes && (
                                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Catatan Penugasan
                                    </dt>
                                    <dd className="text-sm">
                                        {workOrder.assignment_notes}
                                    </dd>
                                </div>
                            )}
                            {assignees.length > 0 && (
                                <div className="flex items-center justify-between gap-4 px-4 py-3">
                                    <dt className="text-sm text-muted-foreground">
                                        Karyawan Pelaksana
                                    </dt>
                                    <dd className="text-sm">
                                        {assignees
                                            .map((a) => a.nama_employee)
                                            .join(', ')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>
                )}

                {(workOrder.result_notes !== null ||
                    (workOrder.result_attachments?.length ?? 0) > 0) && (
                    <section className="space-y-2">
                        <h3 className="text-base font-semibold tracking-tight">
                            Hasil Pekerjaan
                        </h3>
                        <Separator />
                        {workOrder.result_notes !== null && (
                            <p className="text-sm leading-relaxed whitespace-pre-line text-ink-muted">
                                {workOrder.result_notes}
                            </p>
                        )}
                        {(workOrder.result_attachments?.length ?? 0) > 0 && (
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {workOrder.result_attachments?.map(
                                    (path, index) => (
                                        <li key={path}>
                                            <a
                                                href={showAttachment.url({
                                                    tenant,
                                                    workOrder: workOrder.id,
                                                    index,
                                                    type: 'result',
                                                })}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="block"
                                            >
                                                <img
                                                    src={showAttachment.url({
                                                        tenant,
                                                        workOrder: workOrder.id,
                                                        index,
                                                        type: 'result',
                                                    })}
                                                    alt={`Foto hasil ${index + 1} Work Order ${workOrder.nomor_wo}`}
                                                    loading="lazy"
                                                    className="aspect-video w-full rounded-lg border bg-surface-raised object-cover"
                                                />
                                            </a>
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                    </section>
                )}

                {props.is_assignee && (
                    <MyAssignmentPanel
                        workOrderId={workOrder.id}
                        myTimeLog={props.my_time_log}
                        canAccept={props.can_accept_time}
                        canStart={props.can_start_time}
                        canPause={props.can_pause_time}
                        canResume={props.can_resume_time}
                        deadlineDate={workOrder.deadline_date}
                    />
                )}

                {props.can_decide_request && (
                    <RequestApprovalPanel
                        workOrderId={workOrder.id}
                        duplicateCandidates={props.duplicate_candidates}
                    />
                )}

                {props.can_cancel_duplicate && (
                    <CancelDuplicatePanel
                        workOrderId={workOrder.id}
                        duplicateCandidates={props.duplicate_candidates}
                    />
                )}

                {props.can_edit_request && (
                    <ReviseRequestPanel
                        workOrder={workOrder}
                        departments={props.departments}
                        canResubmit={props.can_resubmit_request}
                    />
                )}

                {props.can_cancel_request && (
                    <CancelRequestPanel workOrder={workOrder} />
                )}

                {canReview && reviewable && (
                    <ReviewPanel
                        workOrderId={workOrder.id}
                        isAccident={isAccident}
                    />
                )}

                {canAssign && assignable && (
                    <AssignPanel
                        workOrderId={workOrder.id}
                        employees={employees}
                        isRevision={workOrder.status === 'assigned'}
                    />
                )}

                {canSubmit && submittable && (
                    <SubmitPanel workOrderId={workOrder.id} />
                )}

                {canVerify && verifyable && (
                    <VerifyPanel workOrderId={workOrder.id} />
                )}

                {canRequestExtend && (
                    <ExtendRequestPanel workOrderId={workOrder.id} />
                )}

                {pendingExtend && (canExtend || canExtendApprove) && (
                    <ExtendStatusPanel
                        workOrderId={workOrder.id}
                        pendingRequest={pendingExtend}
                        canDecide={canExtendApprove && canDecideExtend}
                    />
                )}

                {props.planning !== null &&
                    (props.can_update_planning || props.can_decide_schedule) && (
                        <PlanningPanel
                            planning={props.planning}
                            workOrderId={workOrder.id}
                            canUpdate={props.can_update_planning}
                            canDecide={props.can_decide_schedule}
                        />
                    )}

                {events.length > 0 && (
                    <section className="space-y-2">
                        <div className="flex items-center gap-2">
                            <History aria-hidden className="size-4 text-ink-muted" />
                            <h3 className="text-base font-semibold tracking-tight">
                                Riwayat
                            </h3>
                        </div>
                        <Separator />
                        <ol className="space-y-3">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-start justify-between gap-4"
                                >
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {actionLabel[event.action] ??
                                                event.action}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.user_name ?? 'Sistem'}
                                            {event.from_status !== null &&
                                                event.to_status !== null && (
                                                    <>
                                                        {' — '}
                                                        {statusLabel(
                                                            event.from_status as WoStatus,
                                                        )}
                                                        {' → '}
                                                        {statusLabel(
                                                            event.to_status as WoStatus,
                                                        )}
                                                    </>
                                                )}
                                            {typeof event.meta?.notes ===
                                                'string' && (
                                                <>
                                                    {' — '}
                                                    {event.meta.notes as string}
                                                </>
                                            )}
                                            {typeof event.meta?.reason ===
                                                'string' && (
                                                <>
                                                    {' — '}
                                                    {event.meta
                                                        .reason as string}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <time className="text-xs tabular-nums whitespace-nowrap text-muted-foreground">
                                        {formatDateTime(event.created_at)}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
            </div>
        </>
    );
}

/**
 * HOD review decision (wo-slice-1 ticket 02). Urgent by Accident hides the
 * schedule option entirely — the server guard is the source of truth, this
 * is only the mirror.
 */
function ReviewPanel({
    workOrderId,
    isAccident,
}: {
    workOrderId: string;
    isAccident: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.review');

    const form = useForm<{
        action: 'execute_immediately' | 'schedule';
        scheduled_date: string;
        hod_notes: string;
    }>({
        action: isAccident ? 'execute_immediately' : 'execute_immediately',
        scheduled_date: '',
        hod_notes: '',
    });

    const showScheduleFields = form.data.action === 'schedule' && !isAccident;

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <ClipboardCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Keputusan Review
                </h3>
            </div>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(review({ tenant, workOrder: workOrderId }).url, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Eksekusi pekerjaan
                    </legend>
                    <div className="flex items-center gap-2">
                        <input
                            type="radio"
                            id="action-immediate"
                            name="action"
                            checked={form.data.action === 'execute_immediately'}
                            onChange={() =>
                                form.setData('action', 'execute_immediately')
                            }
                            className="accent-primary"
                        />
                        <Label htmlFor="action-immediate">
                            Eksekusi Langsung
                        </Label>
                    </div>
                    {!isAccident && (
                        <div className="flex items-center gap-2">
                            <input
                                type="radio"
                                id="action-schedule"
                                name="action"
                                checked={form.data.action === 'schedule'}
                                onChange={() =>
                                    form.setData('action', 'schedule')
                                }
                                className="accent-primary"
                            />
                            <Label htmlFor="action-schedule">Dijadwalkan</Label>
                        </div>
                    )}
                    {isAccident && (
                        <p className="text-xs text-muted-foreground">
                            Urgent by Accident wajib dieksekusi langsung — opsi
                            jadwal tidak tersedia.
                        </p>
                    )}
                    {form.errors.action && (
                        <p className="text-xs text-danger">
                            {form.errors.action}
                        </p>
                    )}
                </fieldset>

                {showScheduleFields && (
                    <div className="space-y-1.5">
                        <Label htmlFor="scheduled_date">Tanggal Jadwal</Label>
                        <Input
                            id="scheduled_date"
                            type="date"
                            min={(() => {
                                const d = new Date();
                                const y = d.getFullYear();
                                const m = String(d.getMonth() + 1).padStart(2, '0');
                                const day = String(d.getDate()).padStart(2, '0');
                                return `${y}-${m}-${day}`;
                            })()}
                            value={form.data.scheduled_date}
                            onChange={(event) =>
                                form.setData(
                                    'scheduled_date',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.scheduled_date)}
                        />
                        {form.errors.scheduled_date && (
                            <p className="text-xs text-danger">
                                {form.errors.scheduled_date}
                            </p>
                        )}
                    </div>
                )}

                <div className="space-y-1.5">
                    <Label htmlFor="hod_notes">Catatan (opsional)</Label>
                    <Textarea
                        id="hod_notes"
                        rows={2}
                        value={form.data.hod_notes}
                        onChange={(event) =>
                            form.setData('hod_notes', event.target.value)
                        }
                    />
                </div>

                <Button type="submit" disabled={form.processing}>
                    Simpan Keputusan
                </Button>
            </form>
        </section>
    );
}

/**
 * Personnel assignment (wo-slice-1 ticket 03): 1+ karyawan pelaksana from
 * the target department. Assigning sets the deadline clock exactly once —
 * on an assigned WO this form is a revision of the personnel list.
 */
function AssignPanel({
    workOrderId,
    employees,
    isRevision,
}: {
    workOrderId: string;
    employees: EmployeeOption[];
    isRevision: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.assign-submit');

    const form = useForm<{
        employees: string[];
        assignment_notes: string;
    }>({
        employees: [],
        assignment_notes: '',
    });

    const toggle = (id: string, checked: boolean): void => {
        const next = checked
            ? [...form.data.employees, id]
            : form.data.employees.filter((existing) => existing !== id);

        form.setData('employees', next);
    };

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <UserCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    {isRevision ? 'Revisi Penugasan' : 'Assign Karyawan'}
                </h3>
            </div>
            {isRevision && (
                <p className="text-xs text-muted-foreground">
                    Revisi mengganti daftar pelaksana — tanggal assign dan
                    deadline tidak berubah.
                </p>
            )}

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(assignSubmit({ tenant, workOrder: workOrderId }).url, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Karyawan pelaksana
                    </legend>
                    {employees.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada karyawan pada department ini.
                        </p>
                    )}
                    {employees.map((employee) => (
                        <div
                            key={employee.id}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`employee-${employee.id}`}
                                checked={form.data.employees.includes(
                                    employee.id,
                                )}
                                onCheckedChange={(checked) =>
                                    toggle(employee.id, checked === true)
                                }
                            />
                            <Label htmlFor={`employee-${employee.id}`}>
                                {employee.nama_employee}
                            </Label>
                        </div>
                    ))}
                    {form.errors.employees && (
                        <p className="text-xs text-danger">
                            {form.errors.employees}
                        </p>
                    )}
                </fieldset>

                <div className="space-y-1.5">
                    <Label htmlFor="assignment_notes">
                        Catatan penugasan (opsional)
                    </Label>
                    <Textarea
                        id="assignment_notes"
                        rows={2}
                        value={form.data.assignment_notes}
                        onChange={(event) =>
                            form.setData('assignment_notes', event.target.value)
                        }
                    />
                </div>

                <Button
                    type="submit"
                    disabled={
                        form.processing || form.data.employees.length === 0
                    }
                >
                    {isRevision ? 'Simpan Revisi' : 'Assign Karyawan'}
                </Button>
            </form>
        </section>
    );
}

ShowWorkOrder.layout = {
    breadcrumbs: [{ title: 'Work Order' }],
    title: (workOrder: WorkOrder) => workOrder.title,
    description: (workOrder: WorkOrder) =>
        `Dimohon oleh ${workOrder.requester?.name ?? '—'}`,
};

/**
 * FR-1.7: pelaksana menyerahkan hasil pekerjaan — catatan wajib, foto
 * bukti opsional (maks 3). The server guard (assigned/revision) is the
 * source of truth; this mirror hides the form elsewhere.
 */
function SubmitPanel({ workOrderId }: { workOrderId: string }) {
    const tenant = useRequiredTenant('work-orders.submit');

    const form = useForm<{
        result_notes: string;
        result_attachments: File[];
    }>({
        result_notes: '',
        result_attachments: [],
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <Send aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Serahkan Hasil Pekerjaan
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                Catatan hasil wajib diisi; foto bukti maksimal 3 file
                (jpg/jpeg/png/webp, masing-masing maks 2MB). WO akan
                menunggu verifikasi HOD.
            </p>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        submit({ tenant, workOrder: workOrderId }).url,
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="result_notes">Catatan Hasil</Label>
                    <Textarea
                        id="result_notes"
                        rows={3}
                        value={form.data.result_notes}
                        onChange={(event) =>
                            form.setData('result_notes', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.result_notes)}
                    />
                    {form.errors.result_notes && (
                        <p className="text-xs text-danger">
                            {form.errors.result_notes}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="result_attachments">
                        Foto Bukti (opsional, maks 3)
                    </Label>
                    <input
                        id="result_attachments"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        onChange={(event) =>
                            form.setData(
                                'result_attachments',
                                Array.from(event.target.files ?? []),
                            )
                        }
                        className="block w-full text-sm file:mr-3 file:rounded-sm file:border file:border-border file:bg-surface-raised file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    />
                    {form.errors.result_attachments && (
                        <p className="text-xs text-danger">
                            {form.errors.result_attachments}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={form.processing}>
                    Serahkan Hasil
                </Button>
            </form>
        </section>
    );
}

/**
 * FR-1.8: HOD memverifikasi hasil submit — approve closes the WO,
 * reject sends it back for revision with mandatory notes (deadline
 * stays untouched). The submitted result itself is rendered above as
 * the read-only "Hasil Pekerjaan" section.
 */
function VerifyPanel({ workOrderId }: { workOrderId: string }) {
    const tenant = useRequiredTenant('work-orders.verify-submit');

    const approveForm = useForm({});
    const rejectForm = useForm<{ revision_notes: string }>({
        revision_notes: '',
    });
    const [approveOpen, setApproveOpen] = React.useState(false);

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <ShieldCheck aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Verifikasi Hasil
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                Menyetujui menutup WO; menolak mengembalikannya ke pelaksana
                untuk dikerjakan ulang.
            </p>

            <ConfirmDialog
                open={approveOpen}
                onOpenChange={setApproveOpen}
                title="Tutup Work Order?"
                description="Tindakan ini akan menutup WO secara permanen dan tidak dapat dibatalkan."
                confirmLabel="Ya, tutup WO"
                cancelLabel="Batal"
                onConfirm={() => approveForm.post(verifySubmit({ tenant, workOrder: workOrderId }).url, { preserveScroll: true })}
                processing={approveForm.processing}
            >
                <Button type="button" onClick={() => setApproveOpen(true)} disabled={approveForm.processing}>
                    Setujui & Tutup WO
                </Button>
            </ConfirmDialog>

            <Separator />

            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    rejectForm.post(
                        reject({ tenant, workOrder: workOrderId }).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => rejectForm.reset(),
                        },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="revision_notes">
                        Catatan Revisi (wajib untuk penolakan)
                    </Label>
                    <Textarea
                        id="revision_notes"
                        rows={2}
                        value={rejectForm.data.revision_notes}
                        onChange={(event) =>
                            rejectForm.setData(
                                'revision_notes',
                                event.target.value,
                            )
                        }
                        aria-invalid={Boolean(rejectForm.errors.revision_notes)}
                    />
                    {rejectForm.errors.revision_notes && (
                        <p className="text-xs text-danger">
                            {rejectForm.errors.revision_notes}
                        </p>
                    )}
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={rejectForm.processing}
                >
                    Tolak & Kembalikan untuk Revisi
                </Button>
            </form>
        </section>
    );
}

/**
 * FR-2.3: pengajuan extend deadline WO telat — maks 3 hari per request,
 * maks 3 approved per WO. Approval berjenjang Team Leader (jika ada) →
 * HOD. The server guards (telat, batas, duplikat, rantai) are the source
 * of truth; this form is only the mirror for the requester.
 */
function ExtendRequestPanel({ workOrderId }: { workOrderId: string }) {
    const tenant = useRequiredTenant('work-orders.extend-requests.store');

    const form = useForm<{
        days: number;
        reason: string;
    }>({
        days: 1,
        reason: '',
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <CalendarClock aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Ajukan Extend Deadline
                </h3>
            </div>
            <p className="text-xs text-muted-foreground">
                WO sudah melewati deadline. Maksimal 3 hari per pengajuan dan
                3 pengajuan disetujui per WO. Pengajuan diperiksa Team Leader
                (bila ada) lalu HOD.
            </p>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        storeExtendRequest({
                            tenant,
                            workOrder: workOrderId,
                        }).url,
                        {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        },
                    );
                }}
            >
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Tambahan hari kerja
                    </legend>
                    <div className="flex flex-wrap items-center gap-4">
                        {[1, 2, 3].map((value) => (
                            <div
                                key={value}
                                className="flex items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    id={`extend-days-${value}`}
                                    name="days"
                                    checked={form.data.days === value}
                                    onChange={() =>
                                        form.setData('days', value)
                                    }
                                    className="accent-primary"
                                />
                                <Label htmlFor={`extend-days-${value}`}>
                                    {value} hari
                                </Label>
                            </div>
                        ))}
                    </div>
                    {form.errors.days && (
                        <p className="text-xs text-danger">
                            {form.errors.days}
                        </p>
                    )}
                </fieldset>

                <div className="space-y-1.5">
                    <Label htmlFor="extend_reason">Alasan (wajib)</Label>
                    <Textarea
                        id="extend_reason"
                        rows={3}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.reason)}
                    />
                    {form.errors.reason && (
                        <p className="text-xs text-danger">
                            {form.errors.reason}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={form.processing}>
                    Ajukan Extend
                </Button>
            </form>
        </section>
    );
}

/**
 * FR-2.3: status pengajuan extend yang masih berjalan — pengaju melihat
 * status, approver berizin memutuskan (Setujui / Tolak dengan alasan).
 * Posisi rantai (TL → HOD) ditegakkan server; tombol hanya cermin.
 */
function ExtendStatusPanel({
    workOrderId,
    pendingRequest,
    canDecide,
}: {
    workOrderId: string;
    pendingRequest: ExtendRequestView;
    canDecide: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.extend-requests.approve');

    const approveForm = useForm({});
    const rejectForm = useForm<{ reason: string }>({
        reason: '',
    });
    const [approveOpen, setApproveOpen] = React.useState(false);

    const stageLabel =
        pendingRequest.status === 'pending_team_leader'
            ? 'Menunggu persetujuan Team Leader'
            : 'Menunggu persetujuan HOD';

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <Hourglass aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Pengajuan Extend Deadline
                </h3>
            </div>

            <dl className="overflow-hidden rounded-lg border">
                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                    <dt className="text-sm text-muted-foreground">Status</dt>
                    <dd className="text-sm font-semibold">{stageLabel}</dd>
                </div>
                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                    <dt className="text-sm text-muted-foreground">Diajukan</dt>
                    <dd className="text-sm">
                        {pendingRequest.requester_name ?? '—'}
                        {' — '}
                        {formatDateTime(pendingRequest.created_at)}
                    </dd>
                </div>
                <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                    <dt className="text-sm text-muted-foreground">
                        Tambahan
                    </dt>
                    <dd className="text-sm font-semibold tabular-nums">
                        {pendingRequest.days} hari kerja
                    </dd>
                </div>
                <div className="flex items-center justify-between gap-4 px-4 py-3">
                    <dt className="text-sm text-muted-foreground">Alasan</dt>
                    <dd className="max-w-md text-right text-sm">
                        {pendingRequest.reason}
                    </dd>
                </div>
            </dl>

            {canDecide && (
                <>
                    <ConfirmDialog
                        open={approveOpen}
                        onOpenChange={setApproveOpen}
                        title="Setujui Extend Deadline?"
                        description={`Ini akan menambah ${pendingRequest.days} hari kerja ke deadline WO.`}
                        confirmLabel="Ya, setujui"
                        cancelLabel="Batal"
                        onConfirm={() => approveForm.post(approveExtendRequest({
                            tenant,
                            workOrder: workOrderId,
                            extendRequest: pendingRequest.id,
                        }).url, { preserveScroll: true })}
                        processing={approveForm.processing}
                    >
                        <Button type="button" onClick={() => setApproveOpen(true)} disabled={approveForm.processing}>
                            Setujui Extend
                        </Button>
                    </ConfirmDialog>

                    <Separator />

                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            rejectForm.post(
                                rejectExtendRequest({
                                    tenant,
                                    workOrder: workOrderId,
                                    extendRequest: pendingRequest.id,
                                }).url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => rejectForm.reset(),
                                },
                            );
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="extend_reject_reason">
                                Alasan Penolakan (wajib)
                            </Label>
                            <Textarea
                                id="extend_reject_reason"
                                rows={2}
                                value={rejectForm.data.reason}
                                onChange={(event) =>
                                    rejectForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    rejectForm.errors.reason,
                                )}
                            />
                            {rejectForm.errors.reason && (
                                <p className="text-xs text-danger">
                                    {rejectForm.errors.reason}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={rejectForm.processing}
                        >
                            Tolak Extend
                        </Button>
                    </form>
                </>
            )}
        </section>
    );
}

/**
 * FR-2.4: Work Planning — detail jadwal & status extend jadwal.
 * HOD boleh memperbarui detail; DGM/GM memutuskan extend jadwal.
 */
function PlanningPanel({
    planning,
    workOrderId,
    canUpdate,
    canDecide,
}: {
    planning: PlanningView;
    workOrderId: string;
    canUpdate: boolean;
    canDecide: boolean;
}) {
    const tenant = useRequiredTenant('work-planning.update');

    const updateForm = useForm<{
        tgl_jadwal: string;
        jam_mulai: string;
        jam_selesai: string;
        jenis_pekerjaan: string;
        lama_pekerjaan_hari: number;
        budget: number;
        catatan: string;
    }>({
        tgl_jadwal: planning.tgl_jadwal ?? '',
        jam_mulai: planning.jam_mulai ?? '',
        jam_selesai: planning.jam_selesai ?? '',
        jenis_pekerjaan: planning.jenis_pekerjaan ?? '',
        lama_pekerjaan_hari: planning.lama_pekerjaan_hari ?? 1,
        budget: planning.budget ?? 0,
        catatan: planning.catatan ?? '',
    });

    const approveForm = useForm({});
    const rejectForm = useForm<{ catatan: string }>({
        catatan: '',
    });
    const [approveOpen, setApproveOpen] = React.useState(false);

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <CalendarRange aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Detail Jadwal
                </h3>
            </div>

            {planning.pending && canDecide && (
                <>
                    <p className="text-xs text-warning">
                        Ada pengajuan extend jadwal menunggu keputusan DGM/GM.
                    </p>
                    <Separator />
                </>
            )}

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (canUpdate) {
                        // PATCH route — form.post tanpa _method spoof akan
                        // 405 (Laravel mendaftarkan hanya PATCH).
                        updateForm.patch(
                            updatePlanningDetails({
                                tenant,
                                workPlanning: planning.id,
                            }).url,
                            { preserveScroll: true },
                        );
                    }
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="tgl_jadwal">Tanggal Jadwal</Label>
                        <Input
                            id="tgl_jadwal"
                            type="date"
                            value={updateForm.data.tgl_jadwal}
                            onChange={(event) =>
                                updateForm.setData('tgl_jadwal', event.target.value)
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="jam_mulai">Jam Mulai</Label>
                        <Input
                            id="jam_mulai"
                            type="time"
                            value={updateForm.data.jam_mulai}
                            onChange={(event) =>
                                updateForm.setData('jam_mulai', event.target.value)
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="jam_selesai">Jam Selesai</Label>
                        <Input
                            id="jam_selesai"
                            type="time"
                            value={updateForm.data.jam_selesai}
                            onChange={(event) =>
                                updateForm.setData('jam_selesai', event.target.value)
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="jenis_pekerjaan">Jenis Pekerjaan</Label>
                        <Input
                            id="jenis_pekerjaan"
                            value={updateForm.data.jenis_pekerjaan}
                            onChange={(event) =>
                                updateForm.setData('jenis_pekerjaan', event.target.value)
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="lama_pekerjaan_hari">Lama (hari)</Label>
                        <Input
                            id="lama_pekerjaan_hari"
                            type="number"
                            min={1}
                            value={updateForm.data.lama_pekerjaan_hari}
                            onChange={(event) =>
                                updateForm.setData(
                                    'lama_pekerjaan_hari',
                                    parseInt(event.target.value) || 1,
                                )
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="budget">Anggaran</Label>
                        <Input
                            id="budget"
                            type="number"
                            min={0}
                            step={1000}
                            value={updateForm.data.budget}
                            onChange={(event) =>
                                updateForm.setData(
                                    'budget',
                                    parseInt(event.target.value) || 0,
                                )
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                    <div className="space-y-1.5 sm:col-span-2">
                        <Label htmlFor="catatan">Catatan (opsional)</Label>
                        <Textarea
                            id="catatan"
                            rows={2}
                            value={updateForm.data.catatan}
                            onChange={(event) =>
                                updateForm.setData('catatan', event.target.value)
                            }
                            disabled={!canUpdate}
                        />
                    </div>
                </div>

                {canUpdate && (
                    <Button type="submit" disabled={updateForm.processing}>
                        Simpan Perubahan
                    </Button>
                )}
            </form>

            {planning.pending && canDecide && (
                <>
                    <Separator />
                    <ConfirmDialog
                        title="Setujui Extend Jadwal?"
                        description="Jadwal WO akan bergeser ke tanggal yang diajukan."
                        confirmLabel="Ya, setujui"
                        cancelLabel="Batal"
                        open={approveOpen}
                        onOpenChange={setApproveOpen}
                        onConfirm={() => approveForm.post(approveScheduleExtend({
                            tenant,
                            workPlanning: planning.id,
                        }).url, { preserveScroll: true })}
                        processing={approveForm.processing}
                    >
                        Setujui Extend Jadwal
                    </ConfirmDialog>

                    <Separator />

                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            rejectForm.post(
                                rejectScheduleExtend({
                                    tenant,
                                    workPlanning: planning.id,
                                }).url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => rejectForm.reset(),
                                },
                            );
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="schedule_reject_catatan">
                                Alasan Penolakan (wajib)
                            </Label>
                            <Textarea
                                id="schedule_reject_catatan"
                                rows={2}
                                value={rejectForm.data.catatan}
                                onChange={(event) =>
                                    rejectForm.setData('catatan', event.target.value)
                                }
                                aria-invalid={Boolean(rejectForm.errors.catatan)}
                            />
                            {rejectForm.errors.catatan && (
                                <p className="text-xs text-danger">
                                    {rejectForm.errors.catatan}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={rejectForm.processing}
                        >
                            Tolak Extend Jadwal
                        </Button>
                    </form>
                </>
            )}
        </section>
    );
}
/**
 * R19: tracker status untuk pemohon — enam tahap alur, termasuk gerbang
 * approval department pengaju (ADR-0011). Diposisikan server-side
 * (is_requester) supaya viewer lain tetap melihat StatusBadge saja.
 */
function RequestTracker({ status }: { status: WoStatus }) {
    const steps = [
        { key: 'submitted', label: 'Diajukan', reached: true },
        {
            key: 'approval',
            label: 'Persetujuan Dept',
            reached:
                status !== 'pending_dept_approval' && status !== 'cancelled',
            current: status === 'pending_dept_approval',
        },
        {
            key: 'review',
            label: 'Review HOD',
            reached: ['assigned', 'in_progress', 'pending_verify', 'closed', 'revision'].includes(status),
            current: ['waiting_hod', 'hod_approved', 'scheduled'].includes(status),
        },
        {
            key: 'work',
            label: 'Dikerjakan',
            reached: ['pending_verify', 'closed'].includes(status),
            current: ['assigned', 'in_progress', 'revision'].includes(status),
        },
        {
            key: 'verify',
            label: 'Verifikasi',
            reached: status === 'closed',
            current: status === 'pending_verify',
        },
        {
            key: 'done',
            label: 'Selesai',
            reached: status === 'closed',
            current: false,
        },
    ];

    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-2 rounded-lg border bg-card px-4 py-3">
            {steps.map((step, index) => (
                <li key={step.key} className="flex items-center gap-2">
                    {index > 0 && (
                        <span
                            aria-hidden
                            className={`h-px w-4 ${step.reached ? 'bg-success' : 'bg-border'}`}
                        />
                    )}
                    <span
                        className={`inline-flex items-center gap-1.5 text-xs font-medium ${
                            step.current
                                ? 'rounded-full bg-info/10 px-2 py-0.5 text-info'
                                : step.reached
                                  ? 'text-success'
                                  : 'text-ink-subtle'
                        }`}
                        aria-current={step.current ? 'step' : undefined}
                    >
                        <span
                            aria-hidden
                            className={`inline-block size-2 rounded-full ${
                                step.current
                                    ? 'bg-info'
                                    : step.reached
                                      ? 'bg-success'
                                      : 'bg-border'
                            }`}
                        />
                        {step.label}
                    </span>
                </li>
            ))}
        </ol>
    );
}

/**
 * R2/R3: keputusan approver department pengaju — Setujui (rilis ke
 * department tujuan) atau Tolak (alasan wajib). Banner duplikat tampil
 * bila kandidat ada (OQ-4b); keputusan tetap dipaksa di service.
 */
function RequestApprovalPanel({
    workOrderId,
    duplicateCandidates,
}: {
    workOrderId: string;
    duplicateCandidates: { id: string; nomor_wo: string }[];
}) {
    const tenant = useRequiredTenant('work-orders.request.approve');
    const [rejectOpen, setRejectOpen] = useState(false);
    const rejectForm = useForm<{ reason: string }>({ reason: '' });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <ShieldCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Keputusan Department Pengaju
                </h3>
            </div>

            <p className="text-sm text-muted-foreground">
                Permintaan ini ditujukan ke department lain. Menyetujui akan
                merilisnya ke department tujuan; menolak akan menghentikannya
                sebelum sampai ke sana.
            </p>

            {duplicateCandidates.length > 0 && (
                <p className="flex items-start gap-1.5 rounded-md border border-warning/30 bg-warning/10 px-2.5 py-1.5 text-xs text-warning-ink">
                    <TriangleAlert aria-hidden className="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        Kemungkinan duplikat:{' '}
                        <span className="font-mono">
                            {duplicateCandidates
                                .map((candidate) => candidate.nomor_wo)
                                .join(', ')}
                        </span>{' '}
                        — pastikan sebelum memutuskan.
                    </span>
                </p>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    onClick={() =>
                        router.post(
                            approveRequestAction({ tenant, workOrder: workOrderId })
                                .url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Setujui & Rilis
                </Button>
                <Button
                    variant="destructive"
                    onClick={() => setRejectOpen(true)}
                >
                    Tolak
                </Button>
            </div>

            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tolak permintaan</DialogTitle>
                        <DialogDescription>
                            Alasan wajib diisi — pemohon menerima penjelasan
                            ini dan dapat merevisi lalu mengajukan ulang.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            rejectForm.post(
                                rejectRequestAction({ tenant, workOrder: workOrderId })
                                    .url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        rejectForm.reset();
                                        setRejectOpen(false);
                                    },
                                },
                            );
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="request-reject-reason">
                                Alasan penolakan
                            </Label>
                            <Textarea
                                id="request-reject-reason"
                                rows={3}
                                required
                                value={rejectForm.data.reason}
                                onChange={(event) =>
                                    rejectForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(rejectForm.errors.reason)}
                            />
                            {rejectForm.errors.reason && (
                                <p className="text-xs text-danger">
                                    {rejectForm.errors.reason}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setRejectOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={rejectForm.processing}
                            >
                                Tolak Permintaan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}

/**
 * R12: revisi permintaan yang ditolak — hanya pemohon; setelah tersimpan,
 * tombol ajukan ulang mengembalikan WO ke gerbang approval.
 */
function ReviseRequestPanel({
    workOrder,
    departments,
    canResubmit,
}: {
    workOrder: PageProps['workOrder'];
    departments: PageProps['departments'];
    canResubmit: boolean;
}) {
    const tenant = useRequiredTenant('work-orders.request.update');
    const form = useForm<{
        title: string;
        description: string;
        location: string;
        category: 'normal' | 'accident' | 'owner';
        target_department_id: string;
    }>({
        title: workOrder.title,
        description: workOrder.description,
        location: workOrder.location ?? '',
        category: workOrder.category,
        target_department_id: workOrder.target_department_id,
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <History aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Perbaiki Permintaan
                </h3>
            </div>

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(
                        updateRequestAction({ tenant, workOrder: workOrder.id })
                            .url,
                        { preserveScroll: true },
                    );
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="request-title">Judul</Label>
                    <Input
                        id="request-title"
                        required
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.title)}
                    />
                    {form.errors.title && (
                        <p className="text-xs text-danger">
                            {form.errors.title}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="request-description">Deskripsi</Label>
                    <Textarea
                        id="request-description"
                        rows={3}
                        required
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.description)}
                    />
                    {form.errors.description && (
                        <p className="text-xs text-danger">
                            {form.errors.description}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="request-location">
                        Lokasi (opsional)
                    </Label>
                    <Input
                        id="request-location"
                        value={form.data.location}
                        onChange={(event) =>
                            form.setData('location', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.location)}
                    />
                    {form.errors.location && (
                        <p className="text-xs text-danger">
                            {form.errors.location}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="request-category">Kategori</Label>
                        <select
                            id="request-category"
                            className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm"
                            value={form.data.category}
                            onChange={(event) =>
                                form.setData(
                                    'category',
                                    event.target
                                        .value as PageProps['workOrder']['category'],
                                )
                            }
                        >
                            <option value="normal">Normal</option>
                            <option value="accident">Urgent · Kecelakaan</option>
                            <option value="owner">Urgent · Owner</option>
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="request-department">
                            Department Tujuan
                        </Label>
                        <select
                            id="request-department"
                            className="h-10 w-full rounded-lg border border-border bg-card px-3 text-sm"
                            value={form.data.target_department_id}
                            onChange={(event) =>
                                form.setData(
                                    'target_department_id',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(
                                form.errors.target_department_id,
                            )}
                        >
                            <option value="" disabled>
                                Pilih department
                            </option>
                            {departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.nama_department ?? '—'}
                                </option>
                            ))}
                        </select>
                        {form.errors.target_department_id && (
                            <p className="text-xs text-danger">
                                {form.errors.target_department_id}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" variant="secondary" disabled={form.processing}>
                        Simpan Revisi
                    </Button>
                    {canResubmit && (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    resubmitRequestAction({
                                        tenant,
                                        workOrder: workOrder.id,
                                    }).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Ajukan Ulang
                        </Button>
                    )}
                </div>
            </form>
        </section>
    );
}

/**
 * R12: pembatalan oleh pemohon/approver department pengaju sebelum WO
 * dirilis — terminal, alasan opsional tetapi tercatat di histori.
 */
function CancelRequestPanel({ workOrder }: { workOrder: PageProps['workOrder'] }) {
    const tenant = useRequiredTenant('work-orders.request.cancel');
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: '' });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <Ban aria-hidden className="size-4 text-ink-muted" />
                <h3 className="text-base font-semibold tracking-tight">
                    Batalkan Permintaan
                </h3>
            </div>

            <p className="text-sm text-muted-foreground">
                Work Order belum dirilis ke department tujuan. Membatalkan
                bersifat permanen.
            </p>

            <Button variant="destructive" onClick={() => setOpen(true)}>
                Batalkan
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Batalkan permintaan?</DialogTitle>
                        <DialogDescription>
                            {workOrder.nomor_wo} akan berstatus Dibatalkan dan
                            tidak dapat diajukan ulang.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(
                                cancelRequestAction({
                                    tenant,
                                    workOrder: workOrder.id,
                                }).url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        form.reset();
                                        setOpen(false);
                                    },
                                },
                            );
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="cancel-reason">
                                Alasan (opsional, tercatat di histori)
                            </Label>
                            <Textarea
                                id="cancel-reason"
                                rows={2}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            {form.errors.reason && (
                                <p className="text-xs text-danger">
                                    {form.errors.reason}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setOpen(false)}
                            >
                                Kembali
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={form.processing}
                            >
                                Batalkan Permintaan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}

/**
 * OQ-4b: reviewer department tujuan membatalkan WO pada tahap review
 * sebagai DUPLIKAT — wajib menunjuk WO duplikatnya dari daftar kandidat.
 */
function CancelDuplicatePanel({
    workOrderId,
    duplicateCandidates,
}: {
    workOrderId: string;
    duplicateCandidates: { id: string; nomor_wo: string }[];
}) {
    const tenant = useRequiredTenant('work-orders.request.cancel');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        duplicate_of: string;
        reason: string;
        is_review_duplicate: boolean;
    }>({
        duplicate_of: '',
        reason: '',
        is_review_duplicate: true,
    });

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <TriangleAlert aria-hidden className="size-4 text-warning" />
                <h3 className="text-base font-semibold tracking-tight">
                    Batalkan sebagai Duplikat
                </h3>
            </div>

            <p className="text-sm text-muted-foreground">
                Bila Work Order ini duplikat dari permintaan yang sama,
                batalkan dengan menunjuk WO asalnya. Pemohon akan diberi tahu.
            </p>

            <Button
                variant="secondary"
                disabled={duplicateCandidates.length === 0}
                onClick={() => setOpen(true)}
            >
                Batalkan sebagai Duplikat
            </Button>

            {duplicateCandidates.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    Tidak ditemukan WO lain dengan judul serupa dalam 30 hari
                    terakhir.
                </p>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Batalkan sebagai duplikat</DialogTitle>
                        <DialogDescription>
                            Pilih Work Order asal yang diduplikasi oleh WO ini.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(
                                cancelRequestAction({
                                    tenant,
                                    workOrder: workOrderId,
                                }).url,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        form.reset();
                                        setOpen(false);
                                    },
                                },
                            );
                        }}
                    >
                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium">
                                WO asal (wajib)
                            </legend>
                            {duplicateCandidates.map((candidate) => (
                                <div
                                    key={candidate.id}
                                    className="flex items-center gap-2"
                                >
                                    <input
                                        type="radio"
                                        id={`duplicate-${candidate.id}`}
                                        name="duplicate_of"
                                        checked={
                                            form.data.duplicate_of ===
                                            candidate.id
                                        }
                                        onChange={() =>
                                            form.setData(
                                                'duplicate_of',
                                                candidate.id,
                                            )
                                        }
                                        className="accent-primary"
                                    />
                                    <Label
                                        htmlFor={`duplicate-${candidate.id}`}
                                        className="font-mono text-sm"
                                    >
                                        {candidate.nomor_wo}
                                    </Label>
                                </div>
                            ))}
                            {form.errors.duplicate_of && (
                                <p className="text-xs text-danger">
                                    {form.errors.duplicate_of}
                                </p>
                            )}
                        </fieldset>

                        <div className="space-y-1.5">
                            <Label htmlFor="duplicate-reason">
                                Alasan (opsional)
                            </Label>
                            <Textarea
                                id="duplicate-reason"
                                rows={2}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setOpen(false)}
                            >
                                Kembali
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    form.processing ||
                                    form.data.duplicate_of === ''
                                }
                            >
                                Batalkan sebagai Duplikat
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}

/**
 * R15–R17: panel tugas untuk pelaksana — terima penugasan, mulai bekerja,
 * jeda/lanjut, dengan timer live berbasis timestamp server. Aksi
 * "Selesai" tetap lewat Submit Hasil (panel terpisah) yang otomatis
 * mengakhiri timer. Semua keputusan dipaksa ulang di server.
 */
function MyAssignmentPanel({
    workOrderId,
    myTimeLog,
    canAccept,
    canStart,
    canPause,
    canResume,
    deadlineDate,
}: {
    workOrderId: string;
    myTimeLog: PageProps['my_time_log'];
    canAccept: boolean;
    canStart: boolean;
    canPause: boolean;
    canResume: boolean;
    deadlineDate: string | null;
}) {
    const tenant = useRequiredTenant('work-orders.work-time.accept');
    const state = myTimeLog?.state ?? 'not_started';
    const elapsed = myTimeLog?.elapsed_seconds ?? 0;

    const remainingDays = deadlineDate === null
        ? null
        : Math.round(
              (new Date(deadlineDate).getTime() -
                  new Date().setHours(0, 0, 0, 0)) /
                  86_400_000,
          );

    const act = (
        action: 'accept' | 'start' | 'pause' | 'resume',
    ): void => {
        router.post(
            workTime[action]({ tenant, workOrder: workOrderId }).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <section className="space-y-3 rounded-lg border bg-card p-4">
            <div className="flex items-center gap-2">
                <UserCheck aria-hidden className="size-4 text-info" />
                <h3 className="text-base font-semibold tracking-tight">
                    Tugasan Saya
                </h3>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <LiveTimer elapsedSeconds={elapsed} state={state} />
                {deadlineDate !== null && remainingDays !== null && (
                    <span
                        className={`text-xs font-medium ${
                            remainingDays < 0
                                ? 'text-danger'
                                : remainingDays <= 1
                                  ? 'text-warning-ink'
                                  : 'text-ink-muted'
                        }`}
                    >
                        {remainingDays < 0
                            ? `Lewat deadline ${Math.abs(remainingDays)} hari`
                            : remainingDays === 0
                              ? 'Deadline hari ini'
                              : `Sisa ${remainingDays} hari menuju deadline`}
                    </span>
                )}
            </div>

            {state === 'not_started' && (
                <p className="text-sm text-muted-foreground">
                    Terima penugasan ini lalu mulai bekerja — durasi akan
                    tercatat otomatis.
                </p>
            )}
            {state === 'paused' && (
                <p className="text-sm text-muted-foreground">
                    Timer sedang dijeda. Lanjutkan saat siap bekerja kembali.
                </p>
            )}
            {state === 'finished' && (
                <p className="text-sm text-muted-foreground">
                    Timer selesai — hasil telah diserahkan untuk verifikasi.
                </p>
            )}

            <div className="flex flex-wrap gap-2">
                {canAccept && (
                    <Button onClick={() => act('accept')}>
                        Terima Penugasan
                    </Button>
                )}
                {canStart && (
                    <Button onClick={() => act('start')}>Mulai Bekerja</Button>
                )}
                {canPause && (
                    <Button variant="secondary" onClick={() => act('pause')}>
                        Jeda
                    </Button>
                )}
                {canResume && (
                    <Button onClick={() => act('resume')}>Lanjutkan</Button>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                Menyelesaikan pekerjaan = Serahkan Hasil di bagian bawah
                halaman — timer ikut berakhir otomatis.
            </p>
        </section>
    );
}
