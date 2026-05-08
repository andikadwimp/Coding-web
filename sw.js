const CACHE = 'app-v60';

// Path yg BOLEH di-cache (static assets aja)
const CACHEABLE_PATTERNS = [
  /\.(png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)$/i,
  /^\/icon-\d+\.png$/,
  /^\/asset\/uploads\//,
  /^\/img\//,
];

// Path yg JANGAN di-cache
const NO_CACHE_PATTERNS = [
  /\/api\//,
  /\/theme\.php/,
  /\/manifest\.php/,
  /\.php(\?|$)/,
];

function isCacheable(url) {
  const path = new URL(url).pathname;
  for (const p of NO_CACHE_PATTERNS) {
    if (p.test(path)) return false;
  }
  for (const p of CACHEABLE_PATTERNS) {
    if (p.test(path)) return true;
  }
  return false;
}

self.addEventListener('install', e => { self.skipWaiting(); });

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE).map(k => caches.delete(k))
    )).then(() => clients.claim())
  );
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;

  // PHP + API: always network
  if (!isCacheable(e.request.url)) {
    e.respondWith(fetch(e.request));
    return;
  }

  // Static: network-first, fallback cache
  e.respondWith(
    fetch(e.request)
      .then(res => {
        if (res && res.status === 200 && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});

self.addEventListener('message', e => {
  if (e.data && e.data.type === 'CLEAR_CACHE') {
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
  }
});

self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; }
  catch(err) { data = { title: 'Notifikasi', body: e.data ? e.data.text() : '' }; }
  const title = data.title || 'Notifikasi Baru';
  const options = {
    body: data.body || '',
    icon: data.icon || '/icon-192.png',
    badge: data.badge || '/icon-192.png',
    image: data.image || undefined,
    data: { url: data.url || '/dashboard.php' },
    vibrate: [200, 100, 200],
    tag: data.tag || 'notif-' + Date.now(),
    requireInteraction: !!data.requireInteraction
  };
  e.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/dashboard.php';
  e.waitUntil(
    clients.matchAll({ type: 'window' }).then(list => {
      for (const c of list) { if ('focus' in c) return c.focus(); }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
