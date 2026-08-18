import { useEffect, useMemo, useRef, useState, type MouseEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, Play } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { AppHeader } from '@/components/AppHeader'
import { AdBannerSlider } from '@/components/AdBannerSlider'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { loadHomeModule } from './homeApi'
import { isNativeIosApp } from '@/lib/native-platform'
import type { CustomPromo, DashboardData, LiveTvDashboard, MediaItem } from './types'

type HomeState = {
  dashboard: DashboardData
  genres: MediaItem[]
  videos: MediaItem[]
  liveTv: LiveTvDashboard
  ondemandChannels: MediaItem[]
}

const emptyState: HomeState = {
  dashboard: {},
  genres: [],
  videos: [],
  liveTv: {},
  ondemandChannels: [],
}

const homeHeroImage = 'https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/caa4d6ec_3f9c_4f51_8e9c_95153c5d2b98_6a16d19e8d157.jpg'
export function HomePage() {
  const homeQuery = useQuery({
    queryKey: ['home-module'],
    queryFn: loadHomeModule,
    staleTime: 60_000,
  })
  const state = homeQuery.data ?? emptyState
  const railLimit = normalizeRailLimit(state.dashboard.homepage_rail_item_limit)

  const liveChannels = useMemo(
    () => liveTvChannelsFromDashboard(state.liveTv).slice(0, railLimit),
    [railLimit, state.liveTv],
  )
  const heroLiveChannels = useMemo(
    () => (state.dashboard.hero_banner_slider_livetv?.data?.length ? state.dashboard.hero_banner_slider_livetv.data : liveChannels),
    [liveChannels, state.dashboard.hero_banner_slider_livetv?.data],
  )
  const ondemandChannels = useMemo(() => state.ondemandChannels.slice(0, railLimit), [railLimit, state.ondemandChannels])
  const latestVideos = useMemo(() => state.videos.slice(0, railLimit), [railLimit, state.videos])
  const mostWatchedRail = useMemo(() => resolveMostWatchedRail(state.dashboard), [state.dashboard])
  const mostWatchedVideos = useMemo(
    () => (mostWatchedRail?.data ?? []).slice(0, railLimit),
    [railLimit, mostWatchedRail?.data],
  )
  const personalities = useMemo(
    () => (state.dashboard.personality?.data ?? state.dashboard.popular_personality?.data ?? []).slice(0, railLimit),
    [railLimit, state.dashboard.personality?.data, state.dashboard.popular_personality?.data],
  )
  const latestMovies = useMemo(() => (state.dashboard.latest_movie?.data ?? []).slice(0, railLimit), [railLimit, state.dashboard.latest_movie?.data])
  const featured = heroLiveChannels[0] ?? state.liveTv.slider?.[0] ?? liveChannels[0] ?? state.videos[0]

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <Hero featured={featured} liveChannels={heroLiveChannels} loading={homeQuery.isLoading} />
      {!homeQuery.isLoading ? <HomepagePromos promos={state.dashboard.custom_ads ?? []} /> : null}

      <section className="relative z-10 space-y-9 px-4 pb-16 pt-3 sm:px-8 sm:pt-4 lg:px-12">
        {homeQuery.isError ? (
          <div className="rounded-md border border-red-500/30 bg-red-950/40 px-4 py-3 text-sm text-red-100">
            Some home APIs did not respond. Existing backend remains untouched.
          </div>
        ) : null}

        {homeQuery.isLoading ? (
          <HomeSectionsSkeleton />
        ) : (
          <>
            <Rail
              title={mostWatchedRail?.name ?? 'Most Watched Videos'}
              items={mostWatchedVideos}
              href="/videos"
              shape="video"
              index={0}
            />
            <Rail title="Live TV Now" items={liveChannels} href="/livetv" shape="square" index={1} showLiveBadge />
            <Rail title="On Demand Channels" items={ondemandChannels} href="/on-demand" shape="channel" index={2} />
            <Rail title="Latest Videos" items={latestVideos} href="/videos" shape="video" index={3} />
            <Rail
              title={state.dashboard.personality?.name ?? state.dashboard.popular_personality?.name ?? 'Popular Personalities'}
              items={personalities}
              href="/castcrew-list"
              shape="personality"
              index={4}
            />
            <Rail title={state.dashboard.latest_movie?.name ?? 'New Released Movies'} items={latestMovies} href="/movies" shape="poster" index={5} />
            <AdBannerSlider placement="home" className="-mx-4 sm:-mx-8 lg:-mx-12" />
          </>
        )}
      </section>
    </main>
  )
}

