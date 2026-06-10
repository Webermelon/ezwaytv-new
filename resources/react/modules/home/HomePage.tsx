import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronRight, Info, Play } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { AppHeader } from '@/components/AppHeader'
import { AdBannerSlider } from '@/components/AdBannerSlider'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { loadHomeModule } from './homeApi'
import type { DashboardData, LiveTvDashboard, MediaItem } from './types'

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

  const liveChannels = useMemo(
    () => state.liveTv.category_data?.flatMap((category) => category.channel_data ?? []) ?? [],
    [state.liveTv.category_data],
  )
  const featured = state.liveTv.slider?.[0] ?? state.videos[0] ?? liveChannels[0]

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <Hero featured={featured} loading={homeQuery.isLoading} />

      <section className="relative z-10 -mt-10 space-y-9 px-4 pb-16 sm:px-8 lg:px-12">
        {homeQuery.isError ? (
          <div className="rounded-md border border-red-500/30 bg-red-950/40 px-4 py-3 text-sm text-red-100">
            Some home APIs did not respond. Existing backend remains untouched.
          </div>
        ) : null}

        <AdBannerSlider placement="home" className="-mx-4 -mt-2 sm:-mx-8 lg:-mx-12" />
        <Rail title="Live TV Now" items={liveChannels} href="/livetv" shape="square" />
        <Rail title="On Demand Channels" items={state.ondemandChannels} href="/on-demand" shape="channel" />
        <Rail title="Latest Videos" items={state.videos} href="/videos" shape="video" />
        <Rail
          title={state.dashboard.personality?.name ?? state.dashboard.popular_personality?.name ?? 'Popular Personalities'}
          items={state.dashboard.personality?.data ?? state.dashboard.popular_personality?.data ?? []}
          href="/castcrew-list"
          shape="personality"
        />
        <Rail title={state.dashboard.latest_movie?.name ?? 'New Released Movies'} items={state.dashboard.latest_movie?.data ?? []} href="/movies" shape="poster" />
      </section>
    </main>
  )
}

function Hero({ featured, loading }: { featured?: MediaItem; loading: boolean }) {
  const title = featured?.details?.name ?? featured?.name ?? 'eZWay TV'
  const category = featured?.details?.category ?? featured?.type ?? 'Streaming'

  return (
    <section className="relative min-h-[70vh] overflow-hidden bg-[#050505]">
      <img src={homeHeroImage} alt="" className="absolute inset-0 h-full w-full object-cover opacity-80" />
      <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.9)_36%,rgba(5,5,5,0.58)_66%,rgba(5,5,5,0.24)_100%)]" />
      <div className="absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-[#050505] via-[#050505]/82 to-transparent" />

      <div className="relative z-10 flex min-h-[70vh] items-center px-4 pb-20 pt-16 sm:px-8 lg:px-12">
        <div className="max-w-3xl">
          <Badge className={[
            'mb-4 w-fit rounded-sm px-3 py-1 text-xs uppercase',
            loading ? 'bg-white/14 text-white' : 'bg-[#d4a843] text-black',
          ].join(' ')}>
            {loading ? 'Featured' : category}
          </Badge>
          <h1 className="max-w-2xl text-4xl font-black leading-none text-white sm:text-6xl lg:text-7xl">
            {title}
          </h1>
          <p className="mt-5 max-w-xl text-base leading-7 text-white/72 sm:text-lg">
            Stream standout shows, live channels, and on-demand stories from the eZWay TV network.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button asChild size="lg" className="bg-[#d4a843] text-black hover:bg-[#edc342]">
              <a href={contentHref(featured)}>
                <Play className="h-5 w-5 fill-current" />
                Watch Now
              </a>
            </Button>
            <Button asChild size="lg" variant="secondary" className="bg-white/12 text-white hover:bg-white/22">
              <a href={contentHref(featured)}>
                <Info className="h-5 w-5" />
                Details
              </a>
            </Button>
          </div>
        </div>
      </div>
    </section>
  )
}

function Rail({
  title,
  items,
  href,
  shape,
}: {
  title: string
  items: MediaItem[]
  href?: string
  shape: 'poster' | 'video' | 'square' | 'genre' | 'channel' | 'personality'
}) {
  if (items.length === 0) return null

  return (
    <section>
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
        className={[
          'grid grid-flow-col gap-4 overflow-x-auto pb-5 [scrollbar-width:none]',
          shape === 'personality'
            ? 'auto-cols-[minmax(150px,48vw)] sm:auto-cols-[calc((100%-4rem)/5)] lg:auto-cols-[calc((100%-6rem)/7)] 2xl:auto-cols-[calc((100%-9rem)/10)]'
            : 'auto-cols-[minmax(220px,72vw)] sm:auto-cols-[calc((100%-3rem)/4)] lg:auto-cols-[calc((100%-4rem)/5)] 2xl:auto-cols-[calc((100%-6rem)/7)]',
        ].join(' ')}
      >
        {items.map((item) => (
          <PosterCard key={`${title}-${item.id}`} item={item} shape={shape} />
        ))}
      </div>
    </section>
  )
}

function PosterCard({ item, shape }: { item: MediaItem; shape: 'poster' | 'video' | 'square' | 'genre' | 'channel' | 'personality' }) {
  const image = item.poster_tv_image ?? item.poster_image ?? item.cover_image_url ?? item.thumbnail_url ?? item.poster_url ?? item.language_image ?? item.profile_image
  const title = item.details?.name ?? item.name

  if (shape === 'personality') {
    return (
      <a href={contentHref(item)} className="group block min-w-0 text-center">
        <div className="mx-auto aspect-square w-[72%] overflow-hidden rounded-full border border-white/10 bg-white/[0.06] shadow-lg transition duration-300 group-hover:scale-[1.035] group-hover:border-primary/70">
          <MediaThumbnail src={image} alt={title} className="aspect-square rounded-full" />
        </div>
        <h3 className="mt-3 line-clamp-2 text-sm font-bold leading-snug text-white">{title}</h3>
        {item.type ? <p className="mt-1 text-xs capitalize text-white/50">{item.type}</p> : null}
      </a>
    )
  }

  return (
    <a href={contentHref(item)} className="group block min-w-0">
      <div
        className="relative overflow-hidden rounded-md border border-white/8 bg-white/[0.06] shadow-lg transition duration-300 group-hover:z-10 group-hover:scale-[1.035] group-hover:border-primary/60"
      >
        <MediaThumbnail src={image} alt={title} previewSrc={shape === 'video' ? previewHref(item) : null} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80" />
        {item.access ? (
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

function contentHref(item?: MediaItem) {
  if (!item) {
    return '/'
  }

  const type = item.details?.type ?? item.type
  const params = new URLSearchParams()

  if (item.ondemand_channel_id) {
    params.set('ondemand_channel', String(item.ondemand_channel_id))
  }

  if (type === 'ondemand' || item.profile_url) {
    return item.username ? `/on-demand/${item.username}` : '/on-demand'
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
