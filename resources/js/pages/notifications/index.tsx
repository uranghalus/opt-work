import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import type { InertiaConfig } from '@inertiajs/core';

type AppNotification = {
    id: string;
    type: string;
    data: {
        type?: string;
        message?: string;
        nomor_wo?: string;
        title?: string;
    };
    read_at: string | null;
    created_at: string;
};

type PageProps = {
    notifications: { data: AppNotification[] };
};

/** Unread count chip plus the mark-all-read action, in the heading band. */
function NotificationActions() {
    const { unreadNotificationsCount } = usePage<
        InertiaConfig['sharedPageProps']
    >().props;
    const unread = Number(unreadNotificationsCount ?? 0);

    if (unread === 0) {
        return null;
    }

    return (
        <>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand">
                <span
                    aria-hidden="true"
                    className="size-1.5 rounded-full bg-brand"
                />
                {unread} belum dibaca
            </span>
            <Form
                {...NotificationController.markAllAsRead.form()}
                data-test="mark-all-read-button"
            >
                {({ processing }) => (
                    <Button
                        variant="outline"
                        size="lg"
                        disabled={processing}
                    >
                        Tandai semua dibaca
                    </Button>
                )}
            </Form>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifikasi' }],
    title: 'Notifikasi',
    description: 'Riwayat notifikasi akun Anda',
    actions: NotificationActions,
};export default function Notifications({ notifications }: PageProps) {

    return (
        <>
            <Head title="Notifikasi" />

            <div className="mx-auto w-full max-w-2xl space-y-6">
                <div className="space-y-2">
                    {notifications.data.length === 0 ? (
                        <div className="rounded-lg border bg-card px-4 py-10 text-center text-sm text-ink-muted">
                            Belum ada notifikasi.
                        </div>
                    ) : (
                        notifications.data.map((notification) => {
                            const isUnread = notification.read_at === null;

                            return (
                                <div
                                    key={notification.id}
                                    data-test="notification-item"
                                    className={
                                        'flex items-start justify-between gap-4 rounded-lg border bg-card px-4 py-3 ' +
                                        (isUnread
                                            ? 'border-brand/30 ring-1 ring-brand/10'
                                            : '')
                                    }
                                >
                                    <div className="min-w-0 space-y-0.5">
                                        {isUnread && (
                                            <span className="mb-1 inline-flex items-center gap-1.5 text-[0.6875rem] font-semibold tracking-wide text-brand uppercase">
                                                <span
                                                    aria-hidden="true"
                                                    className="size-1.5 rounded-full bg-brand"
                                                />
                                                Belum dibaca
                                            </span>
                                        )}
                                        <p className="text-sm font-semibold">
                                            {notification.data?.title ??
                                                'Notifikasi'}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            {notification.data?.message ?? ''}
                                        </p>
                                        <p className="font-mono text-xs text-ink-subtle">
                                            {notification.created_at}
                                        </p>
                                    </div>
                                    {isUnread && (
                                        <Form
                                            {...NotificationController.markAsRead.form({
                                                id: notification.id,
                                            })}
                                            className="shrink-0"
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Tandai dibaca
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </>
    );
}
