// api/driver_sw.js — offline cache for the driver page (api/driver_tracker.php). Scope: the api/ folder.
// • the page itself: network first, phone copy when there is no / slow internet
// • Leaflet (map library): phone copy first
// • map tiles: phone copy first (refreshed in the background after 14 days), max ~3000 tiles.
//   Only tiles the driver has actually looked at are kept — the same route every day means the same tiles,
//   so after the first trips the map works without internet. (No bulk pre-downloading: OSM tile policy.)
// GPS / trip API calls are never cached here (they are POSTs; the page keeps its own offline queue).

const PAGE = 'drv-page-v2', LIB = 'drv-lib-v1', TILES = 'drv-tiles-v1';
const KEEP = [PAGE, LIB, TILES];
const MAX_TILES = 3000, TILE_TTL = 14 * 864e5, PAGE_TIMEOUT = 6000;
let puts = 0;

self.addEventListener('install', (e) => {
  self.skipWaiting();
  e.waitUntil(caches.open(LIB).then((c) => c.addAll([
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
  ])).catch(() => {}));
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k.startsWith('drv-') && !KEEP.includes(k)) await caches.delete(k);
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (req.mode === 'navigate' && url.pathname.endsWith('/driver_tracker.php')) { e.respondWith(page(req)); return; }
  if (/(^|\.)tile\.openstreetmap\.org$/.test(url.hostname)) { e.respondWith(tile(req)); return; }
  if (url.hostname === 'cdn.jsdelivr.net' && url.pathname.startsWith('/npm/leaflet@1.9.4/')) { e.respondWith(lib(req)); return; }
});

async function page(req) {
  const cache = await caches.open(PAGE);
  try {
    const res = await Promise.race([
      fetch(req),
      new Promise((_, rej) => setTimeout(() => rej(new Error('slow')), PAGE_TIMEOUT)),
    ]);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(req);
    if (hit) return hit;
    return new Response('<h2 style="font-family:sans-serif">Internet nahi hai — page ek baar net ke saath kholna zaroori hai.</h2>',
      {status: 503, headers: {'Content-Type': 'text/html; charset=utf-8'}});
  }
}

async function lib(req) {
  const cache = await caches.open(LIB);
  const hit = await cache.match(req, {ignoreVary: true});
  if (hit) return hit;
  const res = await fetch(req);
  if (res.ok) cache.put(req, res.clone());
  return res;
}

async function tile(req) {
  const cache = await caches.open(TILES);
  const key = req.url;
  const hit = await cache.match(key);
  if (hit) {
    const at = +hit.headers.get('x-sw-at') || 0;
    if (Date.now() - at > TILE_TTL) refreshTile(cache, req, key).catch(() => {});   // stale: show it, update quietly
    return hit;
  }
  try {
    return await refreshTile(cache, req, key);
  } catch (err) {
    return new Response('', {status: 504});
  }
}

async function refreshTile(cache, req, key) {
  const res = await fetch(req);
  if (res.ok && res.type !== 'opaque') {
    const body = await res.clone().blob();
    await cache.put(key, new Response(body, {headers: {'Content-Type': res.headers.get('Content-Type') || 'image/png', 'x-sw-at': String(Date.now())}}));
    if (++puts % 50 === 0) trim(cache);
  }
  return res;
}

async function trim(cache) {
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - MAX_TILES; i++) await cache.delete(keys[i]);   // oldest first
}
