<!-- Horizontal Menu Start -->
<nav id="navbar_main" class="offcanvas mobile-offcanvas nav navbar navbar-expand-xl hover-nav horizontal-nav py-xl-0">
  <div class="container-fluid p-lg-0">
    <div class="offcanvas-header">
      <div class="navbar-brand p-0">
        <!--Logo -->
        @include('frontend::components.partials.logo')

      </div>
      <button type="button" class="btn-close p-0" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <ul class="navbar-nav iq-nav-menu  list-unstyled" id="header-menu">
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('user.login') ? 'active text-primary' : '' }}"  href="{{route('user.login')}}">
          <span class="item-name">{{__('frontend.home')}}</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs(['movies', 'movie-details', 'tv-shows', 'tvshow-details', 'episode-details', 'videos', 'video-details', 'video-detail']) ? 'active text-primary' : '' }}" href="#">
          <span class="item-name">{{__('frontend.all_content')}}</span>
        </a>
        <ul class="sub-menu list-unstyled">
          @if(isenablemodule('movie'))
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs(['movies', 'movie-details']) ? 'active text-primary' : '' }}"  href="{{ route('movies') }}">
              <span class="item-name">{{__('frontend.movies')}}</span>
            </a>
          </li>
          @endif
          @if(isenablemodule('tvshow'))
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs(['tv-shows', 'tvshow-details', 'episode-details']) ? 'active text-primary' : '' }}"  href="{{ route('tv-shows') }}">
              <span class="item-name">{{__('frontend.tvshows')}}</span>
            </a>
          </li>
          @endif
          @if(isenablemodule('video'))
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs(['videos', 'video-details', 'video-detail']) ? 'active text-primary' : '' }}" href="{{ route('videos') }}">
              <span class="item-name">All Videos</span>
            </a>
          </li>
          @endif
        </ul>
      </li>
      <!-- @if(isenablemodule('movie'))
      <li class="nav-item">
        <a class="nav-link"  href="{{ route('movies') }}">
          <span class="item-name">{{__('frontend.movies')}}</span>
        </a>
      </li>
      @endif
      @if(isenablemodule('tvshow'))
      <li class="nav-item">
        <a class="nav-link"  href="{{ route('tv-shows') }}">
          <span class="item-name">{{__('frontend.tvshows')}}</span>
        </a>
      </li>
      @endif
      @if(isenablemodule('video'))
      <li class="nav-item">
        <a class="nav-link"  href="{{ route('videos') }}">
          <span class="item-name">{{__('frontend.video')}}</span>
        </a>
      </li>
      @endif -->
      <!-- <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('comingsoon') ? 'active text-primary' : '' }}"  href="{{ route('comingsoon') }}">
          <span class="item-name">{{__('frontend.coming_soon')}}</span>
        </a>
      </li> -->

      @if(isenablemodule('livetv'))
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('topChannelList') || request()->routeIs('livetv') ? 'active text-primary' : '' }}" href="{{ route('topChannelList') }}">
          <span class="item-name">{{__('frontend.livetv')}}</span>
        </a>
        <ul class="sub-menu list-unstyled scroll-thin" style="max-height: 400px; overflow-y: auto;">
          @php
            $liveTvChannels = \Modules\LiveTV\Models\LiveTvChannel::where('status', 1)
                ->orderBy('name')
                ->get();
          @endphp
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('livetv') && !request()->route('id') ? 'active text-primary' : '' }}" href="{{ route('topChannelList') }}">
              <span class="item-name">All Channels</span>
            </a>
          </li>
          @foreach($liveTvChannels as $channel)
          <li class="nav-item">
            <a class="nav-link {{ request()->route('id') === $channel->slug ? 'active text-primary' : '' }}" href="{{ route('livetv-details', $channel->slug) }}">
              <span class="item-name">{{ $channel->name }}</span>
            </a>
          </li>
          @endforeach
        </ul>
      </li>
      
      @endif
      @if(isenablemodule('video'))
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs(['videos', 'video-details', 'video-detail']) ? 'active text-primary' : '' }}" href="#">
          <span class="item-name">{{__('frontend.video')}}</span>
        </a>
        <ul class="sub-menu list-unstyled scroll-thin" style="max-height: 400px; overflow-y: auto;">
          @php
            $videos = \Modules\Video\Models\Video::where('status', 1)
                ->whereDate('release_date', '<=', now())
                ->orderBy('name')
                ->get();
          @endphp
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('videos') && !request()->route('id') ? 'active text-primary' : '' }}" href="{{ route('videos') }}">
              <span class="item-name">All Videos</span>
            </a>
          </li>
          @foreach($videos as $video)
          <li class="nav-item">
            <a class="nav-link {{ request()->route('id') === $video->slug ? 'active text-primary' : '' }}" href="{{ route('video-details', $video->slug) }}">
              <span class="item-name">{{ $video->name }}</span>
            </a>
          </li>
          @endforeach
        </ul>
      </li>
      @endif 
      @php $navCategories = \Modules\Categories\Models\Category::where('status',1)->orderBy('name')->get(); @endphp
      @if($navCategories->count() && isenablemodule('show_categories_menu'))
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('videos.by-category') ? 'active text-primary' : '' }}" href="#">
          <span class="item-name">Categories</span>
        </a>
        <ul class="sub-menu list-unstyled">
          @foreach($navCategories as $navCat)
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('videos.by-category') && request()->route('slug') === $navCat->slug ? 'active text-primary' : '' }}"
               href="{{ route('videos.by-category', $navCat->slug) }}">
              <span class="item-name">{{ $navCat->name }}</span>
            </a>
          </li>
          @endforeach
        </ul>
      </li>
      @endif
       <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('livetv') ? 'active text-primary' : '' }}" target="_blank"  href="https://ppv.ezway.tv/">
          <span class="item-name" style="text-transform: none;">eZWay PPV</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('distribution') ? 'active text-primary' : '' }}" href="{{ route('distribution') }}">
          <span class="item-name">Distribution</span>
        </a>
      </li>
    </ul>
  </div>
  <!-- container-fluid.// -->
</nav>
<!-- Horizontal Menu End -->
