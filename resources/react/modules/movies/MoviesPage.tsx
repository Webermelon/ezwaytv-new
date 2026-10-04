import { useEffect, useMemo, useRef, useState } from 'react'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Film, Filter, Lock, Search, Star } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { Badge } from '@/components/ui/badge'
import { isNativeIosApp } from '@/lib/native-platform'
import type { MediaItem } from '@/modules/home/types'
import { loadMoviesPage } from './moviesApi'

const accessFilters = ['all', 'free', 'paid'] as const

export function MoviesPage() {
  const [query, setQuery] = useState('')
  const [access, setAccess] = useState<(typeof accessFilters)[number]>('all')
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const routeFilter = getMovieRouteFilter()
  const visibleAccessFilters = isNativeIosApp() ? accessFilters.filter((item) => item !== 'free') : accessFilters
  const moviesQuery = useInfiniteQuery({
    queryKey: ['movies-page', routeFilter.genreId, routeFilter.language],
    queryFn: ({ pageParam }) => loadMoviesPage(pageParam, 36, routeFilter.genreId, routeFilter.language),
    initialPageParam: 1,
    getNextPageParam: (lastPage, pages) => lastPage.hasMore ? pages.length + 1 : undefined,
    staleTime: 60_000,
  })
  const movies = useMemo(
    () => uniqueById(moviesQuery.data?.pages.flatMap((page) => page.items) ?? []),
    [moviesQuery.data],
  )
  const filteredMovies = useMemo(() => {
    const term = query.trim().toLowerCase()

    return movies.filter((movie) => {
      const searchable = [movie.name, movie.description, ...(movie.genres ?? []).map((genre) => genre.name)]
        .filter(Boolean)
        .map((value) => stripHtml(String(value)).toLowerCase())
      const movieAccess = normalizeAccess(movie)

      return (!term || searchable.some((value) => value.includes(term)))
        && (access === 'all' || movieAccess === access)
    })
  }, [access, movies, query])

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver(([entry]) => {
      if (!entry?.isIntersecting || moviesQuery.isLoading || moviesQuery.isFetchingNextPage || !moviesQuery.hasNextPage) return
      moviesQuery.fetchNextPage()
    }, { rootMargin: '600px 0px' })

    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [moviesQuery.fetchNextPage, moviesQuery.hasNextPage, moviesQuery.isFetchingNextPage, moviesQuery.isLoading])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="movies" />

      <section className="px-4 py-8 sm:px-8 lg:px-12">
        <div className="mb-6 flex items-end justify-between gap-5">
          <div>
            <div className="mb-2 inline-flex items-center gap-2 text-xs font-black uppercase text-primary">
              <Film className="h-4 w-4" />
              Movies
            </div>
            <h1 className="text-3xl font-black sm:text-4xl">{routeFilter.title}</h1>
          </div>
          <span className="shrink-0 text-sm text-white/48">{filteredMovies.length} shown</span>
        </div>

        <div className="mb-8 flex flex-col gap-3 rounded-md border border-white/10 bg-white/[0.045] p-3 sm:flex-row sm:items-center">
          <label className="flex min-h-11 flex-1 items-center gap-2 rounded-md bg-black/38 px-3">
            <Search className="h-4 w-4 text-white/44" />
            <span className="sr-only">Search movies</span>
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search movies"
              className="h-10 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
            />
          </label>
          <div className="flex flex-wrap items-center gap-2">
            <Filter className="hidden h-4 w-4 text-white/44 sm:block" />
            {visibleAccessFilters.map((filter) => (
              <button
                key={filter}
                type="button"
                onClick={() => setAccess(filter)}
                className={[
                  'h-9 rounded-md px-3 text-xs font-bold capitalize transition',
                  access === filter ? 'bg-primary text-black' : 'bg-white/8 text-white/62 hover:bg-white/14 hover:text-white',
                ].join(' ')}
              >
                {filter}
              </button>
            ))}
          </div>
        </div>

        {moviesQuery.isLoading ? (
          <MovieGridSkeleton />
        ) : moviesQuery.isError ? (
          <EmptyState message="Movies could not be loaded right now." />
        ) : filteredMovies.length > 0 ? (
          <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
            {filteredMovies.map((movie) => <MovieCard key={movie.id} movie={movie} />)}
          </div>
        ) : (
          <EmptyState message="No movies matched." />
        )}

        <div ref={sentinelRef} className="mt-8 flex min-h-14 items-center justify-center">
          {moviesQuery.isFetchingNextPage ? (
            <div className="flex items-center gap-3 text-sm font-semibold text-white/58">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/20 border-t-primary" />
              Loading more movies
            </div>
          ) : moviesQuery.hasNextPage && !moviesQuery.isLoading ? (
            <button type="button" onClick={() => moviesQuery.fetchNextPage()} className="rounded-md border border-white/10 bg-white/[0.06] px-4 py-2 text-sm font-bold text-white/72 hover:bg-white/[0.1] hover:text-white">
              Load more
            </button>
          ) : null}
        </div>
      </section>
    </main>
  )
}

