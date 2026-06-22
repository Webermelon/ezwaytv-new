@extends('backend.layouts.app')

@section('title')
    Statistics &mdash; Analytics Dashboard
@endsection

@push('before-styles')
<style>
.stat-card {
    background: var(--bs-card-bg, #1a1a2e);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255,255,255,0.07);
    position: relative;
    overflow: hidden;
    transition: transform .15s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card .stat-icon {
    width: 48px; height: 48px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    margin-bottom: .75rem;
}
.stat-card .stat-value { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-card .stat-label { font-size: .8rem; opacity: .6; margin-top: .25rem; text-transform: uppercase; letter-spacing: .05em; }
.stat-card .stat-change {
    font-size: .8rem; margin-top: .5rem;
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 8px; border-radius: 20px;
}
.stat-change.up   { background: rgba(40,199,111,.15); color: #28c76f; }
.stat-change.down { background: rgba(234,84,85,.15);  color: #ea5455; }
.stat-change.neutral { background: rgba(255,255,255,.05); color: #aaa; }

.chart-card {
    background: var(--bs-card-bg, #1a1a2e);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    padding: 1.5rem;
}

.period-select { min-width: 120px; }

.stat-table th, .stat-table td { vertical-align: middle; }
.stat-table .rank-badge { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; }
.badge-1 { background: #ffd70033; color: #ffd700; }
.badge-2 { background: #c0c0c033; color: #c0c0c0; }
.badge-3 { background: #cd7f3233; color: #cd7f32; }
.badge-n { background: rgba(255,255,255,0.07); color: #aaa; }

.type-pill { font-size: .7rem; padding: 2px 8px; border-radius: 20px; background: rgba(255,255,255,0.08); }
.type-pill.video         { background: rgba(147,51,234,.2); color: #a78bfa; }
.type-pill.entertainment { background: rgba(59,130,246,.2); color: #60a5fa; }
.type-pill.episode       { background: rgba(16,185,129,.2); color: #34d399; }
.type-pill.livetv        { background: rgba(245,158,11,.2); color: #fbbf24; }
.type-pill.ondemand_video,
.type-pill.ondemand_channel { background: rgba(236,72,153,.2); color: #f472b6; }

.loading-spinner { display: flex; align-items: center; justify-content: center; min-height: 120px; }
</style>
@endpush

@section('content')

{{-- ── Header ────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h3 class="mb-0 fw-bold">
            <i class="ph ph-chart-bar me-2"></i>Analytics &amp; Statistics
        </h3>
        <p class="text-muted mb-0 small">Track views, plays, watch time, and audience insights.</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select id="globalPeriod" class="form-select form-select-sm period-select">
            <option value="today">Today</option>
            <option value="week" selected>Last 7 Days</option>
            <option value="month">Last 30 Days</option>
            <option value="year">Last Year</option>
            <option value="all" >All Time</option>
        </select>
        <a href="{{ route('backend.statistics.settings') }}" class="btn btn-sm btn-dark" style="display:none">
            <i class="ph ph-gear me-1"></i>Settings
        </a>
        @if(auth()->user()->hasRole('admin'))
        <span id="boostBadge" style="display:none" class="badge bg-danger ms-1 align-self-center">
            <i class="ph ph-rocket-launch me-1"></i>Boost Active
        </span>
        @endif
    </div>
</div>

{{-- ── Stat Cards ────────────────────────────────────── --}}
<div class="row g-3 mb-4" id="statCards">
    <div class="col-sm-6 col-xl">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:rgba(99,102,241,.2); color:#818cf8;">
                <i class="ph ph-play-circle"></i>
            </div>
            <div class="stat-value" id="val-plays">—</div>
            <div class="stat-label">Total Plays</div>
            <span class="stat-change neutral" id="chg-plays" style="display:none"></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:rgba(236,72,153,.2); color:#f472b6;">
                <i class="ph ph-eye"></i>
            </div>
            <div class="stat-value" id="val-pageviews">—</div>
            <div class="stat-label">Page Views</div>
            <span class="stat-change neutral" id="chg-pageviews" style="display:none"></span>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:rgba(20,184,166,.2); color:#2dd4bf;">
                <i class="ph ph-users"></i>
            </div>
            <div class="stat-value" id="val-visitors">—</div>
            <div class="stat-label">Unique Visitors</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:rgba(245,158,11,.2); color:#fbbf24;">
                <i class="ph ph-clock"></i>
            </div>
            <div class="stat-value" id="val-hours">—</div>
            <div class="stat-label">Total Watch Time</div>
        </div>
    </div>
    <!-- Total Video/Channel Views card removed per request -->
</div>

{{-- ── Main Chart + Top Content ──────────────────────── --}}
<div class="row g-3 mb-4">
    {{-- Views / Plays chart --}}
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold">Views &amp; Plays Over Time</h6>
                <div class="d-flex gap-2">
                    <select id="chartType" class="form-select form-select-sm" style="width:auto">
                        <option value="both">Views + Plays</option>
                        <option value="views">Views only</option>
                        <option value="plays">Plays only</option>
                    </select>
                </div>
            </div>
            <div id="mainChart"></div>
        </div>
    </div>

    {{-- Device donut --}}
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h6 class="mb-3 fw-semibold">Device Breakdown</h6>
            <div id="deviceChart"></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Platform donut --}}
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h6 class="mb-3 fw-semibold">Platform</h6>
            <div id="platformChart"></div>
        </div>
    </div>

    {{-- Traffic sources --}}
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h6 class="mb-3 fw-semibold">Traffic Sources</h6>
            <div id="trafficChart"></div>
        </div>
    </div>

    {{-- Countries --}}
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h6 class="mb-3 fw-semibold">Top Countries</h6>
            <div id="countriesChart"></div>
        </div>
    </div>
</div>

{{-- ── Top Content Table ─────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold">Top Content</h6>
                <div class="d-flex gap-2 flex-wrap">
                    <select id="topMetric" class="form-select form-select-sm" style="width:auto">
                        <option value="views">By Views</option>
                        <option value="plays">By Plays</option>
                    </select>
                    <select id="topType" class="form-select form-select-sm" style="width:auto">
                        <option value="all">All Types</option>
                        <option value="video">Videos</option>
                        <option value="entertainment">Movies &amp; TV</option>
                        <option value="episode">Episodes</option>
                        <option value="livetv" selected>Live TV</option>
                        <option value="ondemand_video">On Demand Videos</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table stat-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Content</th>
                            <th>Type</th>
                            <th class="text-end">Count</th>
                        </tr>
                    </thead>
                    <tbody id="topContentBody">
                        <tr><td colspan="4" class="text-center py-4 text-muted">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Top Users Table ───────────────────────────────── --}}
<div class="row g-3 mb-4" style="display:none">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-3 fw-semibold">Top Viewers</h6>
            <div class="table-responsive">
                <table class="table stat-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>User</th>
                            <th class="text-end">Plays</th>
                            <th class="text-end">Watch Hours</th>
                        </tr>
                    </thead>
                    <tbody id="topUsersBody">
                        <tr><td colspan="4" class="text-center py-4 text-muted">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── On Demand Channel Stats ───────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-semibold"><i class="ph ph-television me-1"></i>On Demand Channels</h6>
                    <div class="text-muted small" id="ondemandTotals">Separate On Demand totals loading…</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table stat-table mb-0">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th class="text-end">Video Views</th>
                            <th class="text-end">Other Views</th>
                            <th class="text-end">Total Views</th>
                            <th class="text-end">Plays</th>
                            <th class="text-end">Unique Visitors</th>
                            <th class="text-end">Watch Time</th>
                        </tr>
                    </thead>
                    <tbody id="ondemandBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Individual Page Visits ────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold"><i class="ph ph-browsers me-1"></i>Individual Page Visits</h6>
                <span class="text-muted small" id="pvTotal"></span>
            </div>
            <div class="table-responsive">
                <table class="table stat-table mb-0" style="font-size:.84rem">
                    <thead>
                        <tr>
                            <th>Page / Content</th>
                            <th>Route</th>
                            <th>User</th>
                            <th>Device</th>
                            <th>Browser / OS</th>
                            <th>Country</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody id="pvBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button class="btn btn-sm btn-outline-secondary" id="pvPrev" disabled>‹ Prev</button>
                <span class="text-muted small" id="pvPage"></span>
                <button class="btn btn-sm btn-outline-secondary" id="pvNext">Next ›</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Individual Play Events ────────────────────────── --}}
<div class="row g-3 mb-5">
    <div class="col-12">
        <div class="chart-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold"><i class="ph ph-play-circle me-1"></i>Individual Play Events</h6>
                <span class="text-muted small" id="peTotal"></span>
            </div>
            <div class="table-responsive">
                <table class="table stat-table mb-0" style="font-size:.84rem">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Device</th>
                            <th>Platform</th>
                            <th>Watch Time</th>
                            <th>Country</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody id="peBody">
                        <tr><td colspan="8" class="text-center py-4 text-muted">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button class="btn btn-sm btn-outline-secondary" id="pePrev" disabled>‹ Prev</button>
                <span class="text-muted small" id="pePage"></span>
                <button class="btn btn-sm btn-outline-secondary" id="peNext">Next ›</button>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="snackbar" id="snackbar">
    <div class="d-flex justify-content-around align-items-center">
        <p class="mb-0">{{ session('success') }}</p>
        <a href="#" class="dismiss-link text-decoration-none text-success" onclick="dismissSnackbar(event)">Dismiss</a>
    </div>
</div>
@endif

@endsection

@push('after-scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
(() => {
    'use strict';

    const base = '{{ url("app/statistics") }}';

    // ── Shared state ──────────────────────────────────────
    const getPeriod = () => document.getElementById('globalPeriod').value;

    // ── Helpers ───────────────────────────────────────────
    function fetchJson(url, params = {}) {
        const qs = new URLSearchParams(params).toString();
        return fetch(`${url}?${qs}`, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).catch(err => { console.error('fetchJson error', url, err); return null; });
    }

    // ── ApexCharts base config ────────────────────────────
    function baseLineChart(el, series, categories) {
        return new ApexCharts(document.querySelector(el), {
            series,
            chart: { type: 'area', height: 280, background: 'transparent', toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 400 } },
            colors: ['#818cf8', '#f472b6'],
            stroke: { curve: 'smooth', width: [2, 2] },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.0 } },
            xaxis: { categories, labels: { style: { colors: '#8e9ab5', fontSize: '11px' } }, axisBorder: { show: false } },
            yaxis: { labels: { style: { colors: '#8e9ab5' } } },
            grid: { borderColor: 'rgba(255,255,255,.06)' },
            tooltip: { theme: 'dark' },
            legend: { show: true, position: 'top', labels: { colors: '#ccc' } },
            theme: { mode: 'dark' },
        });
    }

    function baseDonutChart(el, series, labels) {
        return new ApexCharts(document.querySelector(el), {
            series,
            labels,
            chart: { type: 'donut', height: 260, background: 'transparent' },
            colors: ['#818cf8','#f472b6','#2dd4bf','#fbbf24','#60a5fa','#a78bfa','#34d399'],
            legend: { position: 'bottom', labels: { colors: '#ccc' } },
            dataLabels: { style: { fontSize: '11px' } },
            tooltip: { theme: 'dark' },
            theme: { mode: 'dark' },
            plotOptions: { pie: { donut: { size: '60%' } } },
        });
    }

    function baseBarChart(el, series, categories) {
        return new ApexCharts(document.querySelector(el), {
            series,
            chart: { type: 'bar', height: 250, background: 'transparent', toolbar: { show: false } },
            colors: ['#818cf8'],
            xaxis: { categories, labels: { style: { colors: '#8e9ab5', fontSize: '11px' } } },
            yaxis: { labels: { style: { colors: '#8e9ab5' } } },
            grid: { borderColor: 'rgba(255,255,255,.06)' },
            tooltip: { theme: 'dark' },
            legend: { show: false },
            theme: { mode: 'dark' },
            plotOptions: { bar: { borderRadius: 4, horizontal: true } },
        });
    }

    // ── Stat Cards ────────────────────────────────────────
    function loadOverview() {
        const period = getPeriod();
        fetchJson(`${base}/overview`, { period }).then(data => {
            document.getElementById('val-plays').textContent     = data.total_plays;
            document.getElementById('val-pageviews').textContent = data.page_views;
            document.getElementById('val-visitors').textContent  = data.unique_visitors;
            document.getElementById('val-hours').textContent     = `${data.watch_hours} h`;
            const viewsEl = document.getElementById('val-views');
            if (viewsEl) viewsEl.textContent = data.total_views;

            const boostBadge = document.getElementById('boostBadge');
            if (boostBadge) boostBadge.style.display = data.boost_active ? 'inline-block' : 'none';

            const setChange = (elId, val) => {
                if (val === null) return;
                const el = document.getElementById(elId);
                if (!el) return;
                const dir = val > 0 ? 'up' : (val < 0 ? 'down' : 'neutral');
                const icon = val > 0 ? '↑' : (val < 0 ? '↓' : '—');
                el.className = `stat-change ${dir}`;
                el.textContent = `${icon} ${Math.abs(val)}% vs prev`;
                el.style.display = 'inline-flex';
            };

            setChange('chg-views',     data.views_change);
            setChange('chg-plays',     data.plays_change);
            setChange('chg-pageviews', data.page_views_change);
        });
    }

    // ── Main Line Chart ───────────────────────────────────
    let mainChartInstance = null;
    function loadMainChart() {
        const period    = getPeriod();
        const chartType = document.getElementById('chartType').value;

        fetchJson(`${base}/chart`, { period, type: chartType }).then(data => {
            const series = [];
            if (chartType !== 'plays')  series.push({ name: 'Views', data: data.views });
            if (chartType !== 'views')  series.push({ name: 'Plays', data: data.plays });

            if (mainChartInstance) {
                mainChartInstance.updateOptions({ xaxis: { categories: data.labels } });
                mainChartInstance.updateSeries(series);
            } else {
                mainChartInstance = baseLineChart('#mainChart', series, data.labels);
                mainChartInstance.render();
            }
        });
    }

    // ── Device Donut ──────────────────────────────────────
    let deviceChartInstance = null;
    function loadDeviceChart() {
        const period = getPeriod();
        fetchJson(`${base}/devices`, { period }).then(data => {
            if (deviceChartInstance) {
                deviceChartInstance.updateOptions({ labels: data.labels });
                deviceChartInstance.updateSeries(data.values);
            } else {
                deviceChartInstance = baseDonutChart('#deviceChart', data.values, data.labels);
                deviceChartInstance.render();
            }
        });
    }

    // ── Platform Donut ────────────────────────────────────
    let platformChartInstance = null;
    function loadPlatformChart() {
        const period = getPeriod();
        fetchJson(`${base}/platforms`, { period }).then(data => {
            if (platformChartInstance) {
                platformChartInstance.updateOptions({ labels: data.labels });
                platformChartInstance.updateSeries(data.values);
            } else {
                platformChartInstance = baseDonutChart('#platformChart', data.values, data.labels);
                platformChartInstance.render();
            }
        });
    }

    // ── Traffic Sources ───────────────────────────────────
    let trafficChartInstance = null;
    function loadTrafficChart() {
        const period = getPeriod();
        fetchJson(`${base}/traffic`, { period }).then(data => {
            if (trafficChartInstance) {
                trafficChartInstance.updateOptions({ labels: data.labels });
                trafficChartInstance.updateSeries(data.values);
            } else {
                trafficChartInstance = baseDonutChart('#trafficChart', data.values, data.labels);
                trafficChartInstance.render();
            }
        });
    }

    // ── Countries Bar ─────────────────────────────────────
    let countriesChartInstance = null;
    function loadCountriesChart() {
        const period = getPeriod();
        fetchJson(`${base}/countries`, { period, limit: 8 }).then(data => {
            const series = [{ name: 'Count', data: data.values }];
            const categories = data.labels;
            if (countriesChartInstance) {
                countriesChartInstance.updateOptions({ xaxis: { categories } });
                countriesChartInstance.updateSeries(series);
            } else {
                countriesChartInstance = baseBarChart('#countriesChart', series, categories);
                countriesChartInstance.render();
            }
        });
    }

    // ── Top Content Table ─────────────────────────────────
    function loadTopContent() {
        const period = getPeriod();
        const metric = document.getElementById('topMetric').value;
        const type   = document.getElementById('topType').value;

        const tbody = document.getElementById('topContentBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Loading…</td></tr>';

        fetchJson(`${base}/top-content`, { period, metric, content_type: type }).then(data => {
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No data</td></tr>';
                return;
            }
            tbody.innerHTML = data.map((row, i) => {
                const rankClass = i === 0 ? 'badge-1' : i === 1 ? 'badge-2' : i === 2 ? 'badge-3' : 'badge-n';
                const nameHtml = row.url
                    ? `<a href="${escHtml(row.url)}" target="_blank" class="text-decoration-none fw-medium">${escHtml(row.name)}</a>`
                    : `<span class="fw-medium">${escHtml(row.name)}</span>`;
                return `<tr>
                    <td><span class="rank-badge ${rankClass}">${i + 1}</span></td>
                    <td>${nameHtml}</td>
                    <td><span class="type-pill ${escHtml(row.content_type)}">${escHtml(row.content_type)}</span></td>
                    <td class="text-end fw-bold">${Number(row.total).toLocaleString()}</td>
                </tr>`;
            }).join('');
        });
    }

    // ── Top Users Table ───────────────────────────────────
    function loadTopUsers() {
        const period = getPeriod();
        const tbody  = document.getElementById('topUsersBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Loading…</td></tr>';

        fetchJson(`${base}/users`, { period }).then(data => {
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No data</td></tr>';
                return;
            }
            tbody.innerHTML = data.map((row, i) => {
                const rankClass = i === 0 ? 'badge-1' : i === 1 ? 'badge-2' : i === 2 ? 'badge-3' : 'badge-n';
                return `<tr>
                    <td><span class="rank-badge ${rankClass}">${i + 1}</span></td>
                    <td>
                        <div class="fw-medium">${escHtml(row.name)}</div>
                        <div class="text-muted small">${escHtml(row.email)}</div>
                    </td>
                    <td class="text-end">${Number(row.plays).toLocaleString()}</td>
                    <td class="text-end">${row.watch_hours} h</td>
                </tr>`;
            }).join('');
        });
    }

    // ── On Demand Channels ───────────────────────────────
    function loadOndemand() {
        const period = getPeriod();
        const tbody = document.getElementById('ondemandBody');
        const totalsEl = document.getElementById('ondemandTotals');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Loading…</td></tr>';

        fetchJson(`${base}/ondemand`, { period, limit: 10 }).then(data => {
            if (!data || data.migration_required) {
                totalsEl.textContent = 'Run the latest Statistics migration to enable On Demand channel reporting.';
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Migration required</td></tr>';
                return;
            }

            totalsEl.textContent = `${Number(data.totals.total_views || 0).toLocaleString()} total views, ${Number(data.totals.plays || 0).toLocaleString()} plays, ${Number(data.totals.watch_hours || 0).toLocaleString()} watch hours`;

            if (!data.channels.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No On Demand data yet</td></tr>';
                return;
            }

            tbody.innerHTML = data.channels.map(row => {
                const name = row.url
                    ? `<a href="${escHtml(row.url)}" target="_blank" class="text-decoration-none fw-medium">${escHtml(row.name)}</a>`
                    : `<span class="fw-medium">${escHtml(row.name)}</span>`;
                return `<tr>
                    <td>${name}<div class="text-muted small">${Number(row.videos || 0).toLocaleString()} tracked videos</div></td>
                    <td class="text-end">${Number(row.display_views ?? row.views ?? 0).toLocaleString()}</td>
                    <td class="text-end">${Number(row.display_profile_views ?? row.profile_views ?? 0).toLocaleString()}</td>
                    <td class="text-end">${Number((row.display_views ?? row.views ?? 0) + (row.display_profile_views ?? row.profile_views ?? 0)).toLocaleString()}</td>
                    <td class="text-end">${Number(row.display_plays ?? row.plays ?? 0).toLocaleString()}</td>
                    <td class="text-end">${Number(row.display_unique_visitors ?? row.unique_visitors ?? 0).toLocaleString()}</td>
                    <td class="text-end">${escHtml(row.watch_time || '00:00:00')}</td>
                </tr>`;
            }).join('');
        });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str ?? ''));
        return d.innerHTML;
    }

    // ── Page Views Detail Table ───────────────────────────
    const PV_LIMIT = 10;
    let pvOffset = 0, pvTotal = 0;

    function loadPageViews() {
        const period = getPeriod();
        const tbody  = document.getElementById('pvBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Loading…</td></tr>';

        fetchJson(`${base}/page-views`, { period, limit: PV_LIMIT, offset: pvOffset }).then(data => {
            if (!data) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Failed to load data</td></tr>'; return; }
            pvTotal = data.total;
            document.getElementById('pvTotal').textContent = `${pvTotal.toLocaleString()} total records`;
            const page = Math.floor(pvOffset / PV_LIMIT) + 1;
            const pages = Math.ceil(pvTotal / PV_LIMIT) || 1;
            document.getElementById('pvPage').textContent = `Page ${page} of ${pages}`;
            document.getElementById('pvPrev').disabled = pvOffset === 0;
            document.getElementById('pvNext').disabled = pvOffset + PV_LIMIT >= pvTotal;

            if (!data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No data for this period</td></tr>';
                return;
            }
            tbody.innerHTML = data.rows.map(row => {
                const pageLabel = row.page_name || row.page_url || row.route_name || '—';
                const contentLabel = row.content_name ? `<span class="text-muted small">${escHtml(row.content_type)}: ${escHtml(row.content_name)}</span>` : '';
                const user = row.user_name ? `<div>${escHtml(row.user_name)}</div><div class="text-muted small">${escHtml(row.user_email || '')}</div>` : `<span class="text-muted">Guest</span>`;
                const device = `${escHtml(row.device_type || '—')}`;
                const browserOs = `${escHtml(row.browser || '—')} / ${escHtml(row.os || '—')}`;
                const dt = row.created_at ? row.created_at.replace('T', ' ').substring(0, 16) : row.view_date;
                return `<tr>
                    <td><div class="fw-medium">${escHtml(pageLabel)}</div>${contentLabel}</td>
                    <td><code class="small">${escHtml(row.route_name || '—')}</code></td>
                    <td>${user}</td>
                    <td><span class="type-pill ${escHtml(row.device_type || '')}">${device}</span></td>
                    <td>${browserOs}</td>
                    <td>${escHtml(row.country_code || '—')}</td>
                    <td class="text-nowrap">${escHtml(dt)}</td>
                </tr>`;
            }).join('');
        });
    }

    document.getElementById('pvPrev').addEventListener('click', () => { pvOffset = Math.max(0, pvOffset - PV_LIMIT); loadPageViews(); });
    document.getElementById('pvNext').addEventListener('click', () => { if (pvOffset + PV_LIMIT < pvTotal) { pvOffset += PV_LIMIT; loadPageViews(); } });

    // ── Play Events Detail Table ──────────────────────────
    const PE_LIMIT = 10;
    let peOffset = 0, peTotal = 0;

    function loadPlayEvents() {
        const period = getPeriod();
        const tbody  = document.getElementById('peBody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">Loading…</td></tr>';

        fetchJson(`${base}/play-events`, { period, limit: PE_LIMIT, offset: peOffset }).then(data => {
            if (!data) { tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-danger">Failed to load data</td></tr>'; return; }
            peTotal = data.total;
            document.getElementById('peTotal').textContent = `${peTotal.toLocaleString()} total records`;
            const page = Math.floor(peOffset / PE_LIMIT) + 1;
            const pages = Math.ceil(peTotal / PE_LIMIT) || 1;
            document.getElementById('pePage').textContent = `Page ${page} of ${pages}`;
            document.getElementById('pePrev').disabled = peOffset === 0;
            document.getElementById('peNext').disabled = peOffset + PE_LIMIT >= peTotal;

            if (!data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">No data for this period</td></tr>';
                return;
            }
            tbody.innerHTML = data.rows.map(row => {
                const contentLabel = row.content_name || `#${row.content_id}`;
                const user = row.user_name ? `<div>${escHtml(row.user_name)}</div><div class="text-muted small">${escHtml(row.user_email || '')}</div>` : `<span class="text-muted">Guest</span>`;
                const dt = row.created_at ? row.created_at.replace('T', ' ').substring(0, 16) : row.play_date;
                return `<tr>
                    <td class="fw-medium">${escHtml(contentLabel)}</td>
                    <td><span class="type-pill ${escHtml(row.content_type || '')}">${escHtml(row.content_type || '—')}</span></td>
                    <td>${user}</td>
                    <td>${escHtml(row.device_type || '—')}</td>
                    <td>${escHtml(row.platform || '—')}</td>
                    <td>${escHtml(row.watch_time || '—')}</td>
                    <td>${escHtml(row.country_code || '—')}</td>
                    <td class="text-nowrap">${escHtml(dt)}</td>
                </tr>`;
            }).join('');
        });
    }

    document.getElementById('pePrev').addEventListener('click', () => { peOffset = Math.max(0, peOffset - PE_LIMIT); loadPlayEvents(); });
    document.getElementById('peNext').addEventListener('click', () => { if (peOffset + PE_LIMIT < peTotal) { peOffset += PE_LIMIT; loadPlayEvents(); } });

    // ── Load All ──────────────────────────────────────────
    function loadAll() {
        pvOffset = 0;
        peOffset = 0;
        loadOverview();
        loadMainChart();
        loadDeviceChart();
        loadPlatformChart();
        loadTrafficChart();
        loadCountriesChart();
        loadTopContent();
        loadOndemand();
        loadPageViews();
        loadPlayEvents();
    }

    // ── Event Listeners ───────────────────────────────────
    document.getElementById('globalPeriod').addEventListener('change', loadAll);
    document.getElementById('chartType').addEventListener('change', loadMainChart);
    document.getElementById('topMetric').addEventListener('change', loadTopContent);
    document.getElementById('topType').addEventListener('change', loadTopContent);

    // Initial load
    loadAll();
})();
</script>
@endpush
