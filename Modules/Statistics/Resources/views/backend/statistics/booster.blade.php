@extends('backend.layouts.app')

@section('title')
    Content Booster &mdash; Statistics
@endsection

@push('before-styles')
<style>
.boost-card {
    background: #15162c;
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 14px;
    padding: 1.5rem;
}
.search-result-item {
    padding: .65rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background .15s;
    border: 1px solid transparent;
}
.search-result-item:hover,
.search-result-item.selected {
    background: rgba(129,140,248,.12);
    border-color: rgba(129,140,248,.3);
}
.type-pill {
    display: inline-block;
    padding: .15rem .55rem;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.type-pill.video     { background: rgba(99,102,241,.2);  color: #818cf8; }
.type-pill.episode   { background: rgba(20,184,166,.2);  color: #2dd4bf; }
.type-pill.livetv    { background: rgba(244,63,94,.2);   color: #f43f5e; }
.stat-row { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.stat-box {
    flex: 1; min-width: 130px;
    background: rgba(255,255,255,.04);
    border-radius: 10px;
    padding: .85rem 1rem;
    text-align: center;
}
.stat-box .num { font-size: 1.6rem; font-weight: 700; }
.stat-box .lbl { font-size: .72rem; color: #8e9ab5; text-transform: uppercase; letter-spacing: .05em; }
.boost-entry {
    display: flex; align-items: center; gap: .75rem;
    padding: .65rem .85rem;
    border-radius: 8px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
    margin-bottom: .5rem;
}
.boost-entry:hover { background: rgba(255,255,255,.06); }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-0 fw-bold"><i class="ph ph-rocket-launch me-2 text-danger"></i>Content Booster</h3>
        <p class="text-muted mb-0 small">Set a boosted play/view count for individual content items.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('backend.statistics.index') }}" class="btn btn-sm btn-dark">
            <i class="ph ph-chart-bar me-1"></i>Dashboard
        </a>
        <a href="{{ route('backend.statistics.settings') }}" class="btn btn-sm btn-dark">
            <i class="ph ph-gear me-1"></i>Settings
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Left: Search & select content --}}
    <div class="col-lg-5">
        <div class="boost-card h-100">
            <h6 class="fw-semibold mb-3">Search Content</h6>

            <div class="d-flex gap-2 mb-3">
                <select id="searchType" class="form-select form-select-sm" style="width:130px; flex-shrink:0">
                    <option value="all">All Types</option>
                    <option value="video">Videos</option>
                    <option value="episode">Episodes</option>
                    <option value="livetv">Live TV</option>
                </select>
                <input type="text" id="searchInput" class="form-control form-control-sm"
                    placeholder="Type a title to search…" autocomplete="off">
            </div>

            <div id="searchResults" style="max-height:420px; overflow-y:auto">
                <p class="text-muted small text-center py-4">Type at least 2 characters to search</p>
            </div>
        </div>
    </div>

    {{-- Right: Boost editor --}}
    <div class="col-lg-7">
        <div class="boost-card" id="boostEditor" style="display:none">
            <div class="d-flex align-items-start justify-content-between mb-3 gap-2">
                <div>
                    <h6 class="fw-bold mb-0" id="editorTitle">—</h6>
                    <span class="type-pill mt-1" id="editorTypePill"></span>
                </div>
                <button class="btn btn-sm btn-dark" id="clearEditor">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            {{-- Current stats --}}
            <div class="stat-row" id="statsRow">
                <div class="stat-box">
                    <div class="num text-primary" id="realPlays">—</div>
                    <div class="lbl">Real Plays</div>
                </div>
                <div class="stat-box">
                    <div class="num text-info" id="realViews">—</div>
                    <div class="lbl">Real Views</div>
                </div>
                <div class="stat-box">
                    <div class="num text-success" id="displayPlays">—</div>
                    <div class="lbl">Display Plays</div>
                </div>
                <div class="stat-box">
                    <div class="num text-success" id="displayViews">—</div>
                    <div class="lbl">Display Views</div>
                </div>
            </div>

            <form id="boostForm">
                <input type="hidden" id="f_content_type" name="content_type">
                <input type="hidden" id="f_content_id"   name="content_id">

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium" for="f_boost_plays">
                            <i class="ph ph-play-circle text-primary me-1"></i>Boosted Plays
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="f_boost_plays"
                                name="boost_plays" min="0" value="0">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                onclick="document.getElementById('f_boost_plays').value = Math.max(0, parseInt(document.getElementById('f_boost_plays').value||0) * 2)"
                                title="2×">2×</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                onclick="document.getElementById('f_boost_plays').value = Math.max(0, parseInt(document.getElementById('f_boost_plays').value||0) * 5)"
                                title="5×">5×</button>
                        </div>
                        <div class="form-text">Plays to add on top of real count</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium" for="f_boost_views">
                            <i class="ph ph-eye text-info me-1"></i>Boosted Views
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="f_boost_views"
                                name="boost_views" min="0" value="0">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                onclick="document.getElementById('f_boost_views').value = Math.max(0, parseInt(document.getElementById('f_boost_views').value||0) * 2)"
                                title="2×">2×</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2"
                                onclick="document.getElementById('f_boost_views').value = Math.max(0, parseInt(document.getElementById('f_boost_views').value||0) * 5)"
                                title="5×">5×</button>
                        </div>
                        <div class="form-text">Views to add on top of real count</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium" for="f_note">Note (internal)</label>
                    <input type="text" class="form-control" id="f_note" name="note"
                        placeholder="Optional reason…" maxlength="255">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="ph ph-plus me-1"></i>Add Boost Entry
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="resetToReal">
                        <i class="ph ph-trash me-1"></i>Clear All Boosts
                    </button>
                </div>
            </form>

            {{-- Boost history for this item --}}
            <div id="boostHistory" class="mt-3" style="display:none">
                <hr style="border-color:rgba(255,255,255,.08)">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="fw-semibold text-muted text-uppercase">Boost History</small>
                    <small class="text-muted" id="historyTotal"></small>
                </div>
                <div id="historyList" style="max-height:220px;overflow-y:auto"></div>
            </div>
        </div>

        <div class="boost-card mt-4" id="noEditorPlaceholder">
            <p class="text-muted text-center py-4 mb-0">
                <i class="ph ph-arrow-left me-1"></i>Search and select a content item to boost
            </p>
        </div>

        {{-- Active boosts list --}}
        <div class="boost-card mt-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-semibold mb-0"><i class="ph ph-list-bullets me-2"></i>Active Boosts</h6>
                <span class="badge bg-danger" id="activeBoostCount">{{ $boosts->count() }}</span>
            </div>
            <div id="activeBoostsList">
                @forelse($boosts as $boost)
                <div class="boost-entry" id="boost-entry-{{ $boost->content_type }}-{{ $boost->content_id }}">
                    <div class="flex-grow-1">
                        <div class="fw-medium">{{ $boost->content_name ?? '#'.$boost->content_id }}</div>
                        <div class="d-flex gap-2 mt-1">
                            <span class="type-pill {{ $boost->content_type }}">{{ $boost->content_type }}</span>
                            <small class="text-danger">
                                <i class="ph ph-rocket-launch me-1"></i>+{{ number_format($boost->boost_plays) }} plays
                                &nbsp;·&nbsp;
                                <i class="ph ph-eye me-1"></i>+{{ number_format($boost->boost_views) }} views
                            </small>
                            @if($boost->entries > 1)
                            <small class="text-muted">({{ $boost->entries }} entries)</small>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-muted small text-center py-3 mb-0" id="noBoostsMsg">No active boosts yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
(() => {
    'use strict';

    const base    = '{{ url("app/statistics") }}';
    const csrfTok = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Search ──────────────────────────────────────────
    let searchTimeout = null;
    const searchInput   = document.getElementById('searchInput');
    const searchType    = document.getElementById('searchType');
    const searchResults = document.getElementById('searchResults');

    function doSearch() {
        const q = searchInput.value.trim();
        if (q.length < 2) {
            searchResults.innerHTML = '<p class="text-muted small text-center py-4">Type at least 2 characters to search</p>';
            return;
        }
        searchResults.innerHTML = '<p class="text-muted small text-center py-3">Searching…</p>';
        const qs = new URLSearchParams({ q, type: searchType.value }).toString();
        fetch(`${base}/booster/search?${qs}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(items => {
            if (!items.length) {
                searchResults.innerHTML = '<p class="text-muted small text-center py-4">No results found</p>';
                return;
            }
            searchResults.innerHTML = items.map(item => `
                <div class="search-result-item" data-type="${escHtml(item.content_type)}" data-id="${item.content_id}">
                    <div class="d-flex align-items-center gap-2">
                        <span class="type-pill ${escHtml(item.content_type)}">${escHtml(item.type_label)}</span>
                        <span class="fw-medium">${escHtml(item.name)}</span>
                    </div>
                    <div class="mt-1 d-flex gap-3">
                        <small class="text-muted"><i class="ph ph-play-circle me-1"></i>${item.real_plays.toLocaleString()} real plays</small>
                        ${item.boost_plays > 0 ? `<small class="text-danger"><i class="ph ph-rocket-launch me-1"></i>+${item.boost_plays.toLocaleString()} boosted (${item.boost_entries} ${item.boost_entries === 1 ? 'entry' : 'entries'})</small>` : ''}
                    </div>
                </div>
            `).join('');

            searchResults.querySelectorAll('.search-result-item').forEach(el => {
                el.addEventListener('click', () => {
                    searchResults.querySelectorAll('.search-result-item').forEach(x => x.classList.remove('selected'));
                    el.classList.add('selected');
                    loadEditor(el.dataset.type, parseInt(el.dataset.id), el.querySelector('.fw-medium').textContent.trim());
                });
            });
        })
        .catch(() => {
            searchResults.innerHTML = '<p class="text-danger small text-center py-4">Search failed</p>';
        });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(doSearch, 300);
    });
    searchType.addEventListener('change', doSearch);

    // ── Editor ──────────────────────────────────────────
    function loadEditor(ctype, cid, name) {
        document.getElementById('boostEditor').style.display = '';
        document.getElementById('noEditorPlaceholder').style.display = 'none';
        document.getElementById('editorTitle').textContent = name;

        const pill = document.getElementById('editorTypePill');
        pill.textContent = ctype;
        pill.className = `type-pill ${ctype}`;

        document.getElementById('f_content_type').value = ctype;
        document.getElementById('f_content_id').value   = cid;

        // Load stats
        const qs = new URLSearchParams({ content_type: ctype, content_id: cid }).toString();
        fetch(`${base}/booster/stats?${qs}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('realPlays').textContent    = data.real_plays.toLocaleString();
            document.getElementById('realViews').textContent    = data.real_views.toLocaleString();
            document.getElementById('f_boost_plays').value      = 0;
            document.getElementById('f_boost_views').value      = 0;
            document.getElementById('f_note').value             = '';
            renderHistory(data.history, data.boost_plays, data.boost_views, data.real_plays, data.real_views);
            updateDisplayNums();
        });
    }

    function updateDisplayNums() {
        const bPlays = parseInt(document.getElementById('f_boost_plays').value) || 0;
        const bViews = parseInt(document.getElementById('f_boost_views').value) || 0;
        const rPlays = parseInt(document.getElementById('realPlays').textContent.replace(/,/g, '')) || 0;
        const rViews = parseInt(document.getElementById('realViews').textContent.replace(/,/g, '')) || 0;
        const ht = document.getElementById('historyTotal');
        const stackedPlays = parseInt(ht.dataset.boostPlays || '0') || 0;
        const stackedViews = parseInt(ht.dataset.boostViews || '0') || 0;
        document.getElementById('displayPlays').textContent = (rPlays + stackedPlays + bPlays).toLocaleString();
        document.getElementById('displayViews').textContent = (rViews + stackedViews + bViews).toLocaleString();
    }

    document.getElementById('f_boost_plays').addEventListener('input', updateDisplayNums);
    document.getElementById('f_boost_views').addEventListener('input', updateDisplayNums);

    document.getElementById('clearEditor').addEventListener('click', () => {
        document.getElementById('boostEditor').style.display = 'none';
        document.getElementById('noEditorPlaceholder').style.display = '';
        document.querySelectorAll('.search-result-item').forEach(x => x.classList.remove('selected'));
    });

    document.getElementById('resetToReal').addEventListener('click', () => {
        const history = document.querySelectorAll('#historyList .history-entry');
        if (!history.length) return;
        if (!confirm('Delete ALL boost entries for this item?')) return;
        const ids = [...history].map(el => parseInt(el.dataset.id));
        Promise.all(ids.map(id =>
            fetch(`${base}/booster/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfTok,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(r => r.json())
        )).then(() => {
            const ctype = document.getElementById('f_content_type').value;
            const cid   = parseInt(document.getElementById('f_content_id').value);
            const name  = document.getElementById('editorTitle').textContent;
            loadEditor(ctype, cid, name);
            refreshActiveList();
            doSearch();
            showToast('All boosts cleared', 'success');
        });
    });

    // ── Save ──────────────────────────────────────────
    document.getElementById('boostForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        const body = new FormData(this);

        fetch(`${base}/booster/save`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfTok,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-plus me-1"></i>Add Boost Entry';
            if (data.success) {
                showToast('Boost entry added: ' + data.name, 'success');
                // Reload editor to show updated history and reset inputs
                const ctype = document.getElementById('f_content_type').value;
                const cid   = parseInt(document.getElementById('f_content_id').value);
                const name  = document.getElementById('editorTitle').textContent;
                loadEditor(ctype, cid, name);
                refreshActiveList();
                doSearch();
            } else {
                showToast('Failed to save boost', 'danger');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-plus me-1"></i>Add Boost Entry';
            showToast('Network error', 'danger');
        });
    });

    // ── Delete single boost entry ──────────────────────────
    window.deleteBoost = function(id) {
        if (!confirm('Remove this boost entry?')) return;
        fetch(`${base}/booster/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfTok,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // If editor is open, reload it to update history and totals
                const editor = document.getElementById('boostEditor');
                if (editor.style.display !== 'none') {
                    const ctype = document.getElementById('f_content_type').value;
                    const cid   = parseInt(document.getElementById('f_content_id').value);
                    const name  = document.getElementById('editorTitle').textContent;
                    loadEditor(ctype, cid, name);
                }
                // Also remove from active list if present
                const el = document.getElementById('boost-entry-' + id);
                if (el) el.remove();
                updateBoostCount();
                doSearch();
                showToast('Boost entry removed', 'success');
            }
        });
    };

    // ── Render boost history inside editor ─────────────────
    function renderHistory(history, totalBoostPlays, totalBoostViews, realPlays, realViews) {
        const section  = document.getElementById('boostHistory');
        const listEl   = document.getElementById('historyList');
        const totalEl  = document.getElementById('historyTotal');

        if (!history || !history.length) {
            section.style.display = 'none';
            totalEl.dataset.boostPlays = 0;
            totalEl.dataset.boostViews = 0;
            return;
        }

        section.style.display = '';
        totalEl.dataset.boostPlays = totalBoostPlays;
        totalEl.dataset.boostViews = totalBoostViews;
        totalEl.textContent = `${history.length} ${history.length === 1 ? 'entry' : 'entries'} · +${Number(totalBoostPlays).toLocaleString()} plays · +${Number(totalBoostViews).toLocaleString()} views`;

        listEl.innerHTML = history.map(h => {
            const d = new Date(h.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
            return `<div class="history-entry d-flex align-items-center gap-2 py-2 border-bottom border-secondary border-opacity-25" data-id="${h.id}" style="font-size:.82rem">
                <div class="flex-grow-1">
                    <span class="text-danger fw-semibold me-2">+${Number(h.boost_plays).toLocaleString()} plays</span>
                    <span class="text-info me-2">+${Number(h.boost_views).toLocaleString()} views</span>
                    ${h.note ? `<span class="text-muted fst-italic">${escHtml(h.note)}</span>` : ''}
                </div>
                <small class="text-muted me-2 text-nowrap">${escHtml(d)}</small>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" style="line-height:1.4" onclick="deleteBoost(${h.id})" title="Remove entry">
                    <i class="ph ph-trash" style="font-size:.8rem"></i>
                </button>
            </div>`;
        }).join('');
    }

    function refreshActiveList() {
        fetch(`${base}/booster`, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc    = parser.parseFromString(html, 'text/html');
            const newList = doc.getElementById('activeBoostsList');
            if (newList) {
                document.getElementById('activeBoostsList').innerHTML = newList.innerHTML;
                // Re-attach delete handlers (already window.deleteBoost so fine)
            }
            const newCount = doc.getElementById('activeBoostCount');
            if (newCount) document.getElementById('activeBoostCount').textContent = newCount.textContent;
        })
        .catch(() => {});
    }

    function updateBoostCount() {
        const count = document.getElementById('activeBoostsList').querySelectorAll('.boost-entry').length;
        document.getElementById('activeBoostCount').textContent = count;
    }

    // ── Toast ──────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const c = document.createElement('div');
        c.className = `alert alert-${type} position-fixed shadow`;
        c.style.cssText = 'top:1.5rem;right:1.5rem;z-index:9999;min-width:260px;';
        c.textContent = msg;
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 3000);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
@endpush
