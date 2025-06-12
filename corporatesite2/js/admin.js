class AdminPanel {
    constructor() {
        this.initializeModals();
        this.initializeEventListeners();
        this.initializeSecurity();
    }

    initializeModals() {
        this.notesModal = document.getElementById('notesModal');
        this.historyModal = document.getElementById('historyModal');
        this.currentFeedbackId = null;
    }

    initializeEventListeners() {
        // ステータス変更ボタン
        document.querySelectorAll('.btn-status').forEach(button => {
            button.addEventListener('click', (e) => this.handleStatusChange(e));
        });

        // メモボタン
        document.querySelectorAll('.btn-notes').forEach(button => {
            button.addEventListener('click', (e) => this.showNotesModal(e));
        });

        // 履歴ボタン
        document.querySelectorAll('.btn-history').forEach(button => {
            button.addEventListener('click', (e) => this.showHistoryModal(e));
        });

        // モーダル内のボタン
        this.notesModal.querySelector('.btn-save').addEventListener('click', () => this.saveNotes());
        this.notesModal.querySelector('.btn-cancel').addEventListener('click', () => this.hideNotesModal());
        this.historyModal.querySelector('.btn-close').addEventListener('click', () => this.hideHistoryModal());

        // モーダルの外側クリックで閉じる
        window.addEventListener('click', (e) => {
            if (e.target === this.notesModal) this.hideNotesModal();
            if (e.target === this.historyModal) this.hideHistoryModal();
        });
    }

    initializeSecurity() {
        // CSRFトークンの設定
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        // セッションタイムアウトの設定
        this.sessionTimeout = 7200000; // 2時間
        this.lastActivity = Date.now();
        this.initializeSessionTimeout();
        
        // アクティビティ監視
        this.initializeActivityMonitor();
    }

    initializeSessionTimeout() {
        setInterval(() => {
            if (Date.now() - this.lastActivity > this.sessionTimeout) {
                this.handleSessionTimeout();
            }
        }, 60000); // 1分ごとにチェック
    }

    initializeActivityMonitor() {
        const events = ['click', 'keypress', 'scroll', 'mousemove'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
            });
        });
    }

    handleSessionTimeout() {
        // 現在の状態を保存
        localStorage.setItem('adminLastState', JSON.stringify({
            path: window.location.pathname,
            scroll: window.scrollY
        }));

        // ログアウト処理
        this.logout();
    }

    async logout() {
        try {
            await fetch('/api/admin/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrfToken
                }
            });
            window.location.href = '/admin/login?expired=1';
        } catch (error) {
            console.error('Logout error:', error);
            window.location.href = '/admin/login?expired=1';
        }
    }

    async apiRequest(endpoint, method = 'GET', data = null) {
        const headers = {
            'X-CSRF-Token': this.csrfToken,
            'Content-Type': 'application/json'
        };

        try {
            const response = await fetch(endpoint, {
                method,
                headers,
                body: data ? JSON.stringify(data) : null,
                credentials: 'same-origin'
            });

            if (response.status === 401) {
                this.handleSessionTimeout();
                return null;
            }

            if (!response.ok) {
                throw new Error(`API request failed: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    }

    async handleStatusChange(event) {
        const button = event.target;
        const feedbackItem = button.closest('.feedback-item');
        const feedbackId = feedbackItem.dataset.id;
        const newStatus = button.dataset.status;

        try {
            this.setLoading(button, true);

            await this.apiRequest('/api/feedback/status', 'POST', {
                feedback_id: feedbackId,
                status: newStatus
            });

            // 成功時の処理
            const feedbackList = document.querySelector('.feedback-list');
            feedbackItem.remove();
            
            if (feedbackList.children.length === 0) {
                location.reload();
            }

        } catch (error) {
            this.showError('ステータスの更新に失敗しました。時間をおいて再度お試しください。');
        } finally {
            this.setLoading(button, false);
        }
    }

    showNotesModal(event) {
        const button = event.target;
        const feedbackItem = button.closest('.feedback-item');
        this.currentFeedbackId = feedbackItem.dataset.id;
        
        // 現在のメモを取得
        const currentNotes = feedbackItem.querySelector('.admin-notes')?.textContent || '';
        this.notesModal.querySelector('textarea').value = currentNotes;
        
        this.notesModal.style.display = 'block';
    }

    hideNotesModal() {
        this.notesModal.style.display = 'none';
        this.currentFeedbackId = null;
    }

    async saveNotes() {
        if (!this.currentFeedbackId) return;

        const notes = this.notesModal.querySelector('textarea').value;
        const button = this.notesModal.querySelector('.btn-save');

        try {
            this.setLoading(button, true);

            const response = await fetch('/api/feedback/notes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    feedback_id: this.currentFeedbackId,
                    notes: notes
                })
            });

            if (!response.ok) {
                throw new Error('メモの保存に失敗しました');
            }

            // 成功時の処理
            const feedbackItem = document.querySelector(`.feedback-item[data-id="${this.currentFeedbackId}"]`);
            let notesElement = feedbackItem.querySelector('.admin-notes');
            
            if (!notesElement) {
                notesElement = document.createElement('div');
                notesElement.className = 'admin-notes';
                feedbackItem.querySelector('.feedback-content').after(notesElement);
            }
            
            notesElement.textContent = notes;
            this.hideNotesModal();

        } catch (error) {
            console.error('Error:', error);
            alert('メモの保存に失敗しました。時間をおいて再度お試しください。');
        } finally {
            this.setLoading(button, false);
        }
    }

    async showHistoryModal(event) {
        const button = event.target;
        const feedbackItem = button.closest('.feedback-item');
        const feedbackId = feedbackItem.dataset.id;
        
        try {
            this.setLoading(button, true);

            const response = await fetch(`/api/feedback/history/${feedbackId}`);
            
            if (!response.ok) {
                throw new Error('履歴の取得に失敗しました');
            }

            const history = await response.json();
            const historyList = this.historyModal.querySelector('#historyList');
            
            // 履歴の表示
            historyList.innerHTML = history.map(item => `
                <div class="history-item">
                    <div class="history-header">
                        <span class="history-action">${item.action}</span>
                        <span class="history-date">${new Date(item.created_at).toLocaleString()}</span>
                    </div>
                    ${item.notes ? `<div class="history-notes">${item.notes}</div>` : ''}
                </div>
            `).join('');

            this.historyModal.style.display = 'block';

        } catch (error) {
            console.error('Error:', error);
            alert('履歴の取得に失敗しました。時間をおいて再度お試しください。');
        } finally {
            this.setLoading(button, false);
        }
    }

    hideHistoryModal() {
        this.historyModal.style.display = 'none';
    }

    setLoading(element, isLoading) {
        element.disabled = isLoading;
        element.closest('.feedback-item')?.classList.toggle('loading', isLoading);
    }

    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.textContent = message;
        document.querySelector('.admin-main').prepend(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

// 管理者パネルの初期化
document.addEventListener('DOMContentLoaded', () => {
    new AdminPanel();
});

// 管理者用のJavaScript

// DOMの読み込み完了を待つ
document.addEventListener('DOMContentLoaded', () => {
    // セッションタイムアウトの監視
    let sessionTimeout;
    const SESSION_TIMEOUT = 7200000; // 2時間（ミリ秒）

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(() => {
            window.location.href = 'login.php?expired=1';
        }, SESSION_TIMEOUT);
    }

    // ユーザーのアクティビティを監視
    ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
        document.addEventListener(event, resetSessionTimeout);
    });

    // 初期タイマーをセット
    resetSessionTimeout();

    // チャートの初期化
    initializeCharts();

    // テーブルのソート機能
    initializeTableSort();

    // レスポンシブナビゲーション
    initializeResponsiveNav();
});

// チャートの初期化
function initializeCharts() {
    const chartElements = document.querySelectorAll('canvas');
    chartElements.forEach(canvas => {
        const ctx = canvas.getContext('2d');
        const chartType = canvas.dataset.chartType;
        const chartData = JSON.parse(canvas.dataset.chartData);

        new Chart(ctx, {
            type: chartType,
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
}

// テーブルのソート機能
function initializeTableSort() {
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach(header => {
            if (header.dataset.sortable !== 'false') {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    const index = Array.from(header.parentElement.children).indexOf(header);
                    sortTable(table, index);
                });
            }
        });
    });
}

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isNumeric = !isNaN(rows[0].children[column].textContent);
    const direction = table.dataset.sortDirection === 'asc' ? -1 : 1;

    rows.sort((a, b) => {
        const aValue = a.children[column].textContent;
        const bValue = b.children[column].textContent;

        if (isNumeric) {
            return direction * (parseFloat(aValue) - parseFloat(bValue));
        } else {
            return direction * aValue.localeCompare(bValue, 'ja');
        }
    });

    table.dataset.sortDirection = direction === 1 ? 'asc' : 'desc';
    tbody.append(...rows);
}

// レスポンシブナビゲーション
function initializeResponsiveNav() {
    const navToggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.admin-nav');

    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            nav.classList.toggle('active');
        });

        // ウィンドウのリサイズ時にナビゲーションをリセット
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                nav.classList.remove('active');
            }
        });
    }
}

// アラートメッセージの表示
function showAlert(message, type = 'success') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    const container = document.querySelector('.admin-main');
    container.insertBefore(alert, container.firstChild);

    // 5秒後にアラートを消す
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// データの更新
async function updateData(endpoint, data) {
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        showAlert(result.message, result.success ? 'success' : 'error');
        return result;

    } catch (error) {
        console.error('Error:', error);
        showAlert('エラーが発生しました。', 'error');
        return null;
    }
}

// ページの更新
function refreshPage() {
    window.location.reload();
}

// エクスポート機能
function exportFeedback(type) {
    const searchParams = new URLSearchParams(window.location.search);
    searchParams.set('export_type', type);
    
    const url = `api/export.php?${searchParams.toString()}`;
    window.location.href = url;
}

// エクスポートボタンのイベントリスナー
document.querySelectorAll('.export-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const type = e.target.dataset.type;
        exportFeedback(type);
    });
});

// 印刷機能
function printPage() {
    window.print();
}

// ダークモードの切り替え
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// ダークモードの初期化
function initializeDarkMode() {
    const darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
}

// アクセシビリティ機能
function initializeAccessibility() {
    // フォントサイズの調整
    const fontSizeControls = document.querySelectorAll('.font-size-control');
    fontSizeControls.forEach(control => {
        control.addEventListener('click', () => {
            const size = control.dataset.size;
            document.documentElement.style.fontSize = size;
            localStorage.setItem('fontSize', size);
        });
    });

    // コントラストの調整
    const contrastControls = document.querySelectorAll('.contrast-control');
    contrastControls.forEach(control => {
        control.addEventListener('click', () => {
            const contrast = control.dataset.contrast;
            document.body.dataset.contrast = contrast;
            localStorage.setItem('contrast', contrast);
        });
    });
}

// 初期化
initializeDarkMode();
initializeAccessibility(); 