function HomepagePromos({ promos }: { promos: CustomPromo[] }) {
  const visiblePromos = promos.filter((promo) => Boolean(resolvePromoUrl(promo) || resolvePromoMobileUrl(promo)))

  if (visiblePromos.length === 0) return null

  return (
    <section className="ez-home-promo-wrap relative z-10 bg-[#050505] px-4 py-[52px] sm:px-8 sm:py-[60px] lg:px-12">
      <div className="mx-auto max-w-[1800px]">
        <div className="grid gap-4">
          {visiblePromos.map((promo, index) => (
            <PromoPanel key={`${promo.name ?? 'promo'}-${index}`} promo={promo} eager={index === 0} />
          ))}
        </div>
      </div>
    </section>
  )
}

function PromoPanel({ promo, eager }: { promo: CustomPromo; eager: boolean }) {
  const mediaUrl = resolvePromoUrl(promo)
  const mobileMediaUrl = resolvePromoMobileUrl(promo) || mediaUrl
  const panel = (
    <div className="relative aspect-[8/3] overflow-hidden rounded-md border border-white/10 bg-[#050505] shadow-2xl shadow-black/40 sm:aspect-[16/5]">
      {promo.type === 'video' ? (
        <video src={mediaUrl} className="h-full w-full object-contain" autoPlay muted loop playsInline />
      ) : (
        <picture className="block h-full w-full">
          <source media="(max-width: 767px)" srcSet={mobileMediaUrl} />
          <img
            src={mediaUrl}
            alt={promo.name ?? 'Promotion'}
            className="block h-full w-full object-contain"
            loading={eager ? 'eager' : 'lazy'}
            referrerPolicy="no-referrer"
          />
        </picture>
      )}
    </div>
  )

  if (!promo.redirect_url) return panel

  return (
    <a href={promo.redirect_url} target="_blank" rel="noreferrer" className="block">
      {panel}
    </a>
  )
}

function resolvePromoUrl(promo: CustomPromo) {
  if (promo.type !== 'video' && promo.id) {
    return `/api/v3/promo-media/${promo.id}/image`
  }

  return promo.url ?? promo.media ?? ''
}

function resolvePromoMobileUrl(promo: CustomPromo) {
  if (promo.type !== 'video' && promo.id) {
    return `/api/v3/promo-media/${promo.id}/image?device=mobile`
  }

  return promo.mobile_url ?? promo.mobile_media ?? promo.url ?? promo.media ?? ''
}

