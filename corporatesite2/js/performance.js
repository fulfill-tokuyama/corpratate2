// パフォーマンス監視
class PerformanceMonitor {
    constructor() {
        this.metrics = {};
        this.observers = [];
    }

    // パフォーマンスメトリクスの収集
    collectMetrics() {
        // ページロード時間
        const navigationTiming = performance.getEntriesByType('navigation')[0];
        this.metrics.pageLoad = {
            total: navigationTiming.loadEventEnd - navigationTiming.navigationStart,
            dns: navigationTiming.domainLookupEnd - navigationTiming.domainLookupStart,
            tcp: navigationTiming.connectEnd - navigationTiming.connectStart,
            request: navigationTiming.responseEnd - navigationTiming.requestStart,
            dom: navigationTiming.domComplete - navigationTiming.domLoading
        };

        // リソース読み込み時間
        const resources = performance.getEntriesByType('resource');
        this.metrics.resources = resources.map(resource => ({
            name: resource.name,
            duration: resource.duration,
            size: resource.transferSize
        }));

        // レイアウトシフト
        const layoutShifts = performance.getEntriesByType('layout-shift');
        this.metrics.layoutShifts = layoutShifts.map(shift => ({
            value: shift.value,
            startTime: shift.startTime
        }));

        // 最長タスク
        const longTasks = performance.getEntriesByType('longtask');
        this.metrics.longTasks = longTasks.map(task => ({
            duration: task.duration,
            startTime: task.startTime
        }));
    }

    // パフォーマンス監視の開始
    startMonitoring() {
        // レイアウトシフトの監視
        const layoutShiftObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                this.metrics.layoutShifts.push({
                    value: entry.value,
                    startTime: entry.startTime
                });
            }
        });
        layoutShiftObserver.observe({ entryTypes: ['layout-shift'] });
        this.observers.push(layoutShiftObserver);

        // 最長タスクの監視
        const longTaskObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                this.metrics.longTasks.push({
                    duration: entry.duration,
                    startTime: entry.startTime
                });
            }
        });
        longTaskObserver.observe({ entryTypes: ['longtask'] });
        this.observers.push(longTaskObserver);

        // リソース読み込みの監視
        const resourceObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                this.metrics.resources.push({
                    name: entry.name,
                    duration: entry.duration,
                    size: entry.transferSize
                });
            }
        });
        resourceObserver.observe({ entryTypes: ['resource'] });
        this.observers.push(resourceObserver);
    }

    // パフォーマンス監視の停止
    stopMonitoring() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers = [];
    }

    // パフォーマンスレポートの生成
    generateReport() {
        this.collectMetrics();
        return {
            timestamp: new Date().toISOString(),
            metrics: this.metrics,
            summary: {
                pageLoadTime: this.metrics.pageLoad.total,
                resourceCount: this.metrics.resources.length,
                layoutShiftScore: this.metrics.layoutShifts.reduce((sum, shift) => sum + shift.value, 0),
                longTaskCount: this.metrics.longTasks.length
            }
        };
    }

    // パフォーマンス改善の提案
    suggestImprovements() {
        const suggestions = [];

        // ページロード時間の改善提案
        if (this.metrics.pageLoad.total > 3000) {
            suggestions.push({
                type: 'pageLoad',
                priority: 'high',
                message: 'ページロード時間が3秒を超えています。画像の最適化やリソースの遅延読み込みを検討してください。'
            });
        }

        // レイアウトシフトの改善提案
        const layoutShiftScore = this.metrics.layoutShifts.reduce((sum, shift) => sum + shift.value, 0);
        if (layoutShiftScore > 0.25) {
            suggestions.push({
                type: 'layoutShift',
                priority: 'high',
                message: 'レイアウトシフトが大きすぎます。画像サイズの指定や動的コンテンツの事前確保を検討してください。'
            });
        }

        // 最長タスクの改善提案
        const longTasks = this.metrics.longTasks.filter(task => task.duration > 50);
        if (longTasks.length > 0) {
            suggestions.push({
                type: 'longTask',
                priority: 'medium',
                message: `${longTasks.length}個の長時間タスクが検出されました。JavaScriptの最適化を検討してください。`
            });
        }

        // リソースサイズの改善提案
        const largeResources = this.metrics.resources.filter(resource => resource.size > 500000);
        if (largeResources.length > 0) {
            suggestions.push({
                type: 'resourceSize',
                priority: 'medium',
                message: `${largeResources.length}個の大きなリソースが検出されました。圧縮や最適化を検討してください。`
            });
        }

        return suggestions;
    }
}

// パフォーマンスモニターの初期化と開始
const performanceMonitor = new PerformanceMonitor();
performanceMonitor.startMonitoring();

