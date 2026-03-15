/**
 * Service Worker for KIDURI - 個別支援連絡帳システム PWA
 */

const CACHE_VERSION = 'kiduri-cache-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const OFFLINE_URL = '/offline.html';

// 静的キャッシュするリソース
const STATIC_CACHE_URLS = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/assets/icons/icon-192x192.svg',
  '/assets/icons/icon-512x512.svg',
];

// キャッシュ対象の静的アセット拡張子
const STATIC_EXTENSIONS = [
  '.css', '.js', '.woff', '.woff2', '.ttf', '.otf',
  '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.webp',
];

// APIパスのパターン
const API_PATTERNS = ['/api/', '/sanctum/', '/broadcasting/'];

/**
 * インストール時 - 静的アセットをキャッシュ
 */
self.addEventListener('install', (event) => {
  console.log('[ServiceWorker] Install');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[ServiceWorker] Caching static assets');
        return cache.addAll(STATIC_CACHE_URLS);
      })
      .then(() => self.skipWaiting())
  );
});

/**
 * アクティベート時 - 古いキャッシュを削除
 */
self.addEventListener('activate', (event) => {
  console.log('[ServiceWorker] Activate');
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name !== STATIC_CACHE && name !== DYNAMIC_CACHE)
            .map((name) => {
              console.log('[ServiceWorker] Removing old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

/**
 * URLが静的アセットかどうか判定
 */
function isStaticAsset(url) {
  const pathname = new URL(url).pathname;
  return STATIC_EXTENSIONS.some((ext) => pathname.endsWith(ext)) ||
    pathname.startsWith('/_next/static/');
}

/**
 * URLがAPIリクエストかどうか判定
 */
function isApiRequest(url) {
  const pathname = new URL(url).pathname;
  return API_PATTERNS.some((pattern) => pathname.startsWith(pattern));
}

/**
 * フェッチ時 - リクエストに応じた戦略を適用
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;

  // POST等の非GETリクエストはキャッシュしない
  if (request.method !== 'GET') {
    return;
  }

  // APIリクエスト: ネットワーク優先
  if (isApiRequest(request.url)) {
    event.respondWith(networkFirst(request));
    return;
  }

  // 静的アセット: キャッシュ優先
  if (isStaticAsset(request.url)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // HTMLページ: ネットワーク優先（オフラインフォールバック付き）
  event.respondWith(networkFirstWithOfflineFallback(request));
});

/**
 * キャッシュ優先戦略（静的アセット用）
 */
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    // バックグラウンドで更新
    updateCache(request);
    return cached;
  }

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('', { status: 503, statusText: 'Service Unavailable' });
  }
}

/**
 * ネットワーク優先戦略（API用）
 */
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    return new Response(JSON.stringify({ error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

/**
 * ネットワーク優先 + オフラインフォールバック（HTMLページ用）
 */
async function networkFirstWithOfflineFallback(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }

    // HTMLリクエストの場合はオフラインページを表示
    const accept = request.headers.get('accept') || '';
    if (accept.includes('text/html')) {
      const offlinePage = await caches.match(OFFLINE_URL);
      if (offlinePage) {
        return offlinePage;
      }
    }

    return new Response('', { status: 503, statusText: 'Service Unavailable' });
  }
}

/**
 * バックグラウンドでキャッシュを更新
 */
async function updateCache(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response);
    }
  } catch {
    // バックグラウンド更新の失敗は無視
  }
}

/**
 * 定期的なキャッシュクリーンアップ
 */
async function cleanupDynamicCache() {
  const cache = await caches.open(DYNAMIC_CACHE);
  const keys = await cache.keys();
  const MAX_DYNAMIC_ENTRIES = 50;

  if (keys.length > MAX_DYNAMIC_ENTRIES) {
    const toDelete = keys.slice(0, keys.length - MAX_DYNAMIC_ENTRIES);
    await Promise.all(toDelete.map((key) => cache.delete(key)));
    console.log('[ServiceWorker] Cleaned up', toDelete.length, 'dynamic cache entries');
  }
}

/**
 * プッシュ通知
 */
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body || '新しい通知があります',
      icon: '/assets/icons/icon-192x192.svg',
      badge: '/assets/icons/icon-72x72.svg',
      vibrate: [100, 50, 100],
      data: {
        url: data.url || '/',
      },
    };
    event.waitUntil(
      self.registration.showNotification(data.title || 'KIDURI', options)
    );
  }
});

/**
 * 通知クリック時
 */
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url || '/')
  );
});

/**
 * メッセージ受信（キャッシュクリア等）
 */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((names) => Promise.all(names.map((name) => caches.delete(name))))
    );
  }
  if (event.data && event.data.type === 'CLEANUP') {
    event.waitUntil(cleanupDynamicCache());
  }
});
