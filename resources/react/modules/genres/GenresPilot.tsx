import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertCircle, RefreshCcw, Tags } from 'lucide-react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { api, ApiError } from '@/lib/api'

type GenreRecord = {
  id?: number | string
  name?: string
  title?: string
  slug?: string
  status?: number | boolean | string
  image?: string
  poster_url?: string
  poster_image?: string
  [key: string]: unknown
}

export function GenresPilot() {
  const genresQuery = useQuery({
    queryKey: ['genres'],
    queryFn: loadGenres,
    staleTime: 5 * 60_000,
  })
  const records = genresQuery.data ?? []
  const visibleRecords = useMemo(() => records.slice(0, 6), [records])
  const errorMessage =
    genresQuery.error instanceof ApiError
      ? `API returned ${genresQuery.error.status}`
      : genresQuery.error instanceof Error
        ? genresQuery.error.message
        : 'Unable to load genres'

  return (
    <section className="rounded-md border border-white/10 bg-card/70 p-5 shadow-2xl">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 items-center justify-center rounded-sm bg-primary text-primary-foreground">
            <Tags className="h-5 w-5" />
          </span>
          <div>
            <h2 className="text-xl font-bold text-white">Browse by genre</h2>
            <p className="text-sm text-muted-foreground">Live data from existing API: /api/genre-list</p>
          </div>
        </div>
        <Button variant="secondary" className="bg-white/10 text-white hover:bg-white/20" onClick={() => genresQuery.refetch()} disabled={genresQuery.isFetching}>
          <RefreshCcw className={genresQuery.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          Refresh
        </Button>
      </div>

      <div className="mt-6">
        {genresQuery.isLoading ? (
          <div className="flex gap-3 overflow-hidden">
            {Array.from({ length: 6 }).map((_, index) => (
              <div key={index} className="h-44 min-w-44 animate-pulse rounded-md bg-muted" />
            ))}
          </div>
        ) : genresQuery.isError ? (
          <div className="flex gap-3 rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            <div>
              <p className="font-medium">Genres API is not available right now.</p>
              <p className="mt-1 text-destructive/80">{errorMessage}</p>
            </div>
          </div>
        ) : visibleRecords.length ? (
          <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-3">
            {visibleRecords.map((genre, index) => (
              <article
                key={`${genre.id ?? genre.slug ?? index}`}
                className="group min-w-48 overflow-hidden rounded-md border border-white/10 bg-background shadow-xl transition duration-300 hover:-translate-y-1 hover:border-primary/70"
              >
                <div className="relative bg-muted">
                  <MediaThumbnail src={getPoster(genre)} alt={genre.name ?? genre.title ?? `Genre ${index + 1}`} />
                  <div className="absolute inset-0 bg-gradient-to-t from-black via-black/25 to-transparent opacity-90" />
                  <div className="absolute bottom-3 left-3 right-3">
                    <p className="truncate text-base font-bold text-white">{genre.name ?? genre.title ?? `Genre ${index + 1}`}</p>
                    <p className="mt-1 truncate text-xs text-white/58">{genre.slug ?? `Record ID ${genre.id ?? index + 1}`}</p>
                  </div>
                </div>
                <div className="flex items-center justify-between gap-3 p-3">
                  <Badge variant={isActive(genre.status) ? 'success' : 'secondary'}>
                    {isActive(genre.status) ? 'Active' : 'Inactive'}
                  </Badge>
                  <span className="text-xs font-medium text-primary opacity-0 transition group-hover:opacity-100">Explore</span>
                </div>
              </article>
            ))}
          </div>
        ) : (
          <div className="rounded-md border border-border bg-background p-4 text-sm text-muted-foreground">
            The API responded, but no genres were found.
          </div>
        )}
      </div>
    </section>
  )
}

async function loadGenres() {
  const payload = await api.get<unknown>('/api/genre-list')

  return normalizeRecords(payload)
}

function getPoster(record: GenreRecord) {
  return record.poster_image ?? record.poster_url ?? record.image
}

function isActive(status: GenreRecord['status']) {
  return status === 1 || status === true || status === '1' || status === 'active'
}

function normalizeRecords(payload: unknown): GenreRecord[] {
  if (Array.isArray(payload)) {
    return payload as GenreRecord[]
  }

  if (payload && typeof payload === 'object') {
    const objectPayload = payload as Record<string, unknown>
    const candidates = [
      objectPayload.data,
      objectPayload.genres,
      objectPayload.items,
      objectPayload.results,
    ]

    for (const candidate of candidates) {
      if (Array.isArray(candidate)) {
        return candidate as GenreRecord[]
      }
    }
  }

  return []
}
