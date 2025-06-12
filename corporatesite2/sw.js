// キャッシュ名とバージョン
const CACHE_NAME = 'rc-house-cache-v1';

// キャッシュするリソース
const CACHE_URLS = [
    '/',
    '/index.html',
    '/css/style.css',
    '/css/responsive.css',
    '/css/form.css',
    '/js/main.js',
    '/js/performance.js',
    '/js/form-validation.js',
    '/fonts/NotoSansJP-Regular.woff2',
    '/images/hero/main-house.jpg',
    '/images/works/work01.jpg',
    '/images/works/work02.jpg',
    '/images/works/work03.jpg'
];

// Service Workerのインストール
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('キャッシュを開きました');
                return cache.addAll(CACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Service Workerのアクティベート
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('古いキャッシュを削除します:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// フェッチイベントの処理
self.addEventListener('fetch', event => {
    // 画像のキャッシュ戦略
    if (event.request.destination === 'image') {
        event.respondWith(
            caches.match(event.request)
                .then(response => {
                    if (response) {
                        return response;
                    }
                    return fetch(event.request)
                        .then(response => {
                            if (!response || response.status !== 200 || response.type !== 'basic') {
                                return response;
                            }
                            const responseToCache = response.clone();
                            caches.open(CACHE_NAME)
                                .then(cache => {
                                    cache.put(event.request, responseToCache);
                                });
                            return response;
                        });
                })
        );
        return;
    }

    // その他のリソースのキャッシュ戦略
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request)
                    .then(response => {
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        return response;
                    })
                    .catch(() => {
                        // オフライン時のフォールバック
                        if (event.request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                        return new Response('オフラインです。インターネット接続を確認してください。', {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: new Headers({
                                'Content-Type': 'text/plain'
                            })
                        });
                    });
            })
    );
});

// バックグラウンド同期
self.addEventListener('sync', event => {
    if (event.tag === 'sync-form') {
        event.waitUntil(syncFormData());
    }
});

// プッシュ通知
self.addEventListener('push', event => {
    const options = {
        body: event.data.text(),
        icon: '/images/icon-192x192.png',
        badge: '/images/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: '詳細を見る',
                icon: '/images/checkmark.png'
            },
            {
                action: 'close',
                title: '閉じる',
                icon: '/images/xmark.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('プッシュ通知', options)
    );
});

// 通知クリック時の処理
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
}); 