function Hero({
  featured,
  liveChannels,
  loading,
}: {
  featured?: MediaItem
  liveChannels: MediaItem[]
  loading: boolean
}) {
  const channelSlides = useMemo(() => {
    const channels = liveChannels

    if (channels.length > 0) return channels.slice(0, 8)
    return featured ? [featured] : []
  }, [featured, liveChannels])
  const [activeIndex, setActiveIndex] = useState(0)

  useEffect(() => {
    if (activeIndex < channelSlides.length) return
    setActiveIndex(0)
  }, [activeIndex, channelSlides.length])

  useEffect(() => {
    if (channelSlides.length < 2) return undefined

    const timer = window.setInterval(() => {
      setActiveIndex((current) => (current + 1) % channelSlides.length)
    }, 6500)

    return () => window.clearInterval(timer)
  }, [channelSlides.length])

  const activeChannel = channelSlides[activeIndex] ?? featured
  const hasActiveChannel = Boolean(activeChannel)
  const title = activeChannel?.details?.name ?? activeChannel?.name ?? 'eZWay TV Live'
  const category = activeChannel?.details?.category ?? 'Live TV'
  const heroImage = cardImage(activeChannel ?? featured, 'square') ?? homeHeroImage
  const channelDescription = activeChannel?.description ?? activeChannel?.short_desc ?? activeChannel?.details?.description

  function goToPrevious() {
    if (channelSlides.length < 2) return
    setActiveIndex((current) => (current - 1 + channelSlides.length) % channelSlides.length)
  }

  function goToNext() {
    if (channelSlides.length < 2) return
    setActiveIndex((current) => (current + 1) % channelSlides.length)
  }

  if (!hasActiveChannel) {
    return null
  }

  return (
    <section className="relative min-h-[520px] overflow-hidden bg-[#050505] sm:min-h-[72vh]">
      {heroImage ? (
        <>
          <img src={heroImage} alt="" className="ez-home-hero-image absolute inset-0 h-full w-full object-cover object-center opacity-95" />
          <img src={heroImage} alt="" className="absolute inset-0 h-full w-full scale-[1.02] object-cover object-center opacity-18 blur-xl" />
        </>
      ) : null}
      <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.74)_28%,rgba(5,5,5,0.24)_60%,rgba(5,5,5,0.04)_100%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(0deg,#050505_0%,rgba(5,5,5,0.42)_18%,rgba(5,5,5,0)_52%)]" />
      <div className="absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-[#050505]/72 to-transparent" />

      <div className="relative z-10 flex min-h-[520px] flex-col justify-end px-4 pb-10 pt-20 sm:min-h-[72vh] sm:px-8 sm:pb-14 lg:px-12">
        <div className="ez-home-hero-copy max-w-2xl rounded-md bg-black/58 p-4 shadow-[0_24px_80px_rgba(0,0,0,0.72)] backdrop-blur-[2px] sm:bg-transparent sm:p-0 sm:shadow-none sm:backdrop-blur-0">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Badge className="ez-home-badge inline-flex w-fit items-center gap-2 rounded-full bg-red-600 px-3 py-1.5 text-xs font-black uppercase text-white">
              <span className="h-2 w-2 rounded-full bg-white shadow-[0_0_12px_rgba(255,255,255,0.95)]" aria-hidden="true" />
              Live Now
            </Badge>
            <Badge className="w-fit rounded-full border border-[#d4a843]/35 bg-[#d4a843]/14 px-3 py-1.5 text-xs font-bold uppercase text-[#f4d36a]">
              {loading ? 'Loading' : category}
            </Badge>
          </div>
          <h1 className="max-w-2xl text-4xl font-black leading-none text-white drop-shadow-[0_4px_18px_rgba(0,0,0,0.95)] sm:text-6xl lg:text-7xl">{title}</h1>
          <p className="mt-4 max-w-xl text-base font-medium leading-7 text-white/90 drop-shadow-[0_2px_10px_rgba(0,0,0,0.95)] sm:text-lg">
            {channelDescription || 'Stream eZWay related live channels, featured programming, and network broadcasts.'}
          </p>
          <div className="mt-6 flex flex-wrap items-center gap-3">
            <Button asChild size="lg" className="ez-home-cta-primary rounded-sm bg-white text-black hover:bg-white/90">
              <a href={contentHref(activeChannel)}>
                <Play className="h-5 w-5 fill-current" />
                Watch Live
              </a>
            </Button>
            <Button asChild size="lg" variant="secondary" className="ez-home-cta-secondary rounded-sm bg-white/18 text-white hover:bg-white/28">
              <a href="/livetv">
                All Channels
                <ChevronRight className="h-5 w-5" />
              </a>
            </Button>
          </div>
        </div>

        {channelSlides.length > 1 ? (
          <div className="mt-8 flex flex-wrap items-center gap-4">
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={goToPrevious}
                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/18 bg-black/44 text-white backdrop-blur transition hover:border-white/50 hover:bg-white/16"
                aria-label="Previous live channel"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              <button
                type="button"
                onClick={goToNext}
                className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/18 bg-black/44 text-white backdrop-blur transition hover:border-white/50 hover:bg-white/16"
                aria-label="Next live channel"
              >
                <ChevronRight className="h-5 w-5" />
              </button>
            </div>

            <div className="flex min-w-0 flex-1 items-center gap-2">
              {channelSlides.map((channel, index) => {
                const channelTitle = channel.details?.name ?? channel.name

                return (
                  <button
                    key={`hero-channel-dot-${channel.id}`}
                    type="button"
                    onClick={() => setActiveIndex(index)}
                    className={[
                      'h-1.5 rounded-full transition-all',
                      index === activeIndex ? 'w-12 bg-white' : 'w-5 bg-white/36 hover:bg-white/70',
                    ].join(' ')}
                    aria-label={`Show ${channelTitle}`}
                  />
                )
              })}
            </div>

          </div>
        ) : null}
      </div>
    </section>
  )
}

