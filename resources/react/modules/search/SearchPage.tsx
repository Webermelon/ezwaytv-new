import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ListVideo, Search, SlidersHorizontal } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useSpaNavigate, useSpaPath } from '@/lib/spa-router'
import { loadSearchResults, type SearchKind, type SearchResult } from './searchApi'

const filters: Array<{ label: string; value: SearchKind | 'all' }> = [
  { label: 'All', value: 'all' },
  { label: 'Videos', value: 'video' },
  { label: 'Playlists', value: 'playlist' },
  { label: 'Live TV', value: 'livetv' },
  { label: 'On Demand', value: 'ondemand' },
]

export function SearchPage() {
  const path = useSpaPath()
  const navigate = useSpaNavigate()
  const params = new URLSearchParams(path.split('?')[1] ?? '')
  const initialQuery = params.get('q') ?? params.get('search') ?? ''
  const initialType = normalizeFilter(params.get('type'))
  const [query, setQuery] = useState(initialQuery)
  const [submittedQuery, setSubmittedQuery] = useState(initialQuery)
  const [activeFilter, setActiveFilter] = useState<SearchKind | 'all'>(initialType)
  const [searched, setSearched] = useState(Boolean(initialQuery))
  const trimmedSubmittedQuery = submittedQuery.trim()
  const searchTypes = activeFilter === 'all' ? [] : [activeFilter]
  const searchQuery = useQuery({
    queryKey: ['search-results', trimmedSubmittedQuery, activeFilter],
    queryFn: () => loadSearchResults(trimmedSubmittedQuery, searchTypes),
    enabled: Boolean(trimmedSubmittedQuery),
    staleTime: 30_000,
  })
  const results = searchQuery.data ?? []

  const filteredResults = useMemo(
    () => activeFilter === 'all'
      ? results
      : results.filter((item) => activeFilter === 'ondemand'
        ? item.searchKind === 'ondemand' || item.searchKind === 'playlist'
        : item.searchKind === activeFilter),
    [activeFilter, results],
  )

  useEffect(() => {
    const nextParams = new URLSearchParams(path.split('?')[1] ?? '')
    const nextQuery = nextParams.get('q') ?? nextParams.get('search') ?? ''
    const nextType = normalizeFilter(nextParams.get('type'))

    setQuery(nextQuery)
    setSubmittedQuery(nextQuery)
    setSearched(Boolean(nextQuery))
    setActiveFilter(nextType)
  }, [path])

  const submitSearch = () => {
    const term = query.trim()
    setSubmittedQuery(term)
    setSearched(Boolean(term))

    const nextParams = new URLSearchParams()
    if (term) nextParams.set('q', term)
    if (activeFilter !== 'all') nextParams.set('type', activeFilter)
    navigate(nextParams.toString() ? `/search?${nextParams.toString()}` : '/search', { replace: true })
  }

  const changeFilter = (filter: SearchKind | 'all') => {
    setActiveFilter(filter)
    const nextParams = new URLSearchParams()
    const term = query.trim() || submittedQuery.trim()
    if (term) nextParams.set('q', term)
    if (filter !== 'all') nextParams.set('type', filter)
    navigate(nextParams.toString() ? `/search?${nextParams.toString()}` : '/search', { replace: true })
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="search" />

      <section className="px-4 pb-10 pt-12 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          <Badge className="rounded-sm bg-primary text-white">Search</Badge>
          <h1 className="mt-4 text-4xl font-black leading-none sm:text-5xl">Find something to watch</h1>

          <form
            className="mt-7 flex flex-col gap-3 rounded-md border border-white/10 bg-white/[0.055] p-3 sm:flex-row"
            onSubmit={(event) => {
              event.preventDefault()
              submitSearch()
            }}
          >
            <div className="flex min-h-12 flex-1 items-center gap-3 rounded-md bg-black/42 px-4">
              <Search className="h-5 w-5 text-white/42" />
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search videos, playlists, live channels, and on demand"
                className="h-12 min-w-0 flex-1 bg-transparent text-base font-semibold text-white outline-none placeholder:text-white/38"
                autoFocus
              />
            </div>
            <Button type="submit" className="h-12 bg-white px-6 text-black hover:bg-white/86">
              Search
            </Button>
          </form>

          <div className="mt-4 flex flex-wrap items-center gap-2">
            <span className="mr-1 inline-flex items-center gap-2 text-xs font-bold uppercase text-white/42">
              <SlidersHorizontal className="h-4 w-4" />
              Filter
            </span>
            {filters.map((filter) => (
              <button
                key={filter.value}
                type="button"
                onClick={() => changeFilter(filter.value)}
                className={[
                  'h-9 rounded-md px-3 text-xs font-bold transition',
                  activeFilter === filter.value ? 'bg-primary text-white' : 'bg-white/8 text-white/62 hover:bg-white/14 hover:text-white',
                ].join(' ')}
              >
                {filter.label}
              </button>
            ))}
          </div>
        </div>
      </section>

      <section className="px-4 pb-16 sm:px-8 lg:px-12">
        <div className="mb-4 flex items-center justify-between gap-4">
          <h2 className="text-2xl font-bold">
            {submittedQuery ? `Results for "${submittedQuery}"` : 'Search Results'}
          </h2>
          {searched ? <span className="text-sm text-white/50">{filteredResults.length} shown</span> : null}
        </div>

        {searchQuery.isFetching ? (
          <SearchSkeleton />
        ) : filteredResults.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
            {filteredResults.map((item) => (
              <SearchCard key={`${item.searchKind}-${item.id}-${item.slug ?? item.username ?? item.name}`} item={item} />
            ))}
          </div>
        ) : searched ? (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">
            No results matched.
          </div>
        ) : (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">
            Enter a search term to browse the existing library.
          </div>
        )}
      </section>
    </main>
  )
}

