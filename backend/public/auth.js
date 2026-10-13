function togglePw(eventOrBtn, maybeBtn) {
    var btn = maybeBtn;
    if (!btn) {
        btn = eventOrBtn;
    } else if (eventOrBtn && typeof eventOrBtn.preventDefault === 'function') {
        eventOrBtn.preventDefault();
        eventOrBtn.stopPropagation();
    }
    if (!btn || !btn.closest) return;
    var wrap = btn.closest('.pw');
    if (!wrap) return;
    var input = wrap.querySelector('input');
    if (!input) return;
    var isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.classList.toggle('showing', isPassword);
    btn.setAttribute('aria-pressed', String(isPassword));
    btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
}
