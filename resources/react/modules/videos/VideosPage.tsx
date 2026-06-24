import { useEffect, useMemo, useRef, useState } from 'react'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Filter, Lock, Play, Search } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import type { MediaItem } from '@/modules/home/types'
import { loadVideosPage } from './videosApi'

const accessFilters = ['all', 'free', 'paid', 'pay-per-view'] as const

export function VideosPage() {
  const [query, setQuery] = useState('')
  const [access, setAccess] = useState<(typeof accessFilters)[number]>('all')
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const categorySlug = getCategoryFromPath()
  const videosQuery = useInfiniteQuery({
    queryKey: ['videos-page', categorySlug],
    queryFn: ({ pageParam }) => loadVideosPage(categorySlug, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage, allPages) => lastPage.hasMore ? allPages.length + 1 : undefined,
    staleTime: 60_000,
  })
  const videos = useMemo(
    () => uniqueById(videosQuery.data?.pages.flatMap((pageData) => pageData.items) ?? []),
    [videosQuery.data],
  )
  const {
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
    isLoading,
  } = videosQuery

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver((entries) => {
      const entry = entries[0]
      if (!entry?.isIntersecting || isLoading || isFetchingNextPage || !hasNextPage) return

      fetchNextPage()
    }, { rootMargin: '600px 0px' })

    observer.observe(sentinel)

    return () => {
      observer.disconnect()
    }
  }, [fetchNextPage, hasNextPage, isFetchingNextPage, isLoading])

  const filteredVideos = useMemo(() => {
    const term = query.trim().toLowerCase()

    return videos.filter((video) => {
      const matchesQuery = !term || [video.name, video.short_desc, video.description]
        .filter(Boolean)
        .some((value) => stripHtml(String(value)).toLowerCase().includes(term))
      const matchesAccess = access === 'all' || video.access === access

      return matchesQuery && matchesAccess
    })
  }, [access, query, videos])


  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="videos" />

      <section className="px-4 py-8 sm:px-8 lg:px-12">
        <div className="mb-6 flex flex-col gap-3 rounded-md border border-white/10 bg-white/[0.045] p-3 sm:flex-row sm:items-center">
          <div className="flex min-h-11 flex-1 items-center gap-2 rounded-md bg-black/38 px-3">
            <Search className="h-4 w-4 text-white/44" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search videos"
              className="h-10 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
            />
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Filter className="hidden h-4 w-4 text-white/44 sm:block" />
            {accessFilters.map((filter) => (
              <button
                key={filter}
                type="button"
                onClick={() => setAccess(filter)}
                className={[
                  'h-9 rounded-md px-3 text-xs font-bold capitalize transition',
                  access === filter ? 'bg-primary text-white' : 'bg-white/8 text-white/62 hover:bg-white/14 hover:text-white',
                ].join(' ')}
              >
                {filter.replaceAll('-', ' ')}
              </button>
            ))}
          </div>
        </div>

        <div className="mb-3 flex items-center justify-between gap-4">
          <h2 className="text-2xl font-bold">{categorySlug ? `Category: ${categorySlug}` : 'All Videos'}</h2>
          <span className="text-sm text-white/50">{filteredVideos.length} shown{hasNextPage ? ' - loading more as you scroll' : ''}</span>
        </div>

        {isLoading ? (
          <VideoGridSkeleton />
        ) : filteredVideos.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
            {filteredVideos.map((video) => (
              <VideoCard key={video.id} video={video} />
            ))}
          </div>
        ) : (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">
            No videos matched.
          </div>
        )}
        <div ref={sentinelRef} className="mt-8 flex min-h-14 items-center justify-center">
          {isFetchingNextPage ? (
            <div className="flex items-center gap-3 text-sm font-semibold text-white/58">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/20 border-t-primary" />
              Loading more videos
            </div>
          ) : hasNextPage && !isLoading ? (
            <button
              type="button"
              onClick={() => fetchNextPage()}
              className="rounded-md border border-white/10 bg-white/[0.06] px-4 py-2 text-sm font-bold text-white/72 hover:bg-white/[0.1] hover:text-white"
            >
              Load more
            </button>
          ) : !isLoading && videos.length > 0 ? (
            <span className="text-sm text-white/38">End of videos</span>
          ) : null}
        </div>
      </section>
    </main>
  )
}

