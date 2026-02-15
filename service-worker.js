const CACHE_NAME = 'pos-pwa-v1';
const RUNTIME_CACHE = 'pos-runtime-v1';
const OFFLINE_URL = './offline.html';
const PRECACHE_URLS = [
    './',
    './index.php',
    './login.php',
    './manifest.php',
    './offline.html',
    './assets/pwa/icon-192.png',
    './assets/pwa/icon-512.png',
    './assets/pwa/icon-maskable-512.png',
    './assets/pwa/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME && key !== RUNTIME_CACHE)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

function isStaticAsset(pathname) {
    return /\.(?:css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$/i.test(pathname);
}

async function networkFirst(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    try {
        const response = await fetch(request);
        if (response && response.status === 200 && request.method === 'GET') {
            cache.put(request, response.clone());
        }
        return response;
    } catch (_) {
        const cached = await cache.match(request);
        if (cached) return cached;
        if (request.mode === 'navigate') {
            const offline = await caches.match(OFFLINE_URL);
            if (offline) return offline;
        }
        throw _;
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response && response.status === 200 && request.method === 'GET') {
        const cache = await caches.open(RUNTIME_CACHE);
        cache.put(request, response.clone());
    }
    return response;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.includes('/api/')) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request));
        return;
    }

    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    event.respondWith(networkFirst(request));
});

