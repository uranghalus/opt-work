import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { toast } from 'sonner';

/** Minimal window.Echo surface used here — the global is created in @/echo. */
type EchoLike = {
    private: (name: string) => {
        notification: (
            callback: (notification: {
                message?: string;
                nomor_wo?: string;
            }) => void,
        ) => void;
        unsubscribe: () => void;
    };
};

const echoGlobal = (): EchoLike | undefined =>
    (window as unknown as { Echo?: EchoLike }).Echo;

/**
 * FR-7: realtime in-app layer — every broadcast notification for this user
 * arrives on the private notification channel; we toast its message and
 * refresh the lazy bell counter with a partial reload. When the WebSocket
 * is down the channel never attaches and the bell keeps its
 * navigation-time value, so nothing breaks.
 */
export function RealtimeNotifications() {
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const userId = auth?.user?.id;

    useEffect(() => {
        const echo = echoGlobal();

        if (!echo || !userId) {
            return;
        }

        const channel = echo.private(`App.Models.User.${userId}`);

        channel.notification((notification) => {
            toast(notification.message ?? 'Notifikasi baru', {
                description: notification.nomor_wo,
            });
            router.reload({ only: ['unreadNotificationsCount'] });
        });

        return () => {
            channel.unsubscribe();
        };
    }, [userId]);

    return null;
}
