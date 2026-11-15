import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const resolveReverbHost = () => {
    const configured = String(import.meta.env.VITE_REVERB_HOST || '').trim();
    const browserHost = window.location.hostname || 'localhost';
    if (
        !configured ||
        configured === '0.0.0.0' ||
        ((configured === 'localhost' || configured === '127.0.0.1') &&
            browserHost !== 'localhost' &&
            browserHost !== '127.0.0.1')
    ) {
        return browserHost;
    }
    return configured;
};

const resolveReverbScheme = () => {
    if (window.location.protocol === 'https:') {
        return 'https';
    }
    const configured = String(import.meta.env.VITE_REVERB_SCHEME || 'http').trim().toLowerCase();
    return configured === 'https' ? 'https' : 'http';
};

const reverbScheme = resolveReverbScheme();
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT || 8080);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'storkia-reverb-key',
    wsHost: resolveReverbHost(),
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    },
});

const subscribedPrivateChannels = new Set();

const attachStandardRealtimeListeners = (channel, channelName) => {
    if (!channel) return;

    channel
        .listen('.storkia.updated', (payload) => {
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), channel: channelName }
            }));
        })
        .listen('.OrderLifecycleNotification', (payload) => {
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), scope: 'notification', channel: channelName }
            }));
        })
        .listen('.CartQuantityUpdated', (payload) => {
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: payload }));
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), scope: 'cart', action: 'cart.updated', channel: channelName }
            }));
        })
        .listen('.MessageSent', (payload) => {
            window.dispatchEvent(new CustomEvent('storkia:chat-message', { detail: payload }));
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), scope: 'chat', action: 'message.received', channel: channelName }
            }));
        })
        .listen('.SupportMessageSent', (payload) => {
            window.dispatchEvent(new CustomEvent('storkia:support-message', { detail: payload }));
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), scope: 'support_chat', action: 'support.message.received', channel: channelName }
            }));
        });
};

const subscribePrivateChannelOnce = (channelName) => {
    const clean = String(channelName || '').trim();
    if (!clean || !window.Echo || subscribedPrivateChannels.has(clean)) return;
    subscribedPrivateChannels.add(clean);
    try {
        const ch = window.Echo.private(clean);
        attachStandardRealtimeListeners(ch, clean);
    } catch (err) {
        subscribedPrivateChannels.delete(clean);
    }
};

const syncDynamicPageChannels = () => {
    if (!window.Echo) return;
    const isAuthenticated = Boolean(document.querySelector('meta[name="session-authenticated"]'));
    if (!isAuthenticated) return;

    const path = window.location.pathname;
    const orderMatch = path.match(/\/(?:user\/orders|seller\/orders)\/(\d+)/);
    if (orderMatch && orderMatch[1]) {
        subscribePrivateChannelOnce(`order.${orderMatch[1]}`);
    }

    const shipmentMatch = path.match(/\/logistics\/shipments\/(\d+)/);
    if (shipmentMatch && shipmentMatch[1]) {
        subscribePrivateChannelOnce(`shipment.${shipmentMatch[1]}`);
    }
};

const subscribeGlobalRealtimeChannels = () => {
    if (!window.Echo) return;

    const userChannel = document.querySelector('meta[name="reverb-channel"]')?.getAttribute('content');
    const roleChannel = document.querySelector('meta[name="reverb-role-channel"]')?.getAttribute('content');

    if (userChannel) {
        const cleanUser = userChannel.trim();
        subscribePrivateChannelOnce(cleanUser);
        const userIdMatch = cleanUser.match(/^user\.(\d+)$/);
        if (userIdMatch && userIdMatch[1]) {
            const uid = userIdMatch[1];
            subscribePrivateChannelOnce(`chat.${uid}`);
            subscribePrivateChannelOnce(`cart.${uid}`);
            subscribePrivateChannelOnce(`support.user.${uid}`);
        }
    }

    if (roleChannel) {
        subscribePrivateChannelOnce(roleChannel.trim());
    }

    syncDynamicPageChannels();

    try {
        window.Echo.channel('marketplace').listen('.storkia.updated', (payload) => {
            window.dispatchEvent(new CustomEvent('storkia:ws-event', {
                detail: { ...(payload || {}), channel: 'marketplace' }
            }));
        });
    } catch (err) {}
};

window.StorkiaRealtime = {
    subscribePrivate: subscribePrivateChannelOnce,
    syncPageChannels: syncDynamicPageChannels,
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', subscribeGlobalRealtimeChannels, { once: true });
} else {
    subscribeGlobalRealtimeChannels();
}

window.addEventListener('popstate', syncDynamicPageChannels);