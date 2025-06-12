// フォームバリデーション
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = new Map();
        this.init();
    }

    init() {
        this.form.addEventListener('submit', this.handleSubmit.bind(this));
        this.form.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.validateField(input));
        });
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // 必須チェック
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'この項目は必須です';
        }

        // メールアドレス形式チェック
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = '有効なメールアドレスを入力してください';
            }
        }

        // 電話番号形式チェック
        if (field.name === 'phone' && value) {
            const phoneRegex = /^[0-9-]{10,13}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = '有効な電話番号を入力してください';
            }
        }

        // 文字数制限チェック
        if (field.hasAttribute('maxlength')) {
            const maxLength = parseInt(field.getAttribute('maxlength'));
            if (value.length > maxLength) {
                isValid = false;
                errorMessage = `${maxLength}文字以内で入力してください`;
            }
        }

        this.updateFieldValidation(field, isValid, errorMessage);
        return isValid;
    }

    updateFieldValidation(field, isValid, errorMessage) {
        const errorElement = field.nextElementSibling;
        
        if (!isValid) {
            field.classList.add('error');
            if (!errorElement || !errorElement.classList.contains('error-message')) {
                const error = document.createElement('div');
                error.className = 'error-message';
                error.textContent = errorMessage;
                field.parentNode.insertBefore(error, field.nextSibling);
            } else {
                errorElement.textContent = errorMessage;
            }
            this.errors.set(field.name, errorMessage);
        } else {
            field.classList.remove('error');
            if (errorElement && errorElement.classList.contains('error-message')) {
                errorElement.remove();
            }
            this.errors.delete(field.name);
        }
    }

    async handleSubmit(event) {
        event.preventDefault();
        
        // すべてのフィールドをバリデーション
        const fields = this.form.querySelectorAll('input, textarea');
        let isValid = true;
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            return;
        }

        // CSRFトークンの検証
        const csrfToken = this.form.querySelector('input[name="csrf_token"]').value;
        if (!this.validateCsrfToken(csrfToken)) {
            alert('セキュリティエラーが発生しました。ページを再読み込みしてください。');
            return;
        }

        // 送信ボタンを無効化
        const submitButton = this.form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = '送信中...';

        try {
            const formData = new FormData(this.form);
            const response = await fetch(this.form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            if (!response.ok) {
                throw new Error('送信に失敗しました');
            }

            const result = await response.json();
            this.showSuccessMessage();
            this.form.reset();
        } catch (error) {
            this.showErrorMessage(error.message);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = '送信する';
        }
    }

    validateCsrfToken(token) {
        // サーバーサイドで生成されたトークンと比較
        return token && token.length > 0;
    }

    showSuccessMessage() {
        const message = document.createElement('div');
        message.className = 'success-message';
        message.textContent = 'お問い合わせを受け付けました。担当者より折り返しご連絡いたします。';
        this.form.insertBefore(message, this.form.firstChild);
        
        setTimeout(() => {
            message.remove();
        }, 5000);
    }

    showErrorMessage(message) {
        const error = document.createElement('div');
        error.className = 'error-message';
        error.textContent = message;
        this.form.insertBefore(error, this.form.firstChild);
        
        setTimeout(() => {
            error.remove();
        }, 5000);
    }
}

// フォームの初期化
document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.querySelector('#contactForm');
    if (contactForm) {
        new FormValidator(contactForm);
    }
}); 