function HomeSectionsSkeleton() {
  const rails = [
    { titleWidth: 'w-32', shape: 'square' },
    { titleWidth: 'w-48', shape: 'channel' },
    { titleWidth: 'w-36', shape: 'video' },
    { titleWidth: 'w-44', shape: 'personality' },
    { titleWidth: 'w-52', shape: 'poster' },
  ] as const

  return (
    <>
      {rails.map((rail, index) => (
        <section key={index} className="ez-home-rail" style={{ animationDelay: `${index * 90}ms` }}>
          <div className="mb-3 flex items-center justify-between gap-4">
            <div className={['h-7 animate-pulse rounded-md bg-white/12', rail.titleWidth].join(' ')} />
            <div className="h-4 w-16 animate-pulse rounded bg-white/10" />
          </div>
          <div
            className={[
              'grid grid-flow-col gap-4 overflow-x-hidden pb-5',
              rail.shape === 'personality'
                ? 'auto-cols-[minmax(150px,48vw)] sm:auto-cols-[calc((100%-4rem)/5)] lg:auto-cols-[calc((100%-6rem)/7)] 2xl:auto-cols-[calc((100%-9rem)/10)]'
                : 'auto-cols-[minmax(220px,72vw)] sm:auto-cols-[calc((100%-3rem)/4)] lg:auto-cols-[calc((100%-4rem)/5)] 2xl:auto-cols-[calc((100%-6rem)/7)]',
            ].join(' ')}
          >
            {Array.from({ length: rail.shape === 'personality' ? 10 : 7 }).map((_, itemIndex) => (
              <HomeCardSkeleton key={itemIndex} personality={rail.shape === 'personality'} index={itemIndex} />
            ))}
          </div>
        </section>
      ))}
    </>
  )
}

function HomeCardSkeleton({ personality, index }: { personality?: boolean; index: number }) {
  const style = { animationDelay: `${Math.min(index, 9) * 55}ms` }

  if (personality) {
    return (
      <div className="ez-home-card min-w-0 text-center" style={style}>
        <div className="mx-auto aspect-square w-[72%] animate-pulse rounded-full border border-white/10 bg-white/[0.07] shadow-lg" />
        <div className="mx-auto mt-3 h-4 w-28 animate-pulse rounded bg-white/10" />
        <div className="mx-auto mt-2 h-3 w-16 animate-pulse rounded bg-white/7" />
      </div>
    )
  }

  return (
    <div className="ez-home-card min-w-0" style={style}>
      <div className="aspect-video animate-pulse rounded-md border border-white/8 bg-white/[0.07] shadow-lg" />
      <div className="mt-2 h-4 animate-pulse rounded bg-white/10" />
      <div className="mt-2 h-3 w-2/3 animate-pulse rounded bg-white/7" />
    </div>
  )
}
function Rail({
  title,
  items,
  href,
  shape,
  index = 0,
  showLiveBadge = false,
}: {
  title: string
  items: MediaItem[]
  href?: string
  shape: 'poster' | 'video' | 'square' | 'genre' | 'channel' | 'personality'
  index?: number
  showLiveBadge?: boolean
}) {
  const scrollerRef = useRef<HTMLDivElement | null>(null)
  const dragRef = useRef({
    active: false,
    moved: false,
    startX: 0,
    scrollLeft: 0,
  })
  const dragCleanupRef = useRef<(() => void) | null>(null)

  useEffect(() => {
    return () => {
      dragCleanupRef.current?.()
      document.body.classList.remove('ez-home-rail-drag-lock')
    }
  }, [])

  if (items.length === 0) return null

  function handleMouseDown(event: MouseEvent<HTMLDivElement>) {
    if (event.button !== 0) return

    const scroller = scrollerRef.current
    if (!scroller) return
    dragCleanupRef.current?.()

    const handleMouseMove = (moveEvent: globalThis.MouseEvent) => {
      const activeScroller = scrollerRef.current
      if (!dragRef.current.active || !activeScroller) return

      const deltaX = moveEvent.pageX - dragRef.current.startX
      if (Math.abs(deltaX) > 4) {
        dragRef.current.moved = true
      }

      activeScroller.scrollLeft = dragRef.current.scrollLeft - deltaX
    }

    const handleMouseUp = () => {
      stopDragging()
      dragCleanupRef.current?.()
    }

    dragRef.current = {
      active: true,
      moved: false,
      startX: event.pageX,
      scrollLeft: scroller.scrollLeft,
    }
    scroller.classList.add('ez-home-rail-dragging')
    document.body.classList.add('ez-home-rail-drag-lock')
    window.addEventListener('mousemove', handleMouseMove)
    window.addEventListener('mouseup', handleMouseUp)
    dragCleanupRef.current = () => {
      window.removeEventListener('mousemove', handleMouseMove)
      window.removeEventListener('mouseup', handleMouseUp)
      dragCleanupRef.current = null
    }
  }

  function stopDragging() {
    dragRef.current.active = false
    scrollerRef.current?.classList.remove('ez-home-rail-dragging')
    document.body.classList.remove('ez-home-rail-drag-lock')
  }

  function handleClickCapture(event: MouseEvent<HTMLDivElement>) {
    if (!dragRef.current.moved) return

    event.preventDefault()
    event.stopPropagation()
    dragRef.current.moved = false
  }

  return (
    <section className="ez-home-rail" style={{ animationDelay: `${index * 90}ms` }}>
      <div className="mb-3 flex items-center justify-between gap-4">
        <h2 className="text-xl font-bold text-white sm:text-2xl">{title}</h2>
        {href ? (
          <a className="inline-flex items-center gap-1 text-sm font-semibold text-white/60 hover:text-white" href={href}>
            View all
            <ChevronRight className="h-4 w-4" />
          </a>
        ) : null}
      </div>

      <div
        ref={scrollerRef}
        onMouseDown={handleMouseDown}
        onDragStart={(event) => event.preventDefault()}
        onClickCapture={handleClickCapture}
        className={[
          'ez-home-rail-scroller grid grid-flow-col gap-4 overflow-x-auto pb-5 [scrollbar-width:none]',
          shape === 'personality'
            ? 'auto-cols-[minmax(150px,48vw)] sm:auto-cols-[calc((100%-4rem)/5)] lg:auto-cols-[calc((100%-6rem)/7)] 2xl:auto-cols-[calc((100%-9rem)/10)]'
            : 'auto-cols-[minmax(220px,72vw)] sm:auto-cols-[calc((100%-3rem)/4)] lg:auto-cols-[calc((100%-4rem)/5)] 2xl:auto-cols-[calc((100%-6rem)/7)]',
        ].join(' ')}
      >
        {items.map((item, itemIndex) => (
          <PosterCard key={`${title}-${item.id}`} item={item} shape={shape} index={itemIndex} showLiveBadge={showLiveBadge} />
        ))}
      </div>
    </section>
  )
}

