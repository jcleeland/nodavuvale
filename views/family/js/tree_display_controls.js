window.initializeTreeDisplayControls = function (tree) {
    const container = document.getElementById('family-tree');
    const toggle = document.getElementById('treeFullScreenToggle');
    const exitControls = document.getElementById('tree-fullscreen-controls');
    const exitButton = document.getElementById('treeFullScreenExit');
    const panel = document.getElementById('tree-insights-panel');
    const insightsToggle = document.getElementById('treeInsightsToggle');
    const insightsClose = document.getElementById('treeInsightsClose');
    let resizeTimer;
    const insightsOpen = () => panel && !panel.classList.contains('hidden');
    const isFullscreen = () => container && container.classList.contains('familytree-fullscreen');
    const updateScroll = () => document.body.classList.toggle('tree-no-scroll', Boolean(insightsOpen() || isFullscreen()));

    function setFullscreen(open) {
        container.classList.toggle('familytree-fullscreen', open);
        exitControls.hidden = !open;
        toggle.setAttribute('aria-pressed', String(open));
        toggle.setAttribute('title', open ? 'Exit full screen' : 'View tree full screen');
        toggle.setAttribute('aria-label', toggle.getAttribute('title'));
        toggle.innerHTML = open ? '<i class="fas fa-compress" aria-hidden="true"></i>' : '<i class="fas fa-expand" aria-hidden="true"></i>';
        updateScroll();
        (open ? exitButton : toggle).focus({ preventScroll: true });
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (open && tree && tree.zoomToFit) { tree.zoomToFit(250); }
            if (!open && tree && tree.resetZoom) { tree.resetZoom(250); }
        }, 150);
    }

    function setInsights(open) {
        panel.classList.toggle('hidden', !open);
        insightsToggle.setAttribute('aria-expanded', String(open));
        insightsToggle.classList.toggle('bg-emerald-600', open);
        insightsToggle.classList.toggle('hover:bg-emerald-700', open);
        updateScroll();
        (open ? insightsClose : insightsToggle).focus({ preventScroll: true });
    }

    if (toggle && container && exitControls && exitButton) {
        toggle.addEventListener('click', () => setFullscreen(!isFullscreen()));
        exitButton.addEventListener('click', () => setFullscreen(false));
    }
    if (panel && insightsToggle && insightsClose) {
        insightsToggle.addEventListener('click', () => setInsights(!insightsOpen()));
        insightsClose.addEventListener('click', () => setInsights(false));
        panel.addEventListener('click', event => { if (event.target === panel) { setInsights(false); } });
    }
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (insightsOpen()) { setInsights(false); event.preventDefault(); }
            else if (isFullscreen()) { setFullscreen(false); event.preventDefault(); }
        } else if (event.key === 'Tab' && insightsOpen()) {
            const focusable = Array.from(panel.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]'))
                .filter(element => !element.disabled && element.getClientRects().length);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (first && (!panel.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)
                || (!event.shiftKey && document.activeElement === last))) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            }
        }
    });
};
