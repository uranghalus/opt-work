import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/heading';
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

export default function Notifications({ notifications }: PageProps) {
    const { unreadNotificationsCount } = usePage<InertiaConfig['sharedPageProps']>().props;
    const unread = Number(unreadNotificationsCount ?? 0);

    return (
        <>
            <Head title="Notifikasi" />

            <div className="mx-auto w-full max-w-2xl space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Notifikasi"
                        description={
                            unread > 0
                                ? `${unread} notifikasi belum dibaca`
                                : 'Semua notifikasi sudah dibaca'
                        }
                    />
                    {unread > 0 && (
                        <Form
                            {...NotificationController.markAllAsRead.form()}
                            data-test="mark-all-read-button"
                        >
                            {({ processing }) => (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Tandai semua dibaca
                                </Button>
                            )}
                        </Form>
                    )}
                </div>

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
                                        (isUnread ? 'border-l-4 border-l-warning' : '')
                                    }
                                >
                                    <div className="min-w-0 space-y-0.5">
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