function PosterCard({
  item,
  shape,
  index = 0,
  showLiveBadge = false,
}: {
  item: MediaItem
  shape: 'poster' | 'video' | 'square' | 'genre' | 'channel' | 'personality'
  index?: number
  showLiveBadge?: boolean
}) {
  const image = cardImage(item, shape)
  const title = item.details?.name ?? item.name
  const personalitySubtitle = item.designation?.trim()
  const cardStyle = { animationDelay: `${Math.min(index, 9) * 55}ms` }

  if (shape === 'personality') {
    return (
      <a href={contentHref(item)} draggable={false} className="ez-home-card group block min-w-0 text-center" style={cardStyle}>
        <div className="mx-auto aspect-square w-[72%] overflow-hidden rounded-full border border-white/10 bg-white/[0.06] shadow-lg transition duration-300 group-hover:-translate-y-1 group-hover:scale-[1.04] group-hover:border-primary/70 group-hover:shadow-[0_18px_46px_rgba(212,168,67,0.18)]">
          <MediaThumbnail src={image} alt={title} className="aspect-square rounded-full" />
        </div>
        <h3 className="mt-3 line-clamp-2 text-sm font-bold leading-snug text-white">{title}</h3>
        {personalitySubtitle ? <p className="mt-1 text-xs text-white/50">{personalitySubtitle}</p> : null}
      </a>
    )
  }

  return (
    <a href={contentHref(item)} draggable={false} className="ez-home-card group block min-w-0" style={cardStyle}>
      <div
        className="relative overflow-hidden rounded-md border border-white/8 bg-white/[0.06] shadow-lg transition duration-300 group-hover:z-10 group-hover:-translate-y-1 group-hover:scale-[1.035] group-hover:border-primary/60 group-hover:shadow-[0_22px_52px_rgba(0,0,0,0.55)]"
      >
        <MediaThumbnail
          src={image}
          alt={title}
          previewSrc={shape === 'video' ? previewHref(item) : null}
          className={shape === 'video' ? 'aspect-video' : ''}
          imageClassName={shape === 'video' ? 'object-cover' : undefined}
        />
        <div className="pointer-events-none absolute inset-0 opacity-0 transition duration-300 group-hover:opacity-100">
          <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#d4a843]/75 to-transparent" />
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_8%,rgba(212,168,67,0.12),transparent_38%)]" />
        </div>
        <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80" />
        {showLiveBadge ? <LiveChannelBadge /> : null}
        {item.access && !(isNativeIosApp() && item.access.toLowerCase() === 'free') ? (
          <Badge className="absolute left-2 top-2 rounded-sm bg-black/62 text-white backdrop-blur">
            {item.access}
          </Badge>
        ) : null}
      </div>
      <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{title}</h3>
      {item.videos_count ? <p className="mt-1 text-xs text-white/58">{item.videos_count} videos</p> : null}
      {!item.videos_count && item.duration ? <p className="mt-1 text-xs text-white/58">{item.duration}</p> : null}
    </a>
  )
}

