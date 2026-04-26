@extends('frontend::layouts.master')

@section('title')
    {{ __('frontend.top_channels') }}
@endsection
@section('content')
    <div class="list-page section-spacing-bottom px-0">
        <div class="page-title">
            <h4 class="m-0 text-center">{{ __('frontend.top_channels') }}</h4>
        </div>
        <div class="filter-bar d-flex justify-content-center my-3">
            <div class="btn-group" role="group" aria-label="Sort Top Channels">
                <button id="sort-views" type="button" class="btn btn-outline-primary">Top Watched</button>
                <button id="sort-alpha" type="button" class="btn btn-outline-primary">A → Z</button>
            </div>
        </div>
        <div class="movie-lists section-spacing-bottom">
            <div class="container-fluid">
                <div class="row gy-4 gx-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 row-cols-xl-7"
                    id="top-channel">
                </div>
                <div class="card-style-slider shimmer-container">
                    <div class="row gy-4 gx-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 row-cols-xl-7 mt-3">
                        @for ($i = 0; $i < 21; $i++)
                            <div class="col">
                                @include('components.card_shimmer_channel')
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/entertainment.min.js') }}" defer></script>
    <script>
        const noDataImageSrc = '{{ asset('img/NoData.png') }}';
        const shimmerContainer = document.querySelector('.shimmer-container');
        const EntertainmentList = document.getElementById('top-channel');
        let currentPage = 1;
        let isLoading = false;
        let hasMore = true;
        let actor_id = null;
        let movie_id = null;
        let type = null;
        let per_page = 21;
        const baseUrl = document.querySelector('meta[name="baseUrl"]').getAttribute('content');
        // Read desired sort from query string (default to views)
        const urlParams = new URLSearchParams(window.location.search);
        const sortParam = urlParams.get('sort') || 'views';
        // expose current sort to entertainment.js which will append it to requests
        window.currentSortType = sortParam === 'views' ? 'top_star' : (sortParam === 'alpha' ? 'alpha' : sortParam);
        const apiUrl = `${baseUrl}/api/channel-list`;
        // Wire up filter buttons to reload page with chosen sort
        document.addEventListener('DOMContentLoaded', function () {
            const viewsBtn = document.getElementById('sort-views');
            const alphaBtn = document.getElementById('sort-alpha');
            if (viewsBtn) viewsBtn.classList.toggle('active', sortParam === 'views');
            if (alphaBtn) alphaBtn.classList.toggle('active', sortParam === 'alpha');

            if (viewsBtn) viewsBtn.addEventListener('click', function () {
                urlParams.set('sort', 'views');
                window.location.search = urlParams.toString();
            });
            if (alphaBtn) alphaBtn.addEventListener('click', function () {
                urlParams.set('sort', 'alpha');
                window.location.search = urlParams.toString();
            });
        });
        const csrf_token = '{{ csrf_token() }}'
    </script>
@endsection
