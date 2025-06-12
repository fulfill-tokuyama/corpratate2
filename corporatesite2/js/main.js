// ハンバーガーメニューの制御
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const nav = document.querySelector('.nav');
    const navList = document.querySelector('.nav-list');
    
    if (hamburger && nav && navList) {
        hamburger.addEventListener('click', function() {
            nav.classList.toggle('active');
            hamburger.classList.toggle('active');
            navList.classList.toggle('active');
        });
    }
    
    // スクロール時のヘッダー制御
    let lastScroll = 0;
    const header = document.querySelector('.header');
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll <= 0) {
            header.classList.remove('scroll-up');
            return;
        }
        
        if (currentScroll > lastScroll && !header.classList.contains('scroll-down')) {
            // 下スクロール
            header.classList.remove('scroll-up');
            header.classList.add('scroll-down');
        } else if (currentScroll < lastScroll && header.classList.contains('scroll-down')) {
            // 上スクロール
            header.classList.remove('scroll-down');
            header.classList.add('scroll-up');
        }
        lastScroll = currentScroll;
    });
    
    // スムーズスクロール
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
                
                // モバイルメニューが開いている場合は閉じる
                if (nav.classList.contains('active')) {
                    nav.classList.remove('active');
                    hamburger.classList.remove('active');
                    navList.classList.remove('active');
                }
            }
        });
    });

    // スクロールアニメーション
    const animatedElements = document.querySelectorAll('.philosophy-item, .achievement-item, .staff-item, .awards');
    
    // スクロールアニメーションのクラスを追加
    animatedElements.forEach(element => {
        element.classList.add('scroll-animation');
    });
    
    // Intersection Observerの設定
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    // 各要素の監視を開始
    animatedElements.forEach(element => {
        observer.observe(element);
    });

    // モーダル制御
    const staffModal = document.getElementById('staffModal');
    const achievementModal = document.getElementById('achievementModal');
    const modalCloseButtons = document.querySelectorAll('.modal-close');

    // スタッフデータ
    const staffData = {
        'staff1': {
            name: '山田 和夫',
            position: '代表取締役',
            image: '../images/about/staff1.svg',
            career: '大手建設会社で20年以上の経験を積み、2010年に独立。RC住宅の設計・施工に特化した会社を設立。',
            qualifications: '一級建築士、一級施工管理技士',
            message: '「和」の精神を大切に、お客様の想いを形に。RC住宅の可能性を追求し、より良い住まいづくりを目指します。'
        },
        'staff2': {
            name: '佐藤 誠',
            position: '設計部長',
            image: '../images/about/staff2.svg',
            career: '建築設計事務所で10年以上の経験を積み、2015年に株式会社みらい不動産に参画。',
            qualifications: '一級建築士、インテリアコーディネーター',
            message: '機能性とデザインの両立を心がけ、お客様のライフスタイルに合わせた最適な設計を提供します。'
        },
        'staff3': {
            name: '田中 健一',
            position: '施工管理部長',
            image: '../images/about/staff3.svg',
            career: '大手建設会社で15年以上の施工管理経験を積み、2018年に株式会社みらい不動産に参画。',
            qualifications: '一級施工管理技士、建築施工管理技士',
            message: '安全・確実な施工を第一に、品質の高いRC住宅づくりを実現します。'
        }
    };

    // 実績データ
    const achievementData = {
        'achievement1': {
            title: 'RC住宅デザインコンペティション',
            subtitle: '最優秀賞',
            image: '../images/about/achievement1.jpg',
            year: '2022年',
            organizer: '日本RC住宅協会',
            description: '和風デザインとRC構造の融合を評価され、最優秀賞を受賞。伝統的な意匠と現代的な技術の調和を実現した設計が高く評価されました。'
        },
        'achievement2': {
            title: '住まいの環境デザイン賞',
            subtitle: '優秀賞',
            image: '../images/about/achievement2.jpg',
            year: '2021年',
            organizer: '環境デザイン協会',
            description: '自然との調和を重視した設計と、環境配慮型のRC住宅として評価され、優秀賞を受賞。'
        }
    };

    // モーダルを開く
    function openModal(modal, data) {
        const title = modal.querySelector('.modal-title');
        const subtitle = modal.querySelector('.modal-subtitle');
        const image = modal.querySelector('.modal-image');
        const infoItems = modal.querySelectorAll('.modal-info-item');

        title.textContent = data.name || data.title;
        subtitle.textContent = data.position || data.subtitle;
        image.src = data.image;
        image.alt = data.name || data.title;

        if (modal === staffModal) {
            infoItems[0].querySelector('.modal-info-value').textContent = data.career;
            infoItems[1].querySelector('.modal-info-value').textContent = data.qualifications;
            infoItems[2].querySelector('.modal-description').textContent = data.message;
        } else {
            infoItems[0].querySelector('.modal-info-value').textContent = data.year;
            infoItems[1].querySelector('.modal-info-value').textContent = data.organizer;
            infoItems[2].querySelector('.modal-description').textContent = data.description;
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // モーダルを閉じる
    function closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // スタッフカードクリックイベント
    document.querySelectorAll('.staff-item').forEach(item => {
        item.addEventListener('click', () => {
            const staffId = item.getAttribute('data-staff-id');
            openModal(staffModal, staffData[staffId]);
        });
    });

    // 実績カードクリックイベント
    document.querySelectorAll('.achievement-item').forEach(item => {
        item.addEventListener('click', () => {
            const achievementId = item.getAttribute('data-achievement-id');
            openModal(achievementModal, achievementData[achievementId]);
        });
    });

    // モーダルを閉じるボタンのイベント
    modalCloseButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            closeModal(modal);
        });
    });

    // モーダル外クリックで閉じる
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    // ESCキーでモーダルを閉じる
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal);
            });
        }
    });

    // アクセシビリティ対応
    document.querySelectorAll('.staff-item, .achievement-item').forEach(item => {
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        item.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                item.click();
            }
        });
    });
});