function LiveChannelBadge() {
  return (
    <span className="absolute right-2 top-2 z-30 inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-black/70 px-2.5 py-1 text-[11px] font-extrabold uppercase leading-none tracking-normal text-white shadow-lg backdrop-blur-md">
      <span className="h-2 w-2 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.95)]" aria-hidden="true" />
      LIVE
    </span>
  )
}

function cardImage(item: MediaItem | undefined, shape: 'poster' | 'video' | 'square' | 'genre' | 'channel' | 'personality') {
  if (!item) return undefined

  if (shape === 'video') {
    return item.thumbnail_url
      ?? item.details?.thumbnail_image
      ?? item.poster_image
      ?? item.poster_tv_image
      ?? item.cover_image_url
      ?? item.poster_url
  }

  return item.poster_tv_image
    ?? item.poster_image
    ?? item.cover_image_url
    ?? item.thumbnail_url
    ?? item.poster_url
    ?? item.language_image
    ?? item.profile_image
}

function contentHref(item?: MediaItem) {
  if (!item) {
    return '/'
  }

  const type = item.details?.type ?? item.type
  const params = new URLSearchParams()

  if (item.ondemand_channel_id) {
    params.set('ondemand_channel', String(item.ondemand_channel_id))
  }

  if (type === 'video' && item.slug) {
    params.set('autoplay', '1')
    return `/video-details/${item.slug}?${params.toString()}`
  }

  if (type === 'movie' && item.slug) {
    return `/movie-details/${item.slug}`
  }

  if (type === 'tvshow' && item.slug) {
    return `/tvshow-details/${item.slug}`
  }

  if (type === 'ondemand' || item.profile_url) {
    return item.username ? `/on-demand/${item.username}` : '/on-demand'
  }

  if (type === 'livetv') {
    return item.slug || item.details?.slug || item.id ? `/livetv/${item.slug ?? item.details?.slug ?? item.id}` : '/livetv'
  }

  if (shapeIsPersonality(item)) {
    const params = new URLSearchParams()
    if (item.type) {
      params.set('type', item.type)
    }
    const query = params.toString()

    return `/castcrew-detail/${item.id}${query ? `?${query}` : ''}`
  }

  return '/'
}

function shapeIsPersonality(item: MediaItem) {
  return Boolean(item.profile_image || item.type === 'actor' || item.type === 'director')
}

function previewHref(item: MediaItem) {
  return item.video_url_input ?? item.video_url ?? item.trailer_url ?? null
}

function liveTvChannelsFromDashboard(liveTv: LiveTvDashboard) {
  const channels = liveTv.channel_data ?? liveTv.category_data?.flatMap((category) => category.channel_data ?? []) ?? []

  return sortByDashboardOrder(channels)
}

function resolveMostWatchedRail(dashboard: DashboardData) {
  const dynamicRails = Object.entries(dashboard.dynamic_data ?? {})
  const selectedRail = dynamicRails.find(([slug, rail]) => {
    const slugMatch = slug.toLowerCase().includes('most-watched') || slug.toLowerCase().includes('popular-video')
    const nameMatch = (rail.name ?? '').toLowerCase().includes('most watched')

    return rail.type === 'video' && (slugMatch || nameMatch)
  })?.[1]

  return selectedRail ?? dashboard.popular_video
}

function sortByDashboardOrder(channels: MediaItem[]) {
  return [...channels].sort((a, b) => {
    const aOrder = typeof a.dashboard_order === 'number' ? a.dashboard_order : Number.MAX_SAFE_INTEGER
    const bOrder = typeof b.dashboard_order === 'number' ? b.dashboard_order : Number.MAX_SAFE_INTEGER

    return aOrder - bOrder
  })
}

function normalizeRailLimit(value?: number | string | null) {
  const limit = Number(value)

  if (!Number.isFinite(limit) || limit < 1) {
    return 15
  }

  return Math.floor(limit)
}
