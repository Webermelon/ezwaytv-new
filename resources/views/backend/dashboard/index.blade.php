@extends('backend.layouts.app', ['isBanner' => false])


@section('title')
    {{ __('messages.dashboard') }}
@endsection

@section('content')
<style>
    /* Dashboard Loader Styles */
    #dashboard-loader {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background-color: var(--bs-body-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9998;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }

    /* Adjust for sidebar - loader should cover main content area only */
    @media (min-width: 992px) {
        #dashboard-loader {
            left: var(--sidebar-width); /* Adjust based on sidebar width - typically 280px */
        }
    }

    @media (max-width: 991px) {
        #dashboard-loader {
            left: 0; /* Full width on mobile when sidebar is hidden */
        }
    }

    #dashboard-loader.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    #dashboard-loader .loader-wrapper {
        text-align: center;
    }

    /* Hide dashboard content initially */
    #dashboard-content {
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    #dashboard-content.loaded {
        opacity: 1;
    }

    /* Hide zero value labels in Most Watched chart */
    #chart-most-watch .apexcharts-datalabel[data-value="0"],
    #chart-most-watch .apexcharts-datalabel:has-text("0"),
    #chart-most-watch .apexcharts-datalabels-group text[text-anchor="middle"] {
        display: none !important;
    }

    /* Hide zero labels using more specific selectors */
    #chart-most-watch .apexcharts-datalabel text {
        display: block;
    }

    /* Target zero labels specifically */
    #chart-most-watch .apexcharts-datalabel text[textContent="0"],
    #chart-most-watch .apexcharts-datalabel text:contains("0") {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
    }

    /* Hide zero value labels in New Subscribers chart */
    #chart-new-subscription .apexcharts-datalabel[data-value="0"],
    #chart-new-subscription .apexcharts-datalabel:has-text("0"),
    #chart-new-subscription .apexcharts-datalabels-group text[text-anchor="middle"] {
        display: none !important;
    }

    /* Hide zero labels using more specific selectors */
    #chart-new-subscription .apexcharts-datalabel text {
        display: block;
    }

    /* Target zero labels specifically */
    #chart-new-subscription .apexcharts-datalabel text[textContent="0"],
    #chart-new-subscription .apexcharts-datalabel text:contains("0") {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
    }

    /* Ensure all dashboard card icons are the same size */
    .card-icon {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .card-icon i {
        font-size: 2.5rem !important;
        line-height: 1 !important;
        width: 2.5rem !important;
        height: 2.5rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .card-icon svg {
        width: 2.5rem !important;
        height: 2.5rem !important;
        max-width: 2.5rem !important;
        max-height: 2.5rem !important;
    }
</style>

<div id="dashboard-loader">
    <div class="loader-wrapper">
        @php
            $loader_gif = GetSettingValue('loader_gif') ? setBaseUrlWithFileName(GetSettingValue('loader_gif'), 'image', 'logos') : asset('img/logo/loader.gif');
        @endphp
        <img src="{{ $loader_gif }}" alt="Loading..."
            class="loader-gif" width="100" height="100">
        {{-- <div class="loader-text">Loading...</div> --}}
    </div>
</div>

<div id="dashboard-content" class="container-fluid">
        <div class="d-flex align-items-center justify-content-end">
            <form action="{{ route('backend.home') }}" class="d-flex align-items-center gap-2">
                <div class="form-group my-0 ms-3">
                    <input type="text" name="date_range" value="{{ $dateRange ?? '' }}"
                        class="form-control dashboard-date-range" placeholder="Select Date Range" readonly="readonly">
                </div>
                <button type="submit" name="action" value="filter" class="btn btn-primary" data-bs-toggle="tooltip"
                    data-bs-title="{{ __('dashboard.submit_date_filter') }}">{{ __('dashboard.lbl_submit') }}</button>
                @if (request('action') === 'filter' || !empty($dateRange ?? null))
                    <button type="button" class="btn btn-secondary" data-bs-toggle="tooltip"
                        data-bs-title="{{ __('messages.reset') }}" onclick="window.location='{{ route('backend.home') }}'">
                        {{ __('messages.reset') }}
                    </button>
                @endif
            </form>
        </div>
        <div class="row mt-3">
            <div class="col-lg-8">
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <a href="{{ route('backend.users.index') }}">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div class="card-icon avatar-50 d-flex align-items-center justify-content-center">
                                            <i class="ph ph-user fs-1"></i>
                                        </div>
                                        <div id="chart-01"></div>
                                    </div>
                                    <div class="d-flex justify-content-between gap-3 mt-3">
                                        <div class="card-data">
                                            <p class="mb-2 fs-6">{{ __('dashboard.lbl_total_users') }}</p>
                                            <h1 class="m-0 display-6 fw-semibold">{{ $totalusers }}</h1>
                                        </div>
                                        <div
                                            class=" d-flex align-items-center {{ $usersChangeUp ? 'text-success' : 'text-danger' }}">
                                            <b>{{ $usersChangeUp ? '+' : '-' }}{{ $usersChangePercent }}%</b>
                                            <i class="ph {{ $usersChangeUp ? 'ph-arrow-up' : 'ph-arrow-down' }}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a>
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div class="card-icon avatar-50 d-flex align-items-center justify-content-center">
                                            <i class="ph ph-lockers fs-1"></i>
                                        </div>
                                        <div id="chart-05"></div>
                                    </div>
                                    <div class="d-flex justify-content-between gap-3 mt-3">
                                        <div class="card-data">
                                            <p class="mb-2 fs-6">{{ __('dashboard.lbl_storage_full') }}</p>
                                            <h1 class="m-0 display-6 fw-semibold">{{ $totalUsageFormatted }}</h1>
                                        </div>
                                        <div
                                            class=" d-flex align-items-center {{ $rentContentChangeUp ? 'text-success' : 'text-danger' }}">
                                            <b>{{ $rentContentChangeUp ? '+' : '-' }}{{ $rentContentChangePercent }}%</b>
                                            <i
                                                class="ph {{ $rentContentChangeUp ? 'ph-arrow-up' : 'ph-arrow-down' }}"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="{{ route('backend.author_channels.index') }}">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div class="card-icon avatar-50 d-flex align-items-center justify-content-center">
                                            <i class="ph ph-television fs-1"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between gap-3 mt-3">
                                        <div class="card-data">
                                            <p class="mb-2 fs-6">On Demand Channels</p>
                                            <h1 class="m-0 display-6 fw-semibold">{{ number_format($totalOnDemandChannels) }}</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="{{ route('backend.videos.index') }}">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div class="card-icon avatar-50 d-flex align-items-center justify-content-center">
                                            <i class="ph ph-video fs-1"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between gap-3 mt-3">
                                        <div class="card-data">
                                            <p class="mb-2 fs-6">On Demand Videos</p>
                                            <h1 class="m-0 display-6 fw-semibold">{{ number_format($totalOnDemandVideos) }}</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="{{ route('backend.tv-channel.index') }}">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between gap-3">
                                        <div class="card-icon avatar-50 d-flex align-items-center justify-content-center">
                                            <i class="ph ph-broadcast fs-1"></i>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between gap-3 mt-3">
                                        <div class="card-data">
                                            <p class="mb-2 fs-6">TV Channels</p>
                                            <h1 class="m-0 display-6 fw-semibold">{{ number_format($totalTvChannels) }}</h1>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-block card-stretch card-height">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('dashboard.lbl_top_genres') }}</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-top-genres"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-stats card-block card-height">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <h3 class="card-title">{{ __('dashboard.lbl_tot_revenue') }}</h3>
                        <div class="dropdown">
                            <button class="btn btn-dark dropdown-toggle total_revenue" type="button"
                                id="dropdownTotalRevenue" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ __('dashboard.year') }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-soft-primary sub-dropdown"
                                aria-labelledby="dropdownTotalRevenue">
                                <li><a class="revenue-dropdown-item dropdown-item"
                                        data-type="Year">{{ __('dashboard.year') }}</a></li>
                                <li><a class="revenue-dropdown-item dropdown-item"
                                        data-type="Month">{{ __('dashboard.month') }}</a></li>
                                <li><a class="revenue-dropdown-item dropdown-item"
                                        data-type="Week">{{ __('dashboard.week') }}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="chart-top-revenue"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-stats card-block card-height">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <h3 class="card-title">{{ __('dashboard.new_subscribers') }}</h3>
                        <div class="dropdown">
                            <button class="btn btn-dark dropdown-toggle total_subscribers" type="button"
                                id="dropdownNewSubscribers" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ __('dashboard.year') }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-soft-primary sub-dropdown"
                                aria-labelledby="dropdownNewSubscribers">
                                <li><a class="subscribers-dropdown-item dropdown-item"
                                        data-type="Year">{{ __('dashboard.year') }}</a></li>
                                <li><a class="subscribers-dropdown-item dropdown-item"
                                        data-type="Month">{{ __('dashboard.month') }}</a></li>
                                <li><a class="subscribers-dropdown-item dropdown-item"
                                        data-type="Week">{{ __('dashboard.week') }}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="chart-new-subscription"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-block card-height">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <h3 class="card-title">{{ __('dashboard.lbl_most_watched') }}</h3>
                        <div class="dropdown">
                            <button class="btn btn-dark dropdown-toggle most_watch" type="button" id="dropdownMostWatch"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                {{ __('dashboard.year') }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-soft-primary sub-dropdown"
                                aria-labelledby="dropdownMostWatch">
                                <li><a class="mostwatch-dropdown-item dropdown-item"
                                        data-type="Year">{{ __('dashboard.year') }}</a></li>
                                <li><a class="mostwatch-dropdown-item dropdown-item"
                                        data-type="Month">{{ __('dashboard.month') }}</a></li>
                                <li><a class="mostwatch-dropdown-item dropdown-item"
                                        data-type="Week">{{ __('dashboard.week') }}</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="chart-most-watch"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-stats card-block card-height">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <h3 class="card-title">{{ __('customer.reviews') }}</h3>
                        <a href="{{ route('backend.reviews.index') }}">{{ __('dashboard.view_all') }}</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <th>{{ __('dashboard.name') }}</th>
                                    <th>{{ __('dashboard.date') }}</th>
                                    <th>{{ __('dashboard.category') }}</th>
                                    <th>{{ __('dashboard.rating') }}</th>
                                </thead>
                                <tbody>
                                    @if ($reviewData)
                                        @foreach ($reviewData as $review)
                                            <tr>
                                                <td class="d-flex gap-3 align-items-center">
                                                    @if ($review->user)
                                                        <a href="{{ url('/app/users/details/' . $review->user->id) }}"
                                                            class="d-flex gap-3 align-items-center text-decoration-none">
                                                            <img src="{{ setBaseUrlWithFileName($review->user->file_url, 'image', 'users') ?? default_user_avatar() }}"
                                                                alt="avatar" class="avatar avatar-40 rounded-pill">
                                                            <div class="text-start">
                                                                <h6 class="m-0">
                                                                    {{ $review->user->first_name . ' ' . $review->user->last_name }}
                                                                </h6>
                                                                <small
                                                                    class="text-white">{{ $review->user->email }}</small>
                                                            </div>
                                                        </a>
                                                    @else
                                                        <div class="d-flex gap-3 align-items-center">
                                                            <img src="{{ default_user_avatar() }}" alt="avatar"
                                                                class="avatar avatar-40 rounded-pill">
                                                            <div class="text-start">
                                                                <h6 class="m-0">{{ default_user_name() }}</h6>
                                                                <small class="text-white">--</small>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>

                                                <td>
                                                    @php
                                                        if ($review->created_at) {
                                                            $defaultFormat =
                                                                \App\Models\Setting::where(
                                                                    'name',
                                                                    'default_date_format',
                                                                )
                                                                    ->where('datatype', 'misc')
                                                                    ->value('val') ?? 'jS F Y';
                                                            echo \Carbon\Carbon::parse($review->created_at)
                                                                ->locale(app()->getLocale())
                                                                ->translatedFormat($defaultFormat);
                                                        } else {
                                                            echo '';
                                                        }
                                                    @endphp
                                                </td>
                                                <td>
                                                    @php
                                                        $type = optional($review->entertainment)->type;
                                                        if ($type == 'movie') {
                                                            echo __('messages.lbl_movies');
                                                        } elseif ($type == 'tvshow') {
                                                            echo __('messages.lbl_tvshows');
                                                        } else {
                                                            echo ucfirst($type ?? '');
                                                        }
                                                    @endphp
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <div class="star-rating">
                                                            @for ($i = 1; $i <= 5; $i++)
                                                                <span
                                                                    class="star {{ $i <= $review->rating ? 'filled' : '' }}">
                                                                    <i class="ph ph-fill ph-star"></i>
                                                                </span>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                {{ __('messages.no_data_available') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="card card-block card-height">
                            <div class="card-header card-header-primary">
                                <h3 class="card-title">{{ __('dashboard.lbl_top_rated') }}</h3>
                            </div>
                            <div class="card-body">
                                <div id="chart-top-rated"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8 col-md-6">
                        <div class="card card-block card-height">
                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <h3 class="card-title">{{ __('dashboard.transaction_history') }}</h3>
                                <a href="{{ route('backend.subscriptions.index') }}">{{ __('dashboard.view_all') }}</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead class="text-primary">
                                            <th>{{ __('dashboard.name') }}</th>
                                            <th>{{ __('dashboard.date') }}</th>
                                            <th>{{ __('dashboard.plan') }}</th>
                                            <th>{{ __('dashboard.amount') }}</th>
                                            <th>{{ __('dashboard.duration') }}</th>
                                            <th>{{ __('dashboard.payment_method') }}</th>
                                        </thead>
                                        <tbody>

                                            @foreach ($subscriptionData as $subscription)
                                                <tr>
                                                    <td>
                                                        <a href="{{ $subscription->user ? route('backend.users.details', $subscription->user->id) : '#' }}"
                                                            class="d-flex gap-3 align-items-center text-decoration-none text-dark {{ $subscription->user ? '' : 'disabled' }}">
                                                            <img src="{{ setBaseUrlWithFileName(optional($subscription->user)->file_url, 'image', 'users') ?? default_user_avatar() }}"
                                                                alt="avatar" class="avatar avatar-40 rounded-pill">
                                                            <div class="text-start">
                                                                <h6 class="m-0">
                                                                    {{ optional($subscription->user)->first_name . ' ' . optional($subscription->user)->last_name ?? default_user_name() }}
                                                                </h6>
                                                                <small class="text-dark">
                                                                    <!-- ensure email is white or dark, depending on your theme -->
                                                                    {{ optional($subscription->user)->email ?? '--' }}
                                                                </small>
                                                            </div>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        @if($subscription->is_manual == 1)
                                                            {{ $subscription->start_date ? formatDate($subscription->start_date) : '--' }}
                                                        @else
                                                            {{ ($subscription->subscription_transaction && $subscription->subscription_transaction->created_at) ? formatDate($subscription->subscription_transaction->created_at) : '--' }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $subscription->name }}</td>
                                                    <td>{{ Currency::format($subscription->total_amount) }}</td>
                                                    @php
                                                        $unit = \Illuminate\Support\Str::plural($subscription->type, $subscription->duration);
                                                    @endphp
                                                    <td>{{ $subscription->duration . ' ' . $unit ?? '-' . ' ' . optional($subscription->plan)->duration ?? '-' }}
                                                    </td>
                                                    <td>{{ ucfirst(optional($subscription->subscription_transaction)->payment_type) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @if ($subscriptionData->isEmpty())
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        {{ __('messages.no_data_available') }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



@push('after-scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // Chart loading tracker
        (function() {
            const chartLoadTracker = {
                charts: {
                    'chart-01': false,
                    'chart-02': false,
                    'chart-03': false,
                    'chart-04': false,
                    'chart-05': false,
                    'chart-06': false,
                    'chart-07': false,
                    'chart-08': false,
                    'chart-09': false,
                    'chart-top-genres': false,
                    'chart-top-revenue': false,
                    'chart-new-subscription': false,
                    'chart-top-rated': false,
                    'chart-most-watch': false
                },

                markChartLoaded: function(chartId) {
                    if (this.charts.hasOwnProperty(chartId)) {
                        this.charts[chartId] = true;
                        this.checkAllLoaded();
                    }
                },

                checkAllLoaded: function() {
                    const allLoaded = Object.values(this.charts).every(loaded => loaded === true);
                    if (allLoaded) {
                        this.hideLoader();
                    }
                },

                hideLoader: function() {
                    const loader = document.getElementById('dashboard-loader');
                    const content = document.getElementById('dashboard-content');

                    if (loader) {
                        loader.classList.add('hidden');
                        setTimeout(() => {
                            if (loader.parentNode) {
                                loader.remove();
                            }
                        }, 400);
                    }

                    if (content) {
                        content.classList.add('loaded');
                    }
                },

                markMissingChartsLoaded: function() {
                    Object.keys(this.charts).forEach((chartId) => {
                        if (!document.getElementById(chartId)) {
                            this.charts[chartId] = true;
                        }
                    });
                    this.checkAllLoaded();
                }
            };

            // Make tracker globally accessible
            window.chartLoadTracker = chartLoadTracker;
            chartLoadTracker.markMissingChartsLoaded();

            // Fallback timeout (hide loader after 10 seconds max)
            setTimeout(function() {
                if (document.getElementById('dashboard-loader')) {
                    chartLoadTracker.hideLoader();
                }
            }, 10000);
        })();

        // Debug: log all dynamic dashboard data
        console.log('Dashboard percentages', {
            users: {
                percent: @json($usersChangePercent ?? 0),
                up: @json($usersChangeUp ?? true)
            },
            subscribers: {
                percent: @json($subsChangePercent ?? 0),
                up: @json($subsChangeUp ?? true)
            },
            soonToExpire: {
                percent: @json($soonExpireChangePercent ?? 0),
                up: @json($soonExpireChangeUp ?? false)
            },
            reviews: {
                percent: @json($reviewsChangePercent ?? 0),
                up: @json($reviewsChangeUp ?? true)
            },
            subscriptionRevenue: {
                percent: @json($subscriptionRevenueChangePercent ?? 0),
                up: @json($subscriptionRevenueChangeUp ?? true)
            },
            rentRevenue: {
                percent: @json($rentRevenueChangePercent ?? 0),
                up: @json($rentRevenueChangeUp ?? true)
            },
            totalRevenue: {
                percent: @json($totalRevenueChangePercent ?? 0),
                up: @json($totalRevenueChangeUp ?? true)
            },
            rentContent: {
                percent: @json($rentContentChangePercent ?? 0),
                up: @json($rentContentChangeUp ?? true)
            }
        });

        console.log('Dashboard trends', {
            usersTrend: @json($usersTrend ?? [0, 0]),
            subsTrend: @json($subsTrend ?? [0, 0]),
            soonExpireTrend: @json($soonExpireTrend ?? [0, 0]),
            reviewsTrend: @json($reviewsTrend ?? [0, 0]),
            subscriptionRevenueTrend: @json($subscriptionRevenueTrend ?? [0, 0]),
            rentRevenueTrend: @json($rentRevenueTrend ?? [0, 0]),
            totalRevenueTrend: @json($totalRevenueTrend ?? [0, 0]),
            rentContentTrend: @json($rentContentTrend ?? [0, 0])
        });

        const formatCurrencyvalue = (value) => {
            if (window.currencyFormat !== undefined) {
                return window.currencyFormat(value)
            }
            return value
        }

        var selectedDateRange = @json($dateRange ?? '');

        // Localization mapping for dropdown types
        const typeLocalization = {
            'Year': '{{ __('dashboard.year') }}',
            'Month': '{{ __('dashboard.month') }}',
            'Week': '{{ __('dashboard.week') }}'
        };

        const chartLabels = {
            totalRevenue: '{{ __('dashboard.lbl_tot_revenue') }}'
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize flatpickr for date range (Y-m-d format only)
            const range_flatpicker = document.querySelectorAll('.dashboard-date-range')
            Array.from(range_flatpicker, (elem) => {
                if (typeof flatpickr !== typeof undefined) {
                    flatpickr(elem, {
                        mode: "range",
                        dateFormat: "Y-m-d",
                        defaultDate: selectedDateRange || [
                            new Date(Date.now() - 2 * 30 * 24 * 60 * 60 * 1000), // 2 months ago
                            new Date() // today
                        ],
                    })
                }
            })

            var Base_url = "{{ url('/') }}";
            var url = Base_url + "/app/get_genre_chart_data";

            $.ajax({
                url: url,
                method: "GET",
                data: selectedDateRange ? {
                    date_range: selectedDateRange
                } : {},
                success: function(response) {
                    if (document.querySelectorAll('#chart-top-genres').length) {
                        const chartData = response.data.chartData;
                        const category = response.data.category;
                        const options = {
                            series: chartData,
                            chart: {
                                height: 450,
                                width: 380,
                                type: 'pie',
                            },
                            stroke: {
                                width: 0,
                            },
                            colors: ['var(--bs-primary)', 'var(--bs-primary-tint-20)',
                                'var(--bs-primary-tint-40)', 'var(--bs-primary-tint-60)',
                                'var(--bs-primary-tint-80)'
                            ],
                            labels: category,
                            dataLabels: {
                                enabled: false,
                            },
                            legend: {
                                show: true,
                                position: 'bottom',
                                fontSize: '14px',
                                labels: {
                                    colors: ['var(--bs-white)', 'var(--bs-white)',
                                        'var(--bs-white)', 'var(--bs-white)', 'var(--bs-white)'
                                    ]
                                },
                            },
                            responsive: [{
                                breakpoint: 1500,
                                options: {
                                    chart: {
                                        height: 320,
                                        width: 240,
                                    },
                                },
                            }]

                        };

                        var chart = new ApexCharts(document.querySelector("#chart-top-genres"),
                            options);
                        chart.render().then(() => {
                            if (window.chartLoadTracker) {
                                window.chartLoadTracker.markChartLoaded('chart-top-genres');
                            }
                        });
                    }
                }
            });
        });

        revanue_chart('Year')

        var chart = null;
        let revenueInstance;

        function revanue_chart(type) {
            var Base_url = "{{ url('/') }}";
            var url = Base_url + "/app/get_revnue_chart_data/" + type;

            $("#revenue_loader").show();

            var ajaxData = { _: Date.now() };
            if (selectedDateRange) {
                ajaxData.date_range = selectedDateRange;
            }

            $.ajax({
                url: url,
                method: "GET",
                data: ajaxData,
                success: function(response) {
                    $("#revenue_loader").hide();
                    $(".total_revenue").text(typeLocalization[type] || type);

                    if (document.querySelectorAll('#chart-top-revenue').length) {
                        const monthlyTotals = response.data.chartData;
                        const category = response.data.category;

                        const options = {
                            series: [{
                                name: chartLabels.totalRevenue,
                                data: monthlyTotals
                            }],
                            chart: {
                                height: 350,
                                type: 'area',
                                zoom: {
                                    enabled: false
                                },
                                toolbar: {
                                    tools: {
                                        download: true,
                                    },
                                    export: {
                                        csv: {
                                            filename: chartLabels.totalRevenue + ' ' + (typeLocalization[type] || type),
                                        },
                                        svg: {
                                            filename: chartLabels.totalRevenue + ' ' + (typeLocalization[type] || type)
                                        },
                                        png: {
                                            filename: chartLabels.totalRevenue + ' ' + (typeLocalization[type] || type)
                                        }
                                    }
                                }
                            },
                            colors: ['var(--bs-primary)'],
                            dataLabels: {
                                enabled: false
                            },
                            stroke: {
                                curve: 'smooth',
                            },
                            grid: {
                                borderColor: 'var(--bs-border-color)',
                                row: {
                                    colors: ['#f3f3f3', 'transparent'],
                                    opacity: 0
                                },
                            },
                            xaxis: {
                                categories: category
                            },
                            yaxis: {
                                decimalsInFloat: 2,
                                labels: {
                                    formatter: function(value) {
                                        return formatCurrencyvalue(value);
                                    }
                                }
                            },
                            tooltip: {
                                theme: 'dark',
                                y: {
                                    formatter: function(value) {
                                        return formatCurrencyvalue(value); // Currency formatting
                                    }
                                }
                            },
                        };

                        if (revenueInstance) {
                            revenueInstance.updateOptions(options);
                            if (window.chartLoadTracker) {
                                window.chartLoadTracker.markChartLoaded('chart-top-revenue');
                            }
                        } else {
                            revenueInstance = new ApexCharts(document.querySelector("#chart-top-revenue"),
                                options);
                            revenueInstance.render().then(() => {
                                if (window.chartLoadTracker) {
                                    window.chartLoadTracker.markChartLoaded('chart-top-revenue');
                                }
                            });
                        }
                    }
                }
            });
        }

        $(document).on('click', '.revenue-dropdown-item', function() {
            var type = $(this).data('type');
            revanue_chart(type);
        });


        subscriber_chart('Year')
        let subscriberInstance;

        function subscriber_chart(type) {
            var Base_url = "{{ url('/') }}";
            var url = Base_url + "/app/get_subscriber_chart_data/" + type;

            $("#subscriber_loader").show();

            $.ajax({
                url: url,
                method: "GET",
                data: selectedDateRange ? {
                    date_range: selectedDateRange
                } : {},
                success: function(response) {
                    $("#subscriber_loader").hide();
                    $(".total_subscribers").text(typeLocalization[type] || type);
                    if (document.querySelectorAll('#chart-new-subscription').length) {
                        const chartData = response.data.chartData;
                        const category = response.data.category;
                        const options = {
                            series: chartData,
                            chart: {
                                type: 'bar',
                                height: 350,
                                stacked: true,
                                stackType: '100%',
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: true,
                                    },
                                    export: {
                                        csv: {
                                            filename: 'New Subscribers ' + type.charAt(0).toUpperCase() +
                                                type.slice(1).toLowerCase(),
                                        },
                                        svg: {
                                            filename: 'New Subscribers ' + type.charAt(0).toUpperCase() +
                                                type.slice(1).toLowerCase()
                                        },
                                        png: {
                                            filename: 'New Subscribers ' + type.charAt(0).toUpperCase() +
                                                type.slice(1).toLowerCase()
                                        }
                                    }
                                },
                                zoom: {
                                    enabled: true
                                }
                            },
                            colors: ['var(--bs-primary)', 'var(--bs-primary-tint-20)',
                                'var(--bs-primary-tint-40)', 'var(--bs-primary-tint-60)'
                            ],
                            responsive: [{
                                breakpoint: 480,
                                options: {
                                    legend: {
                                        position: 'bottom',
                                        offsetX: -20,
                                        offsetY: 0
                                    }
                                }
                            }],
                            grid: {
                                borderColor: 'var(--bs-border-color)',
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '25%',
                                    borderRadius: 3,
                                    borderRadiusApplication: 'end', // 'around', 'end'
                                    borderRadiusWhenStacked: 'last', // 'all', 'last'
                                    dataLabels: {
                                        enabled: true,
                                        formatter: function(val, opts) {
                                            // Get the actual value from the series data
                                            let actualVal = val;
                                            if (opts && opts.w && opts.w.globals && opts.w.globals.series) {
                                                if (opts.seriesIndex !== undefined && opts.dataPointIndex !== undefined) {
                                                    const seriesData = opts.w.globals.series[opts.seriesIndex];
                                                    if (seriesData && seriesData[opts.dataPointIndex] !== undefined) {
                                                        actualVal = seriesData[opts.dataPointIndex];
                                                    }
                                                }
                                            }

                                            // Convert to number for comparison
                                            let numVal = parseFloat(actualVal) || 0;

                                            // Hide zero values - return empty string
                                            if (numVal === 0 || actualVal === 0 || actualVal === '0' || actualVal === null || actualVal === undefined) {
                                                return '';
                                            }

                                            // Return the number without percentage sign
                                            // val is already a number (0-100), format to remove decimals if whole number
                                            return val % 1 === 0 ? val.toString() : val.toFixed(1);
                                        },
                                        style: {
                                            fontSize: '13px',
                                            fontWeight: 900,
                                            colors: ['var(--bs-body-color)']
                                        },
                                        total: {
                                            enabled: true,
                                            formatter: function(val, opts) {
                                                // Calculate total for this data point
                                                let totalVal = 0;
                                                if (opts && opts.w && opts.w.globals && opts.w.globals.series) {
                                                    if (opts.dataPointIndex !== undefined) {
                                                        const allSeries = opts.w.globals.series;
                                                        for (let i = 0; i < allSeries.length; i++) {
                                                            if (allSeries[i] && allSeries[i][opts.dataPointIndex] !== undefined) {
                                                                totalVal += parseFloat(allSeries[i][opts.dataPointIndex]) || 0;
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    totalVal = parseFloat(val) || 0;
                                                }

                                                // Hide zero values for total
                                                if (totalVal === 0) {
                                                    return '';
                                                }

                                                // Return the number without percentage sign
                                                return val % 1 === 0 ? val.toString() : val.toFixed(1);
                                            },
                                            style: {
                                                fontSize: '13px',
                                                fontWeight: 900,
                                                color: 'var(--bs-body-color)'
                                            }
                                        }
                                    }
                                },
                            },
                            xaxis: {
                                // type: 'datetime',
                                categories: category
                            },
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center',
                                labels: {
                                    colors: 'var(--bs-body-color)',
                                }
                            },
                            fill: {
                                opacity: 1
                            },
                            tooltip: {
                                theme: 'dark',
                            },
                        };

                        // Function to hide zero labels for New Subscribers chart
                        function hideSubscriberZeroLabels() {
                            const chartElement = document.querySelector("#chart-new-subscription");
                            if (chartElement) {
                                // Find all text elements in the chart
                                const textElements = chartElement.querySelectorAll('text.apexcharts-datalabel-text, text.apexcharts-datalabel');
                                textElements.forEach(function(textEl) {
                                    const textContent = textEl.textContent.trim();
                                    // Hide if text is "0" or "0.0" or any zero variation
                                    if (textContent === '0' || textContent === '0.0' || textContent === '0.00' || parseFloat(textContent) === 0) {
                                        textEl.style.display = 'none';
                                        textEl.style.visibility = 'hidden';
                                        textEl.style.opacity = '0';
                                    }
                                });
                            }
                        }

                        if (subscriberInstance) {
                            subscriberInstance.updateOptions(options);
                            // Hide zero labels after update
                            setTimeout(hideSubscriberZeroLabels, 100);
                            if (window.chartLoadTracker) {
                                window.chartLoadTracker.markChartLoaded('chart-new-subscription');
                            }
                        } else {
                            subscriberInstance = new ApexCharts(document.querySelector(
                                "#chart-new-subscription"), options);
                            subscriberInstance.render().then(() => {
                                // Hide zero labels after render
                                setTimeout(hideSubscriberZeroLabels, 100);
                                // Also hide on animation end
                                setTimeout(hideSubscriberZeroLabels, 500);
                                if (window.chartLoadTracker) {
                                    window.chartLoadTracker.markChartLoaded('chart-new-subscription');
                                }
                            });
                        }
                    }
                }
            })
        };

        $(document).on('click', '.subscribers-dropdown-item', function() {
            var type = $(this).data('type');
            subscriber_chart(type);
            // Hide zero labels after chart updates
            setTimeout(function() {
                const chartElement = document.querySelector("#chart-new-subscription");
                if (chartElement) {
                    const textElements = chartElement.querySelectorAll('text.apexcharts-datalabel-text, text.apexcharts-datalabel');
                    textElements.forEach(function(textEl) {
                        const textContent = textEl.textContent.trim();
                        if (textContent === '0' || textContent === '0.0' || textContent === '0.00' || parseFloat(textContent) === 0) {
                            textEl.style.display = 'none';
                            textEl.style.visibility = 'hidden';
                            textEl.style.opacity = '0';
                        }
                    });
                }
            }, 600);
        });


        document.addEventListener('DOMContentLoaded', function() {
            var Base_url = "{{ url('/') }}";
            var url = Base_url + "/app/get_toprated_chart_data";

            $.ajax({
                url: url,
                method: "GET",
                data: selectedDateRange ? {
                    date_range: selectedDateRange
                } : {},
                success: function(response) {
                    console.log('Top Rated Chart Response:', response);
                    if (document.querySelectorAll('#chart-top-rated').length) {
                        const chartData = response.data.chartData;

                        // Prepare series data and labels for radialBar
                        const series = chartData.map(item => item.data[
                            0]); // Extract the first value from data array
                        const labels = chartData.map(item => item.name); // Extract names

                        console.log('Chart Data:', chartData);
                        console.log('Series:', series);
                        console.log('Labels:', labels);

                        const options = {
                            series: series,
                            chart: {
                                height: 430,
                                type: 'radialBar',
                                events: {
                                    dataPointSelection: function(event, chartContext, {
                                        dataPointIndex
                                    }) {
                                        // Log the clicked data point
                                        console.log('Clicked on segment:', labels[
                                            dataPointIndex], 'with value:', series[
                                            dataPointIndex]);
                                    }
                                }
                            },
                            colors: ['var(--bs-primary)', 'var(--bs-primary-tint-40)'],
                            labels: labels,
                            dataLabels: {
                                enabled: true,
                            },
                            plotOptions: {
                                radialBar: {
                                    hollow: {
                                        size: "65%"
                                    },
                                    track: {
                                        background: 'var(--bs-body-bg)',
                                        strokeWidth: '100%',
                                    },
                                    dataLabels: {
                                        name: {
                                            fontSize: '30px',
                                            color: 'var(--bs-heading-color)',
                                        },
                                        value: {
                                            fontSize: '16px',
                                            color: 'var(--bs-heading-color)',
                                            formatter: function(val) {
                                                return val;
                                            }
                                        },
                                        total: {
                                            show: true,
                                            color: 'var(--bs-heading-color)',
                                            fontSize: '22px',
                                            label: '{{ __('dashboard.lbl_total') }}',
                                            formatter: function(w) {
                                                // Calculate total from series values
                                                let total = w.config.series.reduce((a, b) => a +
                                                    b, 0); // sum up each entry's value
                                                return total;
                                            }
                                        }
                                    }
                                }
                            },
                            legend: {
                                show: true,
                                position: 'bottom',
                                fontSize: '14px',
                                labels: {
                                    colors: ['var(--bs-white)', 'var(--bs-white)']
                                },
                            },
                            responsive: [{
                                breakpoint: 300,
                                options: {
                                    chart: {
                                        height: 150,
                                    },
                                },
                            }]
                        };

                        // Create the chart instance
                        var chart = new ApexCharts(document.querySelector("#chart-top-rated"), options);
                        chart.render().then(() => {
                            if (window.chartLoadTracker) {
                                window.chartLoadTracker.markChartLoaded('chart-top-rated');
                            }
                            // Attach click event listener to legend labels
                            const legendItems = document.querySelectorAll(
                                '#chart-top-rated .apexcharts-legend-series');

                            legendItems.forEach((item, index) => {
                                item.addEventListener('click', function() {
                                    // Use toggleSeries to safely toggle visibility
                                    chart.toggleSeries(labels[index]);
                                });
                            });
                        });
                    }
                }
            });
        });




        mostwatch_chart('Year')
        let mostwatchInstance;

        function mostwatch_chart(type) {
            var Base_url = "{{ url('/') }}";
            var url = Base_url + "/app/get_mostwatch_chart_data/" + type;

            $("#mostwatch_loader").show();

            $.ajax({
                url: url,
                method: "GET",
                data: selectedDateRange ? {
                    date_range: selectedDateRange
                } : {},
                success: function(response) {
                    $("#mostwatch_loader").hide();
                    $(".most_watch").text(typeLocalization[type] || type);
                    if (document.querySelectorAll('#chart-most-watch').length) {
                        const chartData = response.data.chartData;
                        const category = response.data.category;
                        const options = {
                            series: chartData,
                            chart: {
                                type: 'bar',
                                height: 380,
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: true,
                                    },
                                    export: {
                                        csv: {
                                            filename: 'Most Watched ' + type.charAt(0).toUpperCase() + type
                                                .slice(1).toLowerCase(),
                                        },
                                        svg: {
                                            filename: 'Most Watched ' + type.charAt(0).toUpperCase() + type
                                                .slice(1).toLowerCase()
                                        },
                                        png: {
                                            filename: 'Most Watched ' + type.charAt(0).toUpperCase() + type
                                                .slice(1).toLowerCase()
                                        }
                                    }
                                },
                                zoom: {
                                    enabled: true
                                }
                            },
                            colors: ['var(--bs-primary)', 'var(--bs-primary-tint-60)',
                                'var(--bs-primary-tint-40)', 'var(--bs-primary-tint-20)'
                            ],
                            responsive: [{
                                breakpoint: 480,
                                options: {
                                    legend: {
                                        position: 'bottom',
                                        offsetX: -10,
                                        offsetY: 0,

                                    }
                                }
                            }],
                            grid: {
                                borderColor: 'var(--bs-border-color)',
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '45%',
                                    borderRadius: 3,
                                    borderRadiusApplication: 'end', // 'around', 'end'
                                    borderRadiusWhenStacked: 'last', // 'all', 'last'
                                    dataLabels: {
                                        enabled: true,
                                        formatter: function(val, opts) {
                                            // Get the actual value from the series data
                                            let actualVal = val;
                                            if (opts && opts.w && opts.w.globals && opts.w.globals.series) {
                                                if (opts.seriesIndex !== undefined && opts.dataPointIndex !== undefined) {
                                                    const seriesData = opts.w.globals.series[opts.seriesIndex];
                                                    if (seriesData && seriesData[opts.dataPointIndex] !== undefined) {
                                                        actualVal = seriesData[opts.dataPointIndex];
                                                    }
                                                }
                                            }

                                            // Convert to number for comparison
                                            let numVal = parseFloat(actualVal) || 0;

                                            // Hide zero values - return empty string
                                            if (numVal === 0 || actualVal === 0 || actualVal === '0' || actualVal === null || actualVal === undefined) {
                                                return '';
                                            }

                                            return val;
                                        },
                                        style: {
                                            fontSize: '13px',
                                            fontWeight: 900,
                                            colors: ['var(--bs-body-color)']
                                        },
                                        total: {
                                            enabled: true,
                                            formatter: function(val, opts) {
                                                // Calculate total for this data point
                                                let totalVal = 0;
                                                if (opts && opts.w && opts.w.globals && opts.w.globals.series) {
                                                    if (opts.dataPointIndex !== undefined) {
                                                        const allSeries = opts.w.globals.series;
                                                        for (let i = 0; i < allSeries.length; i++) {
                                                            if (allSeries[i] && allSeries[i][opts.dataPointIndex] !== undefined) {
                                                                totalVal += parseFloat(allSeries[i][opts.dataPointIndex]) || 0;
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    totalVal = parseFloat(val) || 0;
                                                }

                                                // Hide zero values for total
                                                if (totalVal === 0) {
                                                    return '';
                                                }

                                                return val;
                                            },
                                            style: {
                                                fontSize: '13px',
                                                fontWeight: 900,
                                                color: 'var(--bs-body-color)'
                                            }
                                        }
                                    }
                                },
                            },
                            xaxis: {
                                categories: category
                            },
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center',
                                labels: {
                                    colors: 'var(--bs-body-color)',
                                },
                                markers: {
                                    offsetX: -5
                                }
                            },
                            fill: {
                                opacity: 1
                            },
                            tooltip: {
                                theme: 'dark',
                            },
                        };

                        // Function to hide zero labels
                        function hideZeroLabels() {
                            const chartElement = document.querySelector("#chart-most-watch");
                            if (chartElement) {
                                // Find all text elements in the chart
                                const textElements = chartElement.querySelectorAll('text.apexcharts-datalabel-text, text.apexcharts-datalabel');
                                textElements.forEach(function(textEl) {
                                    const textContent = textEl.textContent.trim();
                                    // Hide if text is "0" or "0.0" or any zero variation
                                    if (textContent === '0' || textContent === '0.0' || textContent === '0.00' || parseFloat(textContent) === 0) {
                                        textEl.style.display = 'none';
                                        textEl.style.visibility = 'hidden';
                                        textEl.style.opacity = '0';
                                    }
                                });
                            }
                        }

                        if (mostwatchInstance) {
                            mostwatchInstance.updateOptions(options);
                            // Hide zero labels after update
                            setTimeout(hideZeroLabels, 100);
                            if (window.chartLoadTracker) {
                                window.chartLoadTracker.markChartLoaded('chart-most-watch');
                            }
                        } else {
                            mostwatchInstance = new ApexCharts(document.querySelector("#chart-most-watch"),
                                options);
                            mostwatchInstance.render().then(() => {
                                // Hide zero labels after render
                                setTimeout(hideZeroLabels, 100);
                                // Also hide on animation end
                                setTimeout(hideZeroLabels, 500);
                                if (window.chartLoadTracker) {
                                    window.chartLoadTracker.markChartLoaded('chart-most-watch');
                                }
                            });
                        }
                    }
                }
            })
        };

        $(document).on('click', '.mostwatch-dropdown-item', function() {
            var type = $(this).data('type');
            mostwatch_chart(type);
            // Hide zero labels after chart updates
            setTimeout(function() {
                const chartElement = document.querySelector("#chart-most-watch");
                if (chartElement) {
                    const textElements = chartElement.querySelectorAll('text.apexcharts-datalabel-text, text.apexcharts-datalabel');
                    textElements.forEach(function(textEl) {
                        const textContent = textEl.textContent.trim();
                        if (textContent === '0' || textContent === '0.0' || textContent === '0.00' || parseFloat(textContent) === 0) {
                            textEl.style.display = 'none';
                            textEl.style.visibility = 'hidden';
                            textEl.style.opacity = '0';
                        }
                    });
                }
            }, 600);
        });
    </script>

    <script>
        /*-------------- Service1 Chart ----------------*/
        if (document.querySelectorAll("#chart-01").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($usersTrend ?? [0, 0]),
                }, ],
                colors: [@json($usersChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($usersTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-01"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-01');
                }
            });
        }

        if (document.querySelectorAll("#chart-02").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($subsTrend ?? [0, 0]),
                }, ],
                colors: [@json($subsChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($subsTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-02"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-02');
                }
            });
        }

        if (document.querySelectorAll("#chart-03").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($soonExpireTrend ?? [0, 0]),
                }, ],
                colors: [@json($soonExpireChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($soonExpireTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-03"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-03');
                }
            });
        }

        if (document.querySelectorAll("#chart-04").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($reviewsTrend ?? [0, 0]),
                }, ],
                colors: [@json($reviewsChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($reviewsTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-04"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-04');
                }
            });
        }
        if (document.querySelectorAll("#chart-05").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($rentContentTrend ?? [0, 0]),
                }, ],
                colors: [@json($rentContentChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($rentContentTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-05"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-05');
                }
            });
        }
        if (document.querySelectorAll("#chart-06").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($rentContentTrend ?? [0, 0]),
                }, ],
                colors: [@json($rentContentChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($rentContentTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-06"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-06');
                }
            });
        }
        if (document.querySelectorAll("#chart-07").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($subscriptionRevenueTrend ?? [0, 0]),
                }, ],
                colors: [@json($subscriptionRevenueChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($subscriptionRevenueTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-07"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-07');
                }
            });
        }
        if (document.querySelectorAll("#chart-08").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($rentRevenueTrend ?? [0, 0]),
                }, ],
                colors: [@json($rentRevenueChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($rentRevenueTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-08"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-08');
                }
            });
        }
        if (document.querySelectorAll("#chart-09").length) {
            const options = {
                series: [{
                    name: "series1",
                    data: @json($totalRevenueTrend ?? [0, 0]),
                }, ],
                colors: [@json($totalRevenueChangeUp ? '#28a745' : '#dc3545')],
                chart: {
                    height: 65,
                    width: 140,
                    type: "area",
                    sparkline: {
                        enabled: true,
                    },
                },
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: "smooth",
                },
                xaxis: {
                    type: "datetime",
                    categories: {!! json_encode($totalRevenueTrendDates ?? ['2018-09-19T00:00:00.000Z', '2018-09-19T01:30:00.000Z']) !!},
                },
                tooltip: {
                    enabled: false,
                },
            };

            const chart = new ApexCharts(
                document.querySelector("#chart-09"),
                options
            );
            chart.render().then(() => {
                if (window.chartLoadTracker) {
                    window.chartLoadTracker.markChartLoaded('chart-09');
                }
            });
        }
    </script>
@endpush
