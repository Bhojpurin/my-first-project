// school/gate-sw.js — keeps the Auto Attendance (gate) page usable without internet.
// Registered by school/auto_attendance.php as gate-sw.js?page=<that page's path>.
//
// Only that one page and the files it loads are handled; every other school page goes
// straight to the network as if this worker did not exist. API calls are never cached:
// scans made offline go to the page's own offline queue and sync when the network is back.
'use strict';

const PAGE  = new URL(self.location.href).searchParams.get('page') || '';
const CACHE = 'sz-gate-v1';
const PAGE_KEY = new URL(PAGE || '/', self.location.origin).href;
const FLAG_KEY = new URL('/__sz_gate_device__', self.location.origin).href;   // set while this browser is a registered gate device

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil((async () => {
  for (const k of await caches.keys()) if (k.startsWith('sz-gate-') && k !== CACHE) await caches.delete(k);
  await self.clients.claim();
})()));

// The page tells us whether this browser is a registered gate device, and can drop the cache.
self.addEventListener('message', e => {
  const t = e.data && e.data.type;
  if (t === 'gate-clear') e.waitUntil(caches.delete(CACHE));
  else if (t === 'gate-device') e.waitUntil(caches.open(CACHE).then(c => e.data.on ? c.put(FLAG_KEY, new Response('1')) : c.delete(FLAG_KEY)));
});

async function pageNetworkFirst(req) {
  const cache = await caches.open(CACHE);
  try {
    const res = await fetch(req, {cache: 'no-store'});
    if (res.ok && !res.redirected && (res.headers.get('content-type') || '').includes('text/html')) {
      // Store it only if it really is the gate page (not a login form served at the same URL).
      const html = await res.clone().text();
      if (html.includes('data-sz-gate-page')) {
        await cache.put(PAGE_KEY, new Response(html, {headers: {'Content-Type': 'text/html; charset=utf-8'}}));
        return res;
      }
    }
    // Login redirect / error page. A registered gate device keeps its scanner (it scans with its
    // device token); any other browser gets the login page as usual, so logging out still works.
    if (await cache.match(FLAG_KEY)) return (await cache.match(PAGE_KEY)) || res;
    return res;
  } catch (err) {
    return (await cache.match(PAGE_KEY)) || new Response(
      '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
      '<title>Offline</title><body style="font-family:sans-serif;padding:40px;text-align:center">' +
      '<h2>No internet</h2><p>Open this page once while online on this device, then it will also open offline.</p>',
      {status: 503, headers: {'Content-Type': 'text/html; charset=utf-8'}});
  }
}

async function assetStaleWhileRevalidate(event) {
  const req = event.request;
  // Only for files requested by the gate page itself.
  const client = event.clientId ? await self.clients.get(event.clientId) : null;
  if (!client || new URL(client.url).pathname !== PAGE) return fetch(req);
  const cache = await caches.open(CACHE);
  const hit = await cache.match(req);
  const net = fetch(req).then(res => {
    if (res.ok && (res.type === 'basic' || res.type === 'cors')) cache.put(req, res.clone());
    return res;
  }).catch(() => hit || Response.error());
  if (hit) { event.waitUntil(net.catch(() => {})); return hit; }
  return net;
}

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET' || !PAGE) return;
  const url = new URL(req.url);
  if (req.mode === 'navigate') {
    if (url.origin === self.location.origin && url.pathname === PAGE) event.respondWith(pageNetworkFirst(req));
    return;
  }
  if (url.pathname.includes('/api/')) return;   // live data and photos: always from the network
  if (!['script', 'style', 'font', 'image'].includes(req.destination)) return;
  event.respondWith(assetStaleWhileRevalidate(event));
});
