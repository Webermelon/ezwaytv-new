@extends('backend.layouts.app')

@section('title', 'TV App Analytics')

@section('content')
<div class="card-main mb-5" style="max-width:1200px">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">TV App Analytics</h3>
            <p class="text-muted mb-0">Authenticated eZWay TV app users, installs, and device platforms.</p>
        </div>
        <div class="d-flex gap-2 align-items-end">
            <label class="mb-0 small">From<input id="analytics-from" type="date" class="form-control mt-1"></label>
            <label class="mb-0 small">To<input id="analytics-to" type="date" class="form-control mt-1"></label>
            <button id="analytics-apply" class="btn btn-primary">Apply</button>
        </div>
    </div>

    <div id="analytics-error" class="alert alert-danger d-none"></div>
    <div class="row g-3 mb-4" id="analytics-summary"></div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100"><div class="card-body">
                <h5 class="card-title">Daily activity</h5>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Active app users</th><th>App opens</th></tr></thead>
                    <tbody id="analytics-daily"><tr><td colspan="3" class="text-muted">Loading...</td></tr></tbody>
                </table></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h5 class="card-title">By platform</h5>
                <div id="analytics-platforms" class="d-grid gap-2"></div>
            </div></div>
        </div>
    </div>
    <p class="small text-muted mt-4 mb-0">Observed installs are first authenticated app launches reported to TV. Store downloads and installs that never launch require a Google Play and Apple App Store import.</p>
</div>
@endsection

@push('after-scripts')
<script>
(() => {
    const from = document.getElementById('analytics-from');
    const to = document.getElementById('analytics-to');
    const error = document.getElementById('analytics-error');
    const summary = document.getElementById('analytics-summary');
    const daily = document.getElementById('analytics-daily');
    const platforms = document.getElementById('analytics-platforms');
    const today = new Date().toISOString().slice(0, 10);
    const yearAgo = new Date(); yearAgo.setDate(yearAgo.getDate() - 364);
    from.value = yearAgo.toISOString().slice(0, 10); to.value = today;

    const labels = { observedInstalls: 'Observed installs', activeUsers: 'Active app users', inactiveUsers: 'Inactive app users', neverOpened: 'Installed, never opened', opens: 'App opens' };
    const escape = (value) => String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));

    async function load() {
        error.classList.add('d-none');
        summary.innerHTML = '<div class="text-muted">Loading analytics...</div>';
        try {
            const query = new URLSearchParams({ from: from.value, to: to.value });
            const response = await fetch('/api/admin/app-analytics?' + query, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Could not load app analytics.');
            summary.innerHTML = Object.entries(labels).map(([key, label]) => `<div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">${label}</div><div class="fs-3 fw-bold">${Number(payload.summary[key] || 0).toLocaleString()}</div></div></div></div>`).join('');
            daily.innerHTML = payload.daily.length ? payload.daily.map((row) => `<tr><td>${escape(row.date)}</td><td>${row.activeUsers}</td><td>${row.opens}</td></tr>`).join('') : '<tr><td colspan="3" class="text-muted">No activity in this range.</td></tr>';
            platforms.innerHTML = Object.entries(payload.platforms).map(([platform, count]) => `<div class="d-flex justify-content-between border-bottom pb-2"><span class="text-capitalize">${escape(platform)}</span><strong>${Number(count).toLocaleString()}</strong></div>`).join('');
        } catch (exception) {
            summary.innerHTML = ''; daily.innerHTML = '<tr><td colspan="3" class="text-muted">No data available.</td></tr>';
            error.textContent = exception.message; error.classList.remove('d-none');
        }
    }
    document.getElementById('analytics-apply').addEventListener('click', load);
    load();
})();
</script>
@endpush
