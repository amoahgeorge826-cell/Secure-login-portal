document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            const button = form.querySelector('button[type="submit"]');
            if (button && form.checkValidity()) {
                button.dataset.originalText = button.textContent;
                button.textContent = button.dataset.loadingText || 'Please wait…';
                button.classList.add('is-loading');
                button.disabled = true;
            }
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            if (!target) return;

            try {
                await navigator.clipboard.writeText(target.textContent.trim());
                const oldText = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => { button.textContent = oldText; }, 1400);
            } catch {
                target.focus();
                window.getSelection()?.selectAllChildren(target);
            }
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.textContent = showing ? 'Show' : 'Hide';
        });
    });

    const password = document.querySelector('[data-password-meter]');
    const meter = document.getElementById('password-meter');
    const strengthLabel = document.getElementById('password-strength-label');
    if (password && meter && strengthLabel) {
        password.addEventListener('input', () => {
            const value = password.value;
            let score = 0;
            if (value.length >= 12) score++;
            if (value.length >= 16) score++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            const strength = value.length === 0 ? '' : score <= 1 ? 'weak' : score <= 2 ? 'fair' : score <= 3 ? 'good' : 'strong';
            meter.dataset.strength = strength;
            strengthLabel.dataset.strength = strength;
            strengthLabel.textContent = strength ? `${strength[0].toUpperCase()}${strength.slice(1)} password` : 'Choose a unique passphrase of at least 12 characters.';
        });
    }
});
