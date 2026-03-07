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
            // ⚠️ NO pasar scope explícito: Chrome lo infiere del path del sw.js.
            // Si el scope fuera más amplio que la ruta del sw.js, Chrome lo bloquea.
            const reg = await navigator.serviceWorker.register(SW_PATH);
            console.log('[Push] SW registrado. Scope:', reg.scope, '| State:', reg.active ? reg.active.state : 'installing');
            return reg;
        } catch (err) {
            console.error('[Push] Error al registrar SW:', err.message, err);
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
        const regs = await navigator.serviceWorker.getRegistrations();
        for (const reg of regs) {
            const sub = await reg.pushManager.getSubscription();
            if (!sub) continue;

            const endpoint = sub.endpoint;
            await sub.unsubscribe();

            await fetch(UNSUBSCRIBE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint }),
            });
        }
        return true;
    }

    // ─── Estado actual ────────────────────────────────────────────────────────

    async function getCurrentPermission() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission; // 'default' | 'granted' | 'denied'
    }

    async function isSubscribed() {
        if (!('serviceWorker' in navigator)) return false;
        // Buscar cualquier SW registrado bajo el scope del subdirectorio
        const regs = await navigator.serviceWorker.getRegistrations();
        for (const reg of regs) {
            const sub = await reg.pushManager.getSubscription();
            if (sub) return true;
        }
        return false;
    }

    // ─── UI: actualizar botón toggle ─────────────────────────────────────────

    async function updateToggleUI(btn) {
        if (!btn) return;

        const permission = await getCurrentPermission();
        const subscribed = await isSubscribed();
        const icon = btn.querySelector('i');
        if (!icon) return;

        if (permission === 'denied') {
            btn.disabled = true;
            icon.className = 'bi bi-bell-slash';
            icon.style.color = 'rgba(255,100,100,0.75)';
            btn.title = 'Push bloqueado — permítelo en la configuración del navegador';
            btn.dataset.pushActive = '0';
            return;
        }

        if (subscribed && permission === 'granted') {
            btn.disabled = false;
            icon.className = 'bi bi-bell-fill';
            icon.style.color = '#4ade80';   /* verde suave */
            btn.title = 'Push activado — clic para desactivar';
            btn.dataset.pushActive = '1';
        } else {
            btn.disabled = false;
            icon.className = 'bi bi-bell-slash';
            icon.style.color = 'rgba(255,255,255,0.55)';
            btn.title = 'Activar notificaciones push del navegador';
            btn.dataset.pushActive = '0';
        }
    }

    // ─── Handler del botón toggle ─────────────────────────────────────────────

    async function handleToggle(btn) {
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-arrow-repeat spin-icon';
            icon.style.color = 'rgba(255,255,255,0.55)';
        }

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