function uniqueById(items: MediaItem[]) {
  const seen = new Set<string | number>()

  return items.filter((item) => {
    if (seen.has(item.id)) return false
    seen.add(item.id)
    return true
  })
}

function VideoCard({ video }: { video: MediaItem }) {
  const locked = isPremiumVideoCard(video)
  const inWatchlist = video.is_watch_list ?? video.is_in_watchlist

  return (
    <a href={videoHref(video)} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <MediaThumbnail src={video.poster_image} alt={video.name} previewSrc={previewHref(video)} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/88 via-black/10 to-transparent" />
        {locked ? <div className="absolute inset-0 bg-black/32" /> : null}
        <div className={[
          'absolute left-3 top-3 flex h-10 w-10 items-center justify-center rounded-full backdrop-blur',
          locked ? 'bg-primary text-black' : 'bg-white/20 text-white',
        ].join(' ')}>
          {locked ? <Lock className="h-5 w-5" /> : <Play className="h-5 w-5 fill-current" />}
        </div>
        {locked ? (
          <Badge className="absolute right-3 top-3 rounded-sm bg-primary text-black">
            <Lock className="mr-1 h-3.5 w-3.5" />
            Premium
          </Badge>
        ) : video.access ? <Badge className="absolute right-3 top-3 rounded-sm bg-black/70 text-white">{video.access}</Badge> : null}
        <div className="absolute bottom-3 left-3 z-10">
          <WatchlistToggleButton
            entertainmentId={video.id}
            type="video"
            initialInWatchlist={inWatchlist}
          />
        </div>
        {video.duration ? <Badge className="absolute bottom-3 right-3 rounded-sm bg-black/70 text-white">{video.duration}</Badge> : null}
      </div>
      <div className="mt-2 grid gap-1">
        <h3 className="line-clamp-2 text-sm font-bold leading-snug text-white">{video.name}</h3>
        <ChannelName video={video} />
      </div>
    </a>
  )
}

function ChannelName({ video }: { video: MediaItem }) {
  const channelName = video.channel_name || video.username || 'eZWay TV'
  const channelSlug = video.channel_username || ''
  const channelHref = video.profile_url || (channelSlug ? `/on-demand/${channelSlug}` : '')
  const avatar = video.profile_image || video.avatar_image_url || video.cover_image_url || ''
  const content = (
    <>
      <span className="flex h-7 w-7 shrink-0 overflow-hidden rounded-full bg-white/10 ring-1 ring-white/10">
        {avatar ? (
          <img src={avatar} alt="" className="h-full w-full object-cover" loading="lazy" decoding="async" />
        ) : (
          <span className="flex h-full w-full items-center justify-center text-[10px] font-black text-white/58">{channelInitials(channelName)}</span>
        )}
      </span>
      <span className="line-clamp-1 min-w-0 text-xs font-semibold text-white/48 transition group-hover:text-white/70">{channelName}</span>
    </>
  )

  if (!channelHref) {
    return <div className="flex min-w-0 items-center gap-2">{content}</div>
  }

  return (
    <a
      href={channelHref}
      onClick={(event) => event.stopPropagation()}
      className="flex w-fit max-w-full min-w-0 items-center gap-2"
    >
      {content}
    </a>
  )
}

function channelInitials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || 'EZ'
}

function VideoGridSkeleton() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
      {Array.from({ length: 12 }).map((_, index) => (
        <div key={index} className="aspect-[3/2] animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </div>
  )
}

function videoHref(video?: MediaItem) {
  if (!video?.slug) {
    return '/videos'
  }

  const params = new URLSearchParams({ autoplay: '1' })
  if (video.ondemand_channel_id) {
    params.set('ondemand_channel', String(video.ondemand_channel_id))
  }

  return `/video-details/${video.slug}?${params.toString()}`
}

function previewHref(video: MediaItem) {
  return video.video_url_input ?? video.video_url ?? video.trailer_url ?? null
}

function isPremiumVideoCard(video?: MediaItem) {
  if (!video || video.access !== 'paid') return false

  if (typeof video.has_content_access !== 'undefined' && video.has_content_access !== null) {
    return !Boolean(video.has_content_access)
  }

  return true
}

function getCategoryFromPath() {
  const match = window.location.pathname.match(/^\/(?:react-videos|spa\/videos|videos\/category)\/([^/]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
