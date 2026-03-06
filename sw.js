// sw.js — Service Worker para Web Push de PaqueteriaCZ
// Ubicación: raíz pública del proyecto (mismo nivel que index.php)

const CACHE_NAME = 'paqueteriacz-v1';

// ─── Evento: push recibido ────────────────────────────────────────────────────
self.addEventListener('push', function (event) {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {
            title: 'PaqueteriaCZ',
            body:  event.data ? event.data.text() : 'Nueva notificación',
            url:   '/',
        };
    }

    const title   = data.title  || 'PaqueteriaCZ';
    const options = {
        body:    data.body   || '',
        icon:    data.icon   || '/android-chrome-192x192.png',
        badge:   data.badge  || '/favicon-32x32.png',
        tag:     data.tag    || 'paqueteriacz',
        renotify: true,
        requireInteraction: false,
        data: {
            url: data.url || (data.data && data.data.url) || '/',
        },
        actions: [
            { action: 'open', title: 'Ver detalles' },
            { action: 'close', title: 'Cerrar' },
        ],
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ─── Evento: click en la notificación ────────────────────────────────────────
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    if (event.action === 'close') return;

    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Si ya hay una ventana abierta con la misma URL, enfocarla
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // Si no, abrir nueva ventana
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// ─── Evento: push subscription changed (renovación automática) ───────────────
self.addEventListener('pushsubscriptionchange', function (event) {
    event.waitUntil(
        self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: event.oldSubscription
                ? event.oldSubscription.options.applicationServerKey
                : null
        }).then(function (subscription) {
            // Notificar al backend de la nueva suscripción
            return fetch('/push/subscribe', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(subscription.toJSON()),
            });
        }).catch(function (e) {
            console.warn('[SW] pushsubscriptionchange error:', e);
        })
    );
});