function SearchCard({ item }: { item: SearchResult }) {
  if (item.searchKind === 'playlist') {
    return <PlaylistSearchCard item={item} />
  }

  const image = item.poster_tv_image ?? item.poster_image ?? item.cover_image_url ?? item.avatar_image_url ?? item.profile_image ?? item.details?.thumbnail_image
  const title = item.details?.name ?? item.name

  return (
    <a href={item.href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <MediaThumbnail src={image} alt={title} previewSrc={item.searchKind === 'video' ? item.video_url_input ?? item.video_url ?? item.trailer_url ?? null : null} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/72 via-transparent to-transparent" />
        <Badge className="absolute left-3 top-3 rounded-sm bg-black/70 text-white">
          {kindLabel(item.searchKind)}
        </Badge>
        {item.duration ? <Badge className="absolute bottom-3 right-3 rounded-sm bg-black/70 text-white">{item.duration}</Badge> : null}
      </div>
      <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{title}</h3>
      <p className="mt-1 text-xs text-white/50">{item.details?.category ?? item.access ?? kindLabel(item.searchKind)}</p>
    </a>
  )
}

function PlaylistSearchCard({ item }: { item: SearchResult }) {
  const image = item.thumbnail_url ?? item.poster_image ?? item.cover_image_url ?? item.avatar_image_url ?? item.profile_image
  const videoCount = item.video_count ?? 0

  return (
    <a href={item.href} className="group block min-w-0 text-left">
      <div className="relative pt-2">
        <div className="absolute left-3 right-3 top-0 h-full rounded-md bg-white/14" />
        <div className="absolute left-1.5 right-1.5 top-1 h-full rounded-md bg-white/10" />
        <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-xl transition group-hover:scale-[1.025] group-hover:border-primary/60">
          <MediaThumbnail src={image} alt={item.name} className="aspect-video" imageClassName="object-cover" />
          <div className="absolute inset-0 bg-gradient-to-t from-black/82 via-transparent to-transparent" />
          <Badge className="absolute left-3 top-3 rounded-sm bg-black/70 text-white">
            Playlist
          </Badge>
          <span className="absolute bottom-3 right-3 z-20 inline-flex h-7 items-center gap-1.5 rounded-sm bg-black/78 px-2.5 text-xs font-black text-white shadow-lg ring-1 ring-white/10 backdrop-blur-sm">
            <ListVideo className="h-3.5 w-3.5" />
            {videoCount} {videoCount === 1 ? 'video' : 'videos'}
          </span>
        </div>
      </div>
      <h3 className="mt-3 line-clamp-2 text-sm font-black leading-snug text-white">{item.name}</h3>
      {item.description ? <p className="mt-1 line-clamp-1 text-xs text-white/52">{stripHtml(item.description)}</p> : null}
      <p className="mt-1 text-xs font-semibold text-white/50">{item.channel_name ?? 'On Demand Channel'}</p>
      <span className="mt-1 inline-flex text-xs font-bold text-white/56 transition group-hover:text-primary">View full playlist</span>
    </a>
  )
}

function SearchSkeleton() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
      {Array.from({ length: 12 }).map((_, index) => (
        <div key={index} className="aspect-[3/2] animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </div>
  )
}

function kindLabel(kind: SearchKind) {
  if (kind === 'livetv') return 'Live TV'
  if (kind === 'ondemand') return 'On Demand'
  if (kind === 'playlist') return 'Playlist'
  return 'Video'
}

function normalizeFilter(value: string | null): SearchKind | 'all' {
  return value === 'video' || value === 'livetv' || value === 'ondemand' || value === 'playlist' ? value : 'all'
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
