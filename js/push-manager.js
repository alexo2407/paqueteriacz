/**
 * push-manager.js
 * Gestiona el registro del Service Worker, suscripción Web Push y el toggle
 * de activación/desactivación de notificaciones push en el navegador.
 *
 * Uso: incluir en el layout/header después de que el DOM esté listo.
 *   <script src="<?= RUTA_URL ?>js/push-manager.js" defer></script>
 *
 * Requiere en el HTML un botón con id="btnTogglePush":
 *   <button id="btnTogglePush" type="button">...</button>
 */

(function () {
    'use strict';

    // ─── Configuración ───────────────────────────────────────────────────────
    // La URL base se inyecta desde PHP en un meta tag o variable global:
    // <meta name="base-url" content="<?= RUTA_URL ?>">
    const BASE_URL = (function () {
        const meta = document.querySelector('meta[name="base-url"]');
        return meta ? meta.getAttribute('content').replace(/\/$/, '') : '';
    })();

    const SW_PATH = BASE_URL + '/sw.js';
    const SUBSCRIBE_URL = BASE_URL + '/push/subscribe';
    const UNSUBSCRIBE_URL = BASE_URL + '/push/unsubscribe';
    const VAPID_KEY_URL = BASE_URL + '/push/vapid-key';

    // ─── Utilidades ──────────────────────────────────────────────────────────

    /**
     * Convierte la clave pública VAPID de base64url a Uint8Array
     * (requerido por PushManager.subscribe)
     */
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    // ─── Registro del Service Worker ─────────────────────────────────────────

    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[Push] Service Workers no soportados en este navegador.');
            return null;
        }
        try {
            return await navigator.serviceWorker.register(SW_PATH, { scope: BASE_URL + '/' });
        } catch (err) {
            console.error('[Push] Error al registrar SW:', err);
            return null;
        }
    }

    // ─── Suscripción ─────────────────────────────────────────────────────────

    async function getVapidPublicKey() {
        const res = await fetch(VAPID_KEY_URL);
        const json = await res.json();
        return json.publicKey;
    }

    async function subscribeToPush(registration) {
        const publicKey = await getVapidPublicKey();

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        // Enviar suscripción al backend
        const res = await fetch(SUBSCRIBE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ...subscription.toJSON(),
                contexto: 'logistica',
            }),
        });

        const result = await res.json();
        return result.success === true;
    }

    async function unsubscribeFromPush() {
        const reg = await navigator.serviceWorker.getRegistration(SW_PATH);
        if (!reg) return true;

        const sub = await reg.pushManager.getSubscription();
        if (!sub) return true;

        const endpoint = sub.endpoint;

        // Desuscribir en el navegador
        await sub.unsubscribe();

        // Notificar al backend
        await fetch(UNSUBSCRIBE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint }),
        });

        return true;
    }

    // ─── Estado actual ────────────────────────────────────────────────────────

    async function getCurrentPermission() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission; // 'default' | 'granted' | 'denied'
    }

    async function isSubscribed() {
        if (!('serviceWorker' in navigator)) return false;
        const reg = await navigator.serviceWorker.getRegistration(SW_PATH);
        if (!reg) return false;
        const sub = await reg.pushManager.getSubscription();
        return !!sub;
    }

    // ─── UI: actualizar botón toggle ─────────────────────────────────────────

    async function updateToggleUI(btn) {
        if (!btn) return;

        const permission = await getCurrentPermission();
        const subscribed = await isSubscribed();

        if (permission === 'denied') {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-bell-slash me-1"></i> Push bloqueado';
            btn.title = 'Permite las notificaciones en la configuración del navegador';
            btn.className = btn.className.replace(/btn-\S+/g, '') + ' btn btn-sm btn-secondary';
            return;
        }

        if (subscribed && permission === 'granted') {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i> Push activado';
            btn.title = 'Click para desactivar notificaciones push';
            btn.className = btn.className.replace(/btn-\S+/g, '') + ' btn btn-sm btn-success';
            btn.dataset.pushActive = '1';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bell me-1"></i> Activar push';
            btn.title = 'Click para activar notificaciones push';
            btn.className = btn.className.replace(/btn-\S+/g, '') + ' btn btn-sm btn-outline-primary';
            btn.dataset.pushActive = '0';
        }
    }

    // ─── Handler del botón toggle ─────────────────────────────────────────────

    async function handleToggle(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

        try {
            if (btn.dataset.pushActive === '1') {
                // Desactivar
                await unsubscribeFromPush();
            } else {
                // Activar: pedir permiso primero
                if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                    alert('Tu navegador no soporta notificaciones push.');
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('Permiso denegado. Permite las notificaciones en la configuración del navegador.');
                    return;
                }

                const reg = await registerServiceWorker();
                if (!reg) {
                    alert('No se pudo registrar el Service Worker.');
                    return;
                }

                await navigator.serviceWorker.ready;
                const success = await subscribeToPush(reg);

                if (!success) {
                    alert('Error al guardar suscripción en el servidor.');
                }
            }
        } catch (err) {
            console.error('[Push] Error en toggle:', err);
            alert('Error inesperado al gestionar notificaciones push.');
        } finally {
            await updateToggleUI(btn);
        }
    }

    // ─── Inicialización ───────────────────────────────────────────────────────

    async function init() {
        // Registrar SW siempre (para recibir pushes en background)
        await registerServiceWorker();

        const btn = document.getElementById('btnTogglePush');
        await updateToggleUI(btn);

        if (btn) {
            btn.addEventListener('click', () => handleToggle(btn));
        }
    }

    // Iniciar después del DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
