/**
 * Service Worker for 個別支援連絡帳システム PWA
 */

const CACHE_NAME = 'kobetu-cache-v1';
const OFFLINE_URL = '/offline.html';

// キャッシュするリソース
const STATIC_CACHE_URLS = [
    '/',
    '/login.php',
    '/offline.html',
    '/assets/css/google-design.css',
    '/assets/icons/icon-192x192.svg',
    '/assets/icons/icon-512x512.svg',
    '/manifest.json'
];

// インストール時
self.addEventListener('install', (event) => {
    console.log('[ServiceWorker] Install');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[ServiceWorker] Caching static assets');
                return cache.addAll(STATIC_CACHE_URLS);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// アクティベート時（古いキャッシュを削除）
self.addEventListener('activate', (event) => {
    console.log('[ServiceWorker] Activate');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[ServiceWorker] Removing old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// フェッチ時
self.addEventListener('fetch', (event) => {
    // APIリクエストはキャッシュしない（常にネットワーク優先）
    if (event.request.url.includes('/api/') ||
        event.request.url.includes('_api.php') ||
        event.request.url.includes('_save.php') ||
        event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // 正常なレスポンスの場合、キャッシュに保存
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // オフライン時はキャッシュから取得
                return caches.match(event.request)
                    .then((response) => {
                        if (response) {
                            return response;
                        }
                        // HTMLリクエストの場合はオフラインページを表示
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match(OFFLINE_URL);
                        }
                    });
            })
    );
});

// プッシュ通知（将来対応用）
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body || '新しい通知があります',
            icon: '/assets/icons/icon-192x192.png',
            badge: '/assets/icons/icon-72x72.png',
            vibrate: [100, 50, 100],
            data: {
                url: data.url || '/'
            }
        };
        event.waitUntil(
            self.registration.showNotification(data.title || '連絡帳', options)
        );
    }
});

// 通知クリック時
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/')
    );
});
