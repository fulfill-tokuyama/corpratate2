class FeedbackForm {
    constructor() {
        this.form = document.querySelector('.feedback-form form');
        this.submitButton = document.querySelector('.submit-button');
        this.resetButton = document.querySelector('.reset-button');
        this.successMessage = document.querySelector('.success-message');
        
        this.initializeEventListeners();
        this.initializeValidation();
    }

    initializeEventListeners() {
        this.form.addEventListener('submit', this.handleSubmit.bind(this));
        this.resetButton.addEventListener('click', this.handleReset.bind(this));
        
        // 入力フィールドの変更を監視
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', () => this.validateField(field));
            field.addEventListener('blur', () => this.validateField(field));
        });
    }

    initializeValidation() {
        // バリデーションルールの定義
        this.validationRules = {
            'feedback-type': {
                required: true,
                message: 'フィードバックの種類を選択してください'
            },
            'feedback-content': {
                required: true,
                minLength: 10,
                maxLength: 1000,
                message: '10文字以上1000文字以内で入力してください'
            },
            'name': {
                maxLength: 50,
                message: '50文字以内で入力してください'
            },
            'email': {
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: '有効なメールアドレスを入力してください'
            },
            'phone': {
                pattern: /^[0-9-+()]*$/,
                message: '有効な電話番号を入力してください'
            }
        };
    }

    validateField(field) {
        const rules = this.validationRules[field.name];
        if (!rules) return true;

        const formGroup = field.closest('.form-group');
        let isValid = true;
        let errorMessage = '';

        // 必須チェック
        if (rules.required && !field.value.trim()) {
            isValid = false;
            errorMessage = rules.message;
        }

        // 最小長チェック
        if (isValid && rules.minLength && field.value.length < rules.minLength) {
            isValid = false;
            errorMessage = rules.message;
        }

        // 最大長チェック
        if (isValid && rules.maxLength && field.value.length > rules.maxLength) {
            isValid = false;
            errorMessage = rules.message;
        }

        // パターンチェック
        if (isValid && rules.pattern && !rules.pattern.test(field.value)) {
            isValid = false;
            errorMessage = rules.message;
        }

        // エラー表示の更新
        this.updateFieldValidation(formGroup, isValid, errorMessage);
        return isValid;
    }

    updateFieldValidation(formGroup, isValid, errorMessage) {
        const existingError = formGroup.querySelector('.error-message');
        
        if (!isValid) {
            formGroup.classList.add('error');
            if (!existingError) {
                const errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                errorElement.textContent = errorMessage;
                formGroup.appendChild(errorElement);
            } else {
                existingError.textContent = errorMessage;
            }
        } else {
            formGroup.classList.remove('error');
            if (existingError) {
                existingError.remove();
            }
        }
    }

    validateForm() {
        let isValid = true;
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        return isValid;
    }

    async handleSubmit(event) {
        event.preventDefault();

        if (!this.validateForm()) {
            return;
        }

        // プライバシーポリシーの同意チェック
        const privacyCheckbox = this.form.querySelector('input[name="privacy-agreement"]');
        if (!privacyCheckbox.checked) {
            alert('プライバシーポリシーに同意してください。');
            return;
        }

        try {
            this.setLoading(true);
            
            const formData = new FormData(this.form);
            const data = Object.fromEntries(formData.entries());
            
            // APIエンドポイントに送信
            const response = await fetch('/api/feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error('送信に失敗しました');
            }

            this.showSuccessMessage();
            this.form.reset();
            
        } catch (error) {
            console.error('Error:', error);
            alert('送信に失敗しました。時間をおいて再度お試しください。');
        } finally {
            this.setLoading(false);
        }
    }

    handleReset(event) {
        event.preventDefault();
        if (confirm('入力内容をリセットしますか？')) {
            this.form.reset();
            this.form.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error');
                const errorMessage = group.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
            });
        }
    }

    setLoading(isLoading) {
        this.submitButton.disabled = isLoading;
        this.resetButton.disabled = isLoading;
        this.form.classList.toggle('loading', isLoading);
    }

    showSuccessMessage() {
        this.successMessage.style.display = 'block';
        setTimeout(() => {
            this.successMessage.style.display = 'none';
        }, 5000);
    }
}

// フォームの初期化
document.addEventListener('DOMContentLoaded', () => {
    new FeedbackForm();
}); 