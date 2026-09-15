// Run: node tests/tree_display_controls_test.js /path/to/chrome-or-edge
// Exercises the actual tree CSS, dialog markup and controls without production data.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const browser = process.argv[2] || process.env.NV_TEST_BROWSER;
if (!browser) { throw new Error('Pass a Chrome/Edge executable or set NV_TEST_BROWSER.'); }
const root = path.resolve(__dirname, '..');
const template = fs.readFileSync(path.join(root, 'views/family/tree.php'), 'utf8');
const css = template.match(/<style>([\s\S]*?)<\/style>/)[1];
const panel = template.slice(template.indexOf('    <div id="tree-insights-panel"'), template.indexOf('    <!-- Family Tree Display -->'))
    .replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g, '');
const button = id => template.match(new RegExp('<button[^>]*id="' + id + '"[^>]*>[\\s\\S]*?</button>'))[0];
const exitControls = template.match(/<div id="tree-fullscreen-controls"[\s\S]*?<\/div>/)[0];
const controls = fs.readFileSync(path.join(root, 'views/family/js/tree_display_controls.js'), 'utf8');
const styles = ['styles/tailwind.min.css', 'styles/styles.css', 'styles/dTree.css']
    .map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\n');
const checks = `
    const assert = (ok, message) => { if (!ok) throw new Error(message); };
    const panel = document.getElementById('tree-insights-panel');
    const open = document.getElementById('treeInsightsToggle');
    const close = document.getElementById('treeInsightsClose');
    const toggle = document.getElementById('treeFullScreenToggle');
    const exit = document.getElementById('treeFullScreenExit');
    const treeContainer = document.getElementById('family-tree');
    const body = panel.querySelector('.tree-insights-body');
    const key = (key, shiftKey = false) => document.dispatchEvent(new KeyboardEvent('keydown', {key, shiftKey, bubbles:true, cancelable:true}));
    const reachable = element => {
        const box = element.getBoundingClientRect();
        return box.top >= 0 && box.bottom <= innerHeight && box.left >= 0 && box.right <= innerWidth
            && element.contains(document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2));
    };
    try {
        initializeTreeDisplayControls({zoomToFit(){}, resetZoom(){}});
        open.click();
        assert(reachable(close), 'Insights close button must be above navigation and inside viewport');
        assert(document.activeElement === close, 'Opening insights focuses Close');
        assert(open.getAttribute('aria-expanded') === 'true', 'Insights expanded state');
        const heading = document.getElementById('tree-insights-title');
        const extra = document.createElement('div'); extra.style.height = '2000px'; body.append(extra);
        const initialTop = heading.getBoundingClientRect().top;
        body.scrollTop = 500;
        assert(body.scrollTop > 0 && reachable(close), 'Long insights scroll without losing Close');
        assert(heading.getBoundingClientRect().top === initialTop, 'Insights heading remains stationary');
        key('Tab'); assert(document.activeElement === close, 'Tab stays inside dialog');
        key('Tab', true); assert(document.activeElement === close, 'Shift-Tab stays inside dialog');
        key('Escape');
        assert(panel.classList.contains('hidden') && document.activeElement === open, 'Escape closes insights and restores focus');
        assert(!document.body.classList.contains('tree-no-scroll'), 'Closing insights unlocks scrolling');
        open.click(); close.click(); assert(panel.classList.contains('hidden'), 'Close button closes insights');
        open.click(); panel.click(); assert(panel.classList.contains('hidden'), 'Backdrop closes insights');
        toggle.click();
        assert(treeContainer.classList.contains('familytree-fullscreen') && reachable(exit), 'Fullscreen exit is visible above the tree');
        assert(document.activeElement === exit && exit.textContent.includes('Exit full screen'), 'Fullscreen exit is labelled and focused');
        assert(toggle.getAttribute('aria-pressed') === 'true', 'Fullscreen state announced');
        exit.click();
        assert(!treeContainer.classList.contains('familytree-fullscreen') && document.activeElement === toggle, 'Exit button restores regular tree and focus');
        toggle.click(); key('Escape');
        assert(!treeContainer.classList.contains('familytree-fullscreen'), 'Escape exits fullscreen');
        assert(document.getElementById('tree-fullscreen-controls').hidden, 'Exit controls hide after leaving fullscreen');
        toggle.click(); open.click(); key('Escape');
        assert(panel.classList.contains('hidden') && document.body.classList.contains('tree-no-scroll'), 'Escape closes top dialog without unlocking fullscreen');
        key('Escape'); assert(!document.body.classList.contains('tree-no-scroll'), 'Second Escape exits fullscreen and unlocks scrolling');
        document.getElementById('test-result').textContent = 'PASS ' + innerWidth + 'x' + innerHeight;
    } catch (error) { document.getElementById('test-result').textContent = 'FAIL: ' + error.message; }
`;
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'nv-tree-ui-'));
try {
    const fixture = path.join(temp, 'tree.html');
    fs.writeFileSync(fixture, `<!doctype html><html><head><meta charset="utf-8"><style>${styles}\n${css}</style></head>
        <body><header class="site-header fixed top-0 left-0 right-0 z-50" style="height:72px">Navigation</header>
        <main style="padding-top:90px">${button('treeInsightsToggle')}${button('treeFullScreenToggle')}${panel}${exitControls}
        <div id="family-tree" class="familytree"><svg width="1200" height="800"></svg></div></main>
        <pre id="test-result">WAITING</pre><script>${controls}\n${checks}</script></body></html>`);
    for (const size of ['1280,900', '390,680']) {
        const result = spawnSync(browser, ['--headless', '--disable-gpu', '--no-first-run',
            '--no-default-browser-check', '--allow-file-access-from-files', '--user-data-dir=' + path.join(temp, 'profile-' + size),
            '--window-size=' + size, '--dump-dom', '--virtual-time-budget=1000', pathToFileURL(fixture).href],
        {encoding:'utf8', timeout:45000, windowsHide:true, maxBuffer:10 * 1024 * 1024});
        const outcome = result.stdout?.match(/<pre id="test-result">([^<]*)<\/pre>/)?.[1];
        if (result.error || result.status !== 0 || !/^PASS \d+x\d+$/.test(outcome || '')) {
            throw new Error(size + ': ' + (result.error?.message || outcome || result.stderr));
        }
        console.log('Tree dialog and fullscreen browser tests passed at viewport ' + outcome.slice(5) + '.');
    }
} finally {
    const resolved = path.resolve(temp);
    if (path.dirname(resolved) === path.resolve(os.tmpdir()) && path.basename(resolved).startsWith('nv-tree-ui-')) {
        fs.rmSync(resolved, {recursive:true, force:true, maxRetries:5, retryDelay:200});
    }
}