// ページロード完了時にレポートを生成
window.addEventListener('load', () => {
    setTimeout(() => {
        const report = performanceMonitor.generateReport();
        const suggestions = performanceMonitor.suggestImprovements();
        
        // 開発環境でのみコンソールに出力
        if (process.env.NODE_ENV === 'development') {
            console.log('Performance Report:', report);
            console.log('Improvement Suggestions:', suggestions);
        }
        
        // 本番環境ではサーバーに送信
        if (process.env.NODE_ENV === 'production') {
            fetch('/api/performance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    report,
                    suggestions
                })
            });
        }
    }, 1000);
});

// パフォーマンス最適化
class PerformanceOptimizer {
    constructor() {
        this.init();
    }

    init() {
        this.setupIntersectionObserver();
        this.setupResourceHints();
        this.setupServiceWorker();
        this.setupImageOptimization();
        this.setupScrollOptimization();
    }

    // Intersection Observerの設定
    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    
                    // 画像の遅延読み込み
                    if (target.tagName === 'IMG') {
                        this.loadImage(target);
                    }
                    
                    // アニメーション要素の表示
                    if (target.classList.contains('animate-on-scroll')) {
                        target.classList.add('visible');
                    }
                }
            });
        }, options);

        // 監視対象の要素を登録
        document.querySelectorAll('img[data-src], .animate-on-scroll').forEach(el => {
            observer.observe(el);
        });
    }

    // リソースヒントの設定
    setupResourceHints() {
        // 重要なリソースの事前読み込み
        const preloadLinks = [
            { rel: 'preload', href: '/fonts/NotoSansJP-Regular.woff2', as: 'font', type: 'font/woff2', crossorigin: true },
            { rel: 'preload', href: '/css/style.css', as: 'style' },
            { rel: 'preload', href: '/js/main.js', as: 'script' }
        ];

        preloadLinks.forEach(link => {
            const linkElement = document.createElement('link');
            Object.entries(link).forEach(([key, value]) => {
                linkElement.setAttribute(key, value);
            });
            document.head.appendChild(linkElement);
        });
    }

    // Service Workerの設定
    async setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('ServiceWorker registration successful');
            } catch (error) {
                console.error('ServiceWorker registration failed:', error);
            }
        }
    }

    // 画像最適化
    setupImageOptimization() {
        // WebP対応の確認
        const supportsWebP = () => {
            const elem = document.createElement('canvas');
            if (elem.getContext && elem.getContext('2d')) {
                return elem.toDataURL('image/webp').indexOf('data:image/webp') === 0;
            }
            return false;
        };

        // 画像の遅延読み込み
        this.loadImage = (img) => {
            if (img.dataset.src) {
                const src = supportsWebP() && img.dataset.webp ? img.dataset.webp : img.dataset.src;
                img.src = src;
                img.classList.add('loaded');
            }
        };
    }

    // スクロール最適化
    setupScrollOptimization() {
        let ticking = false;
        let lastScrollY = 0;

        const updateScroll = () => {
            const currentScrollY = window.scrollY;
            
            // スクロール方向の検出
            const direction = currentScrollY > lastScrollY ? 'down' : 'up';
            
            // ヘッダーの表示/非表示
            const header = document.querySelector('.header');
            if (header) {
                if (direction === 'down' && currentScrollY > 100) {
                    header.classList.add('header-hidden');
                } else {
                    header.classList.remove('header-hidden');
                }
            }

            // スクロールアニメーション
            document.querySelectorAll('.animate-on-scroll').forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementBottom = element.getBoundingClientRect().bottom;
                
                if (elementTop < window.innerHeight && elementBottom > 0) {
                    element.classList.add('visible');
                }
            });

            lastScrollY = currentScrollY;
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(updateScroll);
                ticking = true;
            }
        }, { passive: true });
    }
}

// パフォーマンスメトリクスの計測
class PerformanceMetrics {
    constructor() {
        this.metrics = {};
        this.init();
    }

    init() {
        this.measureFirstContentfulPaint();
        this.measureLargestContentfulPaint();
        this.measureTimeToInteractive();
        this.measureCumulativeLayoutShift();
    }

    measureFirstContentfulPaint() {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                this.metrics.fcp = entry.startTime;
                console.log('FCP:', entry.startTime);
            });
        });
        observer.observe({ entryTypes: ['paint'] });
    }

    measureLargestContentfulPaint() {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                this.metrics.lcp = entry.startTime;
                console.log('LCP:', entry.startTime);
            });
        });
        observer.observe({ entryTypes: ['largest-contentful-paint'] });
    }

    measureTimeToInteractive() {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                this.metrics.tti = entry.duration;
                console.log('TTI:', entry.duration);
            });
        });
        observer.observe({ entryTypes: ['longtask'] });
    }

    measureCumulativeLayoutShift() {
        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                this.metrics.cls = entry.value;
                console.log('CLS:', entry.value);
            });
        });
        observer.observe({ entryTypes: ['layout-shift'] });
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    new PerformanceOptimizer();
    new PerformanceMetrics();
}); 