(function () {
    function toggle(btn) {
        if (!btn) return;
        var now = Date.now ? Date.now() : new Date().getTime();
        if (btn._lastToggle && (now - btn._lastToggle) < 250) {
            return;
        }
        btn._lastToggle = now;

        var wrap = btn.closest ? btn.closest('.pw') : null;
        if (!wrap && btn.parentElement) {
            wrap = btn.parentElement;
        }
        if (!wrap) return;

        var input = wrap.querySelector('input');
        if (!input) return;

        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.classList.toggle('showing', isPassword);
        btn.setAttribute('aria-pressed', String(isPassword));
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    }

    // Global function for backward compatibility
    window.togglePw = function (eventOrBtn, maybeBtn) {
        var btn = maybeBtn;
        if (!btn) {
            if (eventOrBtn && eventOrBtn.target && eventOrBtn.target.closest) {
                btn = eventOrBtn.target.closest('button.eye') || eventOrBtn.currentTarget;
            } else {
                btn = eventOrBtn;
            }
        }
        if (eventOrBtn && typeof eventOrBtn.preventDefault === 'function') {
            eventOrBtn.preventDefault();
            eventOrBtn.stopPropagation();
        }
        toggle(btn);
    };

    // Delegated click listener on document (works seamlessly with strict CSP)
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('button.eye') : null;
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            toggle(btn);
        }
    }, true);
})();
