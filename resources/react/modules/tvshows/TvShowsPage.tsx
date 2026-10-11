import { useEffect, useMemo, useRef, useState } from 'react'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Lock, Search, Star, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { Badge } from '@/components/ui/badge'
import type { MediaItem } from '@/modules/home/types'
import { loadTvShows } from './tvshowsApi'

export function TvShowsPage() {
  const [query, setQuery] = useState('')
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const showsQuery = useInfiniteQuery({
    queryKey: ['tv-shows'],
    queryFn: ({ pageParam }) => loadTvShows(pageParam, 36),
    initialPageParam: 1,
    getNextPageParam: (lastPage, pages) => lastPage.hasMore ? pages.length + 1 : undefined,
    staleTime: 60_000,
  })
  const shows = useMemo(() => uniqueById(showsQuery.data?.pages.flatMap((page) => page.items) ?? []), [showsQuery.data])
  const filteredShows = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) return shows
    return shows.filter((show) => [show.name, show.description, ...(show.genres ?? []).map((genre) => genre.name)]
      .filter(Boolean)
      .some((value) => stripHtml(String(value)).toLowerCase().includes(term)))
  }, [query, shows])

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return
    const observer = new IntersectionObserver(([entry]) => {
      if (entry?.isIntersecting && showsQuery.hasNextPage && !showsQuery.isFetchingNextPage) showsQuery.fetchNextPage()
    }, { rootMargin: '600px 0px' })
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [showsQuery.fetchNextPage, showsQuery.hasNextPage, showsQuery.isFetchingNextPage])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="tvshows" />
      <section className="px-4 py-8 sm:px-8 lg:px-12">
        <div className="mb-6 flex items-end justify-between gap-5">
          <div>
            <div className="mb-2 inline-flex items-center gap-2 text-xs font-black uppercase text-primary"><Tv className="h-4 w-4" />Series</div>
            <h1 className="text-3xl font-black sm:text-4xl">TV Shows</h1>
          </div>
          <span className="text-sm text-white/48">{filteredShows.length} shown</span>
        </div>
        <label className="mb-8 flex min-h-12 max-w-2xl items-center gap-3 rounded-md border border-white/10 bg-white/[0.045] px-4">
          <Search className="h-5 w-5 text-white/44" />
          <span className="sr-only">Search TV shows</span>
          <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search TV shows" className="h-11 min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-white/42" />
        </label>

        {showsQuery.isLoading ? <ShowGridSkeleton /> : showsQuery.isError ? (
          <EmptyState message="TV shows could not be loaded right now." />
        ) : filteredShows.length ? (
          <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
            {filteredShows.map((show) => <ShowCard key={show.id} show={show} />)}
          </div>
        ) : <EmptyState message="No TV shows are available yet." />}

        <div ref={sentinelRef} className="mt-8 flex min-h-14 items-center justify-center">
          {showsQuery.isFetchingNextPage ? <span className="h-6 w-6 animate-spin rounded-full border-2 border-white/20 border-t-primary" /> : null}
        </div>
      </section>
    </main>
  )
}

function ShowCard({ show }: { show: MediaItem }) {
  const access = (show.movie_access ?? show.access ?? 'free').toLowerCase()
  const locked = access === 'paid' && !Boolean(show.has_content_access)
  return (
    <a href={`/tvshow-details/${show.id}`} className="group min-w-0">
      <div className="relative aspect-[2/3] overflow-hidden rounded-md border border-white/10 bg-[#111] shadow-lg transition duration-300 group-hover:-translate-y-1 group-hover:border-primary/65">
        <MediaThumbnail src={show.poster_image ?? show.poster_url} alt={show.name} className="h-full w-full" imageClassName="object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/76 via-transparent to-transparent" />
        {locked ? <div className="absolute inset-0 bg-black/35" /> : null}
        <Badge className="absolute left-2 top-2 rounded-sm bg-primary text-[10px] font-black uppercase text-black">{locked ? <Lock className="mr-1 h-3 w-3" /> : null}{access}</Badge>
        <div className="absolute right-2 top-2"><WatchlistToggleButton entertainmentId={show.id} type="tvshow" initialInWatchlist={show.is_watch_list} /></div>
        {show.imdb_rating ? <span className="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-sm bg-black/75 px-2 py-1 text-[10px] font-bold"><Star className="h-3 w-3 fill-primary text-primary" />{show.imdb_rating}</span> : null}
      </div>
      <h2 className="mt-2 line-clamp-2 text-sm font-bold leading-snug">{show.name}</h2>
      <p className="mt-1 line-clamp-1 text-xs text-white/48">{show.genres?.map((genre) => genre.name).filter(Boolean).join(' · ') || String(show.release_date ?? '').slice(0, 4) || 'eZWay TV'}</p>
    </a>
  )
}

function ShowGridSkeleton() {
  return <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">{Array.from({ length: 16 }).map((_, index) => <div key={index} className="aspect-[2/3] animate-pulse rounded-md bg-white/[0.05]" />)}</div>
}

function EmptyState({ message }: { message: string }) {
  return <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">{message}</div>
}

function uniqueById(items: MediaItem[]) {
  const seen = new Set<string | number>()
  return items.filter((item) => seen.has(item.id) ? false : (seen.add(item.id), true))
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