function MovieCard({ movie }: { movie: MediaItem }) {
  const access = normalizeAccess(movie)
  const locked = access === 'paid' && !Boolean(movie.has_content_access)
  const rating = movie.imdb_rating ? String(movie.imdb_rating) : ''

  return (
    <a href={`/movie-details/${movie.slug ?? movie.id}`} className="group min-w-0">
      <div className="relative aspect-[9/16] overflow-hidden rounded-md border border-white/10 bg-[#111] shadow-lg transition duration-300 group-hover:-translate-y-1 group-hover:border-primary/65 group-hover:shadow-[0_20px_42px_rgba(0,0,0,0.6)]">
        <MediaThumbnail src={movie.poster_image ?? movie.poster_url} alt={movie.name} className="aspect-[9/16]" imageClassName="object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/72 via-transparent to-black/5" />
        {locked ? <div className="absolute inset-0 bg-black/38" /> : null}
        {access !== 'free' || !isNativeIosApp() ? (
          <Badge className="absolute left-2 top-2 z-20 rounded-sm bg-primary text-[10px] font-black uppercase text-black">
            {locked ? <Lock className="mr-1 h-3 w-3" /> : null}
            {access}
          </Badge>
        ) : null}
        <div className="absolute right-2 top-2 z-20">
          <WatchlistToggleButton entertainmentId={movie.id} type="movie" initialInWatchlist={movie.is_watch_list ?? movie.is_in_watchlist} />
        </div>
        {rating ? (
          <span className="absolute bottom-2 right-2 z-20 inline-flex items-center gap-1 rounded-sm bg-black/75 px-2 py-1 text-[10px] font-bold text-white">
            <Star className="h-3 w-3 fill-primary text-primary" />
            {rating}
          </span>
        ) : null}
      </div>
      <h2 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{movie.name}</h2>
      <p className="mt-1 line-clamp-1 text-xs text-white/48">{movie.genres?.map((genre) => genre.name).join(' · ') || formatYear(movie.release_date)}</p>
    </a>
  )
}

function MovieGridSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
      {Array.from({ length: 16 }).map((_, index) => <div key={index} className="aspect-[9/16] animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />)}
    </div>
  )
}

function EmptyState({ message }: { message: string }) {
  return <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">{message}</div>
}

function normalizeAccess(movie: MediaItem) {
  return (movie.access ?? movie.movie_access ?? 'free').toLowerCase()
}

function uniqueById(items: MediaItem[]) {
  const seen = new Set<string | number>()
  return items.filter((item) => seen.has(item.id) ? false : (seen.add(item.id), true))
}

function getMovieRouteFilter() {
  const path = window.location.pathname
  const genreMatch = path.match(/^\/movies\/genre\/([^/]+)/)
  if (genreMatch?.[1]) return { genreId: decodeURIComponent(genreMatch[1]), language: '', title: 'Movies by Genre' }

  const languageMatch = path.match(/^\/movies\/([^/]+)/)
  const language = languageMatch?.[1] ? decodeURIComponent(languageMatch[1]) : ''
  return { genreId: '', language, title: language ? `${language} Movies` : 'All Movies' }
}

function formatYear(value?: string | null) {
  if (!value) return 'eZWay TV'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? 'eZWay TV' : String(date.getFullYear())
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
