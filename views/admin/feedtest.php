<?php
// Feed service test harness for administrators.
require 'vendor/autoload.php';
$admin_page = true;
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $auth->getUserRole() === 'admin';

if (!$is_logged_in || !$is_admin) {
    header('Location: index.php?to=home');
    exit;
}
?>

<section class="hero text-white py-16">
    <div class="container hero-content">
        <h2 class="text-3xl font-bold">Feed Service Test</h2>
        <p class="mt-3 text-lg">This page calls <code>ajax.php</code> with the <code>get_feed_entries</code> method so you can inspect the paginated feed payload.</p>
    </div>
</section>

<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <div>
            <h3 class="text-xl font-semibold text-brown mb-2">Initial Load</h3>
            <p class="text-sm text-gray-600">The script below automatically fetches the first two feed entries when the page loads.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
            <div>
                <label for="feedOffset" class="block text-sm font-medium text-gray-700">Offset</label>
                <input id="feedOffset" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-ocean-blue focus:border-ocean-blue" value="0" min="0">
            </div>
            <div>
                <label for="feedLimit" class="block text-sm font-medium text-gray-700">Limit</label>
                <input id="feedLimit" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-ocean-blue focus:border-ocean-blue" value="3" min="1" max="50">
            </div>
            <div class="md:col-span-2">
                <label for="feedSince" class="block text-sm font-medium text-gray-700">Since (optional datetime)</label>
                <input id="feedSince" type="text" placeholder="YYYY-MM-DD HH:MM:SS" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-ocean-blue focus:border-ocean-blue">
            </div>
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            <button id="feedFetchButton" type="button" class="px-4 py-2 bg-ocean-blue text-white rounded-lg hover:bg-ocean-blue-700">Fetch Feed</button>
            <button id="feedNextButton" type="button" class="px-4 py-2 bg-burnt-orange text-white rounded-lg hover:bg-burnt-orange-700" disabled>Fetch Next Batch</button>
            <span id="feedStatus" class="text-sm text-gray-600"></span>
            <span id="feedTiming" class="text-xs text-gray-500"></span>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-brown mb-2">Last Request</h3>
            <pre id="feedRequest" class="bg-gray-900 text-blue-200 text-sm rounded-lg p-4 overflow-auto" style="max-height: 240px;">Waiting for request…</pre>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-brown mb-2">Last Response</h3>
            <pre id="feedResponse" class="bg-gray-900 text-green-200 text-sm rounded-lg p-4 overflow-auto" style="max-height: 420px;">Waiting for response…</pre>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-brown mb-2">Rendered Entries</h3>
            <div id="feedEntries" class="space-y-4"></div>
        </div>
    </div>
</section>

<script>
(() => {
    const endpoint = 'ajax.php';
    const offsetInput = document.getElementById('feedOffset');
    const limitInput = document.getElementById('feedLimit');
    const sinceInput = document.getElementById('feedSince');
    const fetchButton = document.getElementById('feedFetchButton');
    const nextButton = document.getElementById('feedNextButton');
    const statusEl = document.getElementById('feedStatus');
    const timingEl = document.getElementById('feedTiming');
    const requestEl = document.getElementById('feedRequest');
    const responseEl = document.getElementById('feedResponse');
    const entriesEl = document.getElementById('feedEntries');

    let lastPayload = null;

    function setStatus(message, isError = false) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('text-warm-red', Boolean(isError));
    }

    function setTiming(message) {
        timingEl.textContent = message || '';
    }

    function renderRequest(data) {
        requestEl.textContent = JSON.stringify(data, null, 2);
    }

    function renderResponse(data) {
        responseEl.textContent = JSON.stringify(data, null, 2);
    }

    function renderEntries(entries) {
        if (!Array.isArray(entries) || entries.length === 0) {
            entriesEl.innerHTML = '<p class="text-sm text-gray-500">No entries returned.</p>';
            return;
        }
        const fragments = entries.map(entry => {
            if (entry.html) {
                return entry.html;
            }
            const metaBits = [];
            if (entry.type) metaBits.push(`<span class="font-semibold">${entry.type}</span>`);
            if (entry.meta && entry.meta.actor_name) metaBits.push(`by ${escapeHtml(entry.meta.actor_name)}`);
            if (entry.raw_time) metaBits.push(`@ ${escapeHtml(entry.raw_time)}`);
            return `
                <article class="border border-gray-200 rounded-lg p-4 bg-white shadow-sm">
                    <header class="text-sm text-gray-600 mb-2">${metaBits.join(' &bull; ')}</header>
                    ${entry.title ? `<h4 class="text-lg font-semibold text-brown mb-1">${escapeHtml(entry.title)}</h4>` : ''}
                    ${entry.content_html ? `<div class="text-sm text-gray-700 mb-2">${entry.content_html}</div>` : ''}
                    ${entry.content && !entry.content_html ? `<p class="text-sm text-gray-700 mb-2">${escapeHtml(entry.content)}</p>` : ''}
                    ${entry.url ? `<a href="${entry.url}" class="text-sm text-ocean-blue hover:text-burnt-orange" target="_blank" rel="noopener">Open link</a>` : ''}
                </article>
            `;
        });
        entriesEl.innerHTML = fragments.join('');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function fetchFeed(offset, limit, since) {
        setStatus('Loading…');
        setTiming('');
        nextButton.disabled = true;

        try {
            const payload = {
                method: 'get_feed_entries',
                data: {
                    offset: Number.isFinite(offset) ? offset : 0,
                    limit: Number.isFinite(limit) ? limit : 10
                }
            };
            if (since) {
                payload.data.since = since;
            }

            renderRequest(payload);

            const start = performance.now();
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const body = await response.json();
            const elapsedMs = performance.now() - start;
            lastPayload = body;
            renderResponse(body);
            setTiming(`Request time: ${elapsedMs.toFixed(1)} ms`);

            if (body && body.success && body.data) {
                renderEntries(body.data.entries);
                setStatus(`Loaded ${body.data.entries.length} entries (offset ${body.data.requestedOffset}, limit ${body.data.requestedLimit}).`);
                if (body.data.hasMore) {
                    nextButton.disabled = false;
                    nextButton.dataset.nextOffset = body.data.nextOffset;
                } else {
                    nextButton.disabled = true;
                    nextButton.removeAttribute('data-next-offset');
                }
            } else {
                renderEntries([]);
                const errorMessage = body && body.message ? body.message : 'Request failed';
                setStatus(errorMessage, true);
            }
        } catch (error) {
            renderEntries([]);
            setStatus(error.message || 'Unexpected error', true);
            responseEl.textContent = error.stack || String(error);
            setTiming('');
        }
    }

    fetchButton.addEventListener('click', () => {
        const offset = parseInt(offsetInput.value, 10) || 0;
        const limit = parseInt(limitInput.value, 10) || 2;
        const since = sinceInput.value.trim();
        fetchFeed(offset, limit, since);
    });

    nextButton.addEventListener('click', () => {
        const nextOffset = parseInt(nextButton.dataset.nextOffset, 10) || 0;
        offsetInput.value = nextOffset;
        fetchFeed(nextOffset, parseInt(limitInput.value, 10) || 2, sinceInput.value.trim());
    });

    // Pull the first two entries immediately.
    fetchFeed(0, 2, '');
})();
</script>
