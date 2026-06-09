import { useEffect, useMemo, useState } from 'react'
import { Search, UsersRound } from 'lucide-react'

import { MediaThumbnail } from '@/components/MediaThumbnail'
import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { loadCastCrewList, type CastCrewItem } from './castCrewApi'

const filters = ['all', 'actor', 'director'] as const

export function CastCrewPage() {
  const [items, setItems] = useState<CastCrewItem[]>([])
  const [query, setQuery] = useState('')
  const [type, setType] = useState<(typeof filters)[number]>(getTypeFromPath())
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let mounted = true

    setLoading(true)
    loadCastCrewList({ search: query, type, perPage: 80 })
      .then((data) => {
        if (mounted) setItems(data)
      })
      .finally(() => {
        if (mounted) setLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [query, type])

  const featured = useMemo(() => items[0], [items])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="castcrew" />

      <section className="relative overflow-hidden px-4 pb-10 pt-24 sm:px-8 lg:px-12">
        {featured?.profile_image ? <img src={featured.profile_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-18 blur-sm" /> : null}
        <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.92)_46%,#050505_100%)]" />
        <div className="relative z-10 max-w-4xl">
          <Badge className="rounded-sm bg-primary text-white">
            <UsersRound className="mr-1 h-3.5 w-3.5" />
            Personalities
          </Badge>
          <h1 className="mt-4 text-4xl font-black leading-none sm:text-5xl">Popular Personalities</h1>
          <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">
            Browse the existing cast and crew directory from the current Laravel API.
          </p>
        </div>
      </section>

      <section className="px-4 pb-16 sm:px-8 lg:px-12">
        <div className="mb-6 grid gap-3 rounded-md border border-white/10 bg-white/[0.045] p-3 lg:grid-cols-[1fr_auto]">
          <div className="flex min-h-11 items-center gap-2 rounded-md bg-black/38 px-3">
            <Search className="h-4 w-4 text-white/44" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search personalities"
              className="h-10 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
            />
          </div>
          <div className="flex flex-wrap gap-2">
            {filters.map((filter) => (
              <button
                key={filter}
                type="button"
                onClick={() => setType(filter)}
                className={[
                  'h-9 rounded-md px-3 text-xs font-bold capitalize transition',
                  type === filter ? 'bg-primary text-white' : 'bg-white/8 text-white/62 hover:bg-white/14 hover:text-white',
                ].join(' ')}
              >
                {filter}
              </button>
            ))}
          </div>
        </div>

        <div className="mb-3 flex items-center justify-between gap-4">
          <h2 className="text-2xl font-bold">{type === 'all' ? 'All Personalities' : `${type}s`}</h2>
          <span className="text-sm text-white/50">{items.length} shown</span>
        </div>

        {loading ? (
          <CastCrewSkeleton />
        ) : items.length > 0 ? (
          <div className="grid gap-x-4 gap-y-8 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-7 2xl:grid-cols-10">
            {items.map((item) => (
              <CastCrewCard key={item.id} item={item} />
            ))}
          </div>
        ) : null}
      </section>
    </main>
  )
}

function CastCrewCard({ item }: { item: CastCrewItem }) {
  return (
    <a href={castCrewDetailHref(item)} className="group block min-w-0 text-center">
      <div className="mx-auto aspect-square overflow-hidden rounded-full border border-white/10 bg-white/[0.06] shadow-lg transition group-hover:scale-[1.04] group-hover:border-primary/70">
        <MediaThumbnail src={item.profile_image} alt={item.name} className="aspect-square rounded-full" />
      </div>
      <h3 className="mt-3 line-clamp-2 text-sm font-bold leading-snug text-white">{item.name}</h3>
      {item.designation ?? item.type ? <p className="mt-1 line-clamp-1 text-xs capitalize text-white/50">{item.designation ?? item.type}</p> : null}
    </a>
  )
}

function CastCrewSkeleton() {
  return (
    <div className="grid gap-x-4 gap-y-8 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-7 2xl:grid-cols-10">
      {Array.from({ length: 20 }).map((_, index) => (
        <div key={index}>
          <div className="mx-auto aspect-square animate-pulse rounded-full border border-white/10 bg-white/[0.045]" />
          <div className="mx-auto mt-3 h-4 w-24 animate-pulse rounded bg-white/[0.07]" />
        </div>
      ))}
    </div>
  )
}

function getTypeFromPath(): (typeof filters)[number] {
  const match = window.location.pathname.match(/^\/castcrew-list\/([^/]+)/)
  const value = match?.[1]

  return value === 'actor' || value === 'director' ? value : 'all'
}

function castCrewDetailHref(item: CastCrewItem) {
  const params = new URLSearchParams()
  if (item.type) {
    params.set('type', item.type)
  }

  const query = params.toString()

  return `/castcrew-detail/${item.id}${query ? `?${query}` : ''}`
}
