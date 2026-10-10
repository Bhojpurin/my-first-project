// student/sw.js — Service worker for background bus-proximity push alerts.
// Scope is /student/ by default (its own folder) — enough to receive pushes
// for the student portal even while the tab/app is closed or the phone is locked.

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) {}

  const title = data.title || 'Bus Alert';
  const options = {
    body: data.body || 'Your bus is nearby.',
    vibrate: [200, 100, 200, 100, 200, 100, 400],
    requireInteraction: true,
    tag: 'bus-proximity',
    renotify: true,
    data: { url: data.url || '/student/index.php#bus' },
  };
  if (data.icon) options.icon = data.icon;
  if (data.icon) options.badge = data.icon;

  event.waitUntil(self.registration.showNotification(title, options));
});

// Tapping the notification focuses an already-open STUDENT PORTAL tab if there is one,
// otherwise opens the Bus tab fresh. (Other tabs of this site — e.g. an admin panel the
// family member has open — are never navigated away.)
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/student/index.php#bus';

  event.waitUntil((async () => {
    const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const portal = clientList.find((c) => c.url.indexOf('/student/') !== -1);
    if (portal) {
      try { await portal.focus(); } catch (e) {}
      // Same page, only the #bus part differs, so this switches tab without a full reload.
      try { if ('navigate' in portal) await portal.navigate(targetUrl); } catch (e) {}
      return;
    }
    return self.clients.openWindow(targetUrl);
  })());
});

// The browser can silently replace a push subscription (key rotation, storage clean-up).
// Without this, the server keeps sending to the dead endpoint and alerts stop with no error.
// Re-subscribe and tell the server; if the student's login has expired the request is refused,
// and the portal repairs the subscription the next time the Bus tab is opened.
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    try {
      const old = event.oldSubscription;
      const key = old && old.options && old.options.applicationServerKey;
      let sub = event.newSubscription || null;
      if (!sub && key) {
        sub = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
      }
      if (!sub) return;
      const j = sub.toJSON();
      const fd = new FormData();
      fd.append('action', 'subscribe_push');
      fd.append('endpoint', j.endpoint);
      fd.append('p256dh', j.keys.p256dh);
      fd.append('auth', j.keys.auth);
      await fetch(new URL('../api/student_bus.php', self.registration.scope).href,
                  { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch (e) { /* the portal re-syncs on next visit */ }
  })());
});