// 数値カウントアップアニメーション
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// 実績数値のアニメーション
const achievementNumbers = document.querySelectorAll('.achievement-number');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const element = entry.target;
            const value = parseInt(element.textContent);
            if (!isNaN(value)) {
                animateValue(element, 0, value, 2000);
            }
            observer.unobserve(element);
        }
    });
}, {
    threshold: 0.5
});

achievementNumbers.forEach(number => {
    observer.observe(number);
});

// パフォーマンス最適化のためのユーティリティ関数
const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// 画像の遅延読み込み
const lazyLoadImages = () => {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
};

// スクロールアニメーション
const initScrollAnimations = () => {
    const animatedElements = document.querySelectorAll('.philosophy-item, .achievement-item, .staff-item, .awards');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    animatedElements.forEach(element => {
        element.classList.add('scroll-animation');
        observer.observe(element);
    });
};

// ヘッダーの制御
const initHeader = () => {
    const header = document.querySelector('.header');
    let lastScroll = 0;

    const handleScroll = debounce(() => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll <= 0) {
            header.classList.remove('scroll-up');
            return;
        }
        
        if (currentScroll > lastScroll && !header.classList.contains('scroll-down')) {
            header.classList.remove('scroll-up');
            header.classList.add('scroll-down');
        } else if (currentScroll < lastScroll && header.classList.contains('scroll-down')) {
            header.classList.remove('scroll-down');
            header.classList.add('scroll-up');
        }
        lastScroll = currentScroll;
    }, 100);

    window.addEventListener('scroll', handleScroll);
};

// モバイルメニューの制御
const initMobileMenu = () => {
    const hamburger = document.querySelector('.hamburger');
    const nav = document.querySelector('.nav');
    
    if (hamburger && nav) {
        hamburger.addEventListener('click', () => {
            nav.classList.toggle('active');
            hamburger.classList.toggle('active');
        });
    }
};

// スムーズスクロール
const initSmoothScroll = () => {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
                
                const nav = document.querySelector('.nav');
                if (nav.classList.contains('active')) {
                    nav.classList.remove('active');
                    document.querySelector('.hamburger').classList.remove('active');
                }
            }
        });
    });
};

// モーダル制御
const initModals = () => {
    const modals = document.querySelectorAll('.modal');
    const modalCloseButtons = document.querySelectorAll('.modal-close');

    const closeModal = (modal) => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    modalCloseButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            closeModal(modal);
        });
    });

    modals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal);
            });
        }
    });
};

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initHeader();
    initSmoothScroll();
    initScrollAnimations();
    initModals();
    lazyLoadImages();
}); 