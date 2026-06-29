import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bookmark, Loader2, Play, Trash2, X } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { AccountHero, AccountSidebar, AuthRequired, Notice, readApiError } from '@/modules/account/ProfileDetailsPage'
import { loadWatchlist, removeWatchlistItem, type WatchlistItem } from '@/modules/account/watchlistApi'

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'movie', label: 'Movies' },
  { key: 'tvshow', label: 'TV Shows' },
  { key: 'video', label: 'Videos' },
]

export function WatchlistPage() {
  const queryClient = useQueryClient()
  const [activeType, setActiveType] = useState('all')
  const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)

  const watchlistQuery = useQuery({
    queryKey: ['watchlist', activeType],
    queryFn: () => loadWatchlist(activeType),
    enabled: isAuthenticated(),
  })

  const items = watchlistQuery.data?.items ?? []
  const total = watchlistQuery.data?.meta.total ?? items.length

  const removeMutation = useMutation({
    mutationFn: removeWatchlistItem,
    onSuccess: (message) => {
      setNotice({ tone: 'success', text: message || 'Removed from watchlist.' })
      queryClient.invalidateQueries({ queryKey: ['watchlist'] })
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Item could not be removed.') })
    },
  })

  const countsLabel = useMemo(() => {
    if (watchlistQuery.isLoading) return 'Loading'
    return `${total} ${total === 1 ? 'item' : 'items'}`
  }, [total, watchlistQuery.isLoading])

  if (!isAuthenticated()) {
    return <AuthRequired title="My Watchlist" />
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <AccountHero title="My Watchlist" description="Keep track of movies, shows, and videos you want to watch next." actionLabel="Explore Content" actionHref="/" />

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1800px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/watch-list" />

          <div className="min-w-0">
            {notice ? <Notice tone={notice.tone} text={notice.text} onClose={() => setNotice(null)} /> : null}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-2xl font-black">Saved Titles</h2>
                <p className="mt-1 text-sm font-semibold text-white/46">{countsLabel}</p>
              </div>
              <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => (
                  <button
                    key={tab.key}
                    type="button"
                    onClick={() => setActiveType(tab.key)}
                    className={[
                      'h-10 rounded-md border px-4 text-sm font-black transition',
                      activeType === tab.key
                        ? 'border-[#d4a843]/45 bg-[#d4a843]/12 text-[#edc342]'
                        : 'border-white/10 bg-white/[0.035] text-white/62 hover:bg-white/[0.07] hover:text-white',
                    ].join(' ')}
                  >
                    {tab.label}
                  </button>
                ))}
              </div>
            </div>

            {watchlistQuery.isLoading ? (
              <div className="flex min-h-72 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
                <Loader2 className="h-6 w-6 animate-spin text-[#edc342]" />
              </div>
            ) : watchlistQuery.isError ? (
              <div className="rounded-md border border-red-400/20 bg-red-500/10 p-8 text-center text-red-100">
                {readApiError(watchlistQuery.error, 'Watchlist could not be loaded.')}
              </div>
            ) : items.length > 0 ? (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                {items.map((item) => (
                  <WatchlistCard
                    key={`${item.entertainment_type ?? item.type}-${item.entertainment_id ?? item.id}`}
                    item={item}
                    removing={removeMutation.isPending}
                    onRemove={() => removeMutation.mutate(item)}
                  />
                ))}
              </div>
            ) : (
              <EmptyWatchlist activeType={activeType} />
            )}
          </div>
        </div>
      </section>
    </main>
  )
}

function WatchlistCard({ item, removing, onRemove }: { item: WatchlistItem; removing: boolean; onRemove: () => void }) {
  const title = item.name ?? item.details?.name ?? 'Untitled'
  const type = item.entertainment_type ?? item.type ?? item.details?.type ?? 'video'
  const href = itemHref(item)
  const image = watchlistImage(item, type)

  return (
    <article className="group overflow-hidden rounded-md border border-white/10 bg-white/[0.035] transition hover:border-white/20">
      <a href={href} className="relative block aspect-video overflow-hidden bg-white/[0.04]">
        {image ? (
          <img src={image} alt={title} className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-white/28">
            <Bookmark className="h-10 w-10" />
          </div>
        )}
        <span className="absolute left-3 top-3 rounded-md border border-black/30 bg-black/72 px-2 py-1 text-xs font-black uppercase text-white/86">
          {typeLabel(type)}
        </span>
      </a>
      <div className="p-4">
        <h3 className="line-clamp-2 min-h-11 text-base font-black leading-snug">{title}</h3>
        <p className="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-white/48">{item.description ?? item.details?.description ?? 'Saved to your watchlist.'}</p>
        <div className="mt-4 grid grid-cols-[minmax(0,1fr)_42px] gap-2">
          <Button asChild className="bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
            <a href={href}>
              <Play className="h-4 w-4" />
              View
            </a>
          </Button>
          <button
            type="button"
            onClick={onRemove}
            disabled={removing}
            aria-label={`Remove ${title} from watchlist`}
            title="Remove"
            className="flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/[0.055] text-white transition hover:bg-red-500/15 hover:text-red-100 disabled:opacity-50"
          >
            {removing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
          </button>
        </div>
      </div>
    </article>
  )
}

function watchlistImage(item: WatchlistItem, type: string) {
  if (type === 'video') {
    return item.thumbnail_image
      ?? item.thumbnail_url
      ?? item.details?.thumbnail_image
      ?? item.poster_image
      ?? item.poster_tv_image
  }

  return item.poster_tv_image
    ?? item.poster_image
    ?? item.thumbnail_image
    ?? item.thumbnail_url
    ?? item.details?.thumbnail_image
}

function EmptyWatchlist({ activeType }: { activeType: string }) {
  return (
    <div className="rounded-md border border-white/10 bg-white/[0.035] p-8 text-center">
      <Bookmark className="mx-auto h-10 w-10 text-[#edc342]" />
      <h3 className="mt-4 text-2xl font-black">Your watchlist is empty</h3>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-white/54">
        {activeType === 'all' ? 'Add content to your watchlist while browsing eZWay TV.' : `No ${typeLabel(activeType).toLowerCase()} titles are saved yet.`}
      </p>
      <Button asChild className="mt-6 bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
        <a href="/">Explore Content</a>
      </Button>
    </div>
  )
}

function itemHref(item: WatchlistItem) {
  const type = item.entertainment_type ?? item.type ?? item.details?.type
  const slug = item.slug ?? item.details?.slug
  const id = item.entertainment_id ?? item.details?.id ?? item.id

  if (type === 'video') return slug ? `/video-details/${slug}` : `/video-details/${id}`
  if (type === 'tvshow') return `/tvshow-details/${slug ?? id}`
  return `/movie-details/${slug ?? id}`
}

function typeLabel(type: string) {
  if (type === 'tvshow') return 'TV Show'
  if (type === 'movie') return 'Movie'
  if (type === 'video') return 'Video'
  return type || 'Title'
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}
