import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Browser-only module: the @inertiajs/vite dev SSR evaluates this import
// graph in Node, where `window` does not exist. Guarding here keeps the
// realtime wiring (FR-7) importable from anywhere without breaking SSR.
if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
