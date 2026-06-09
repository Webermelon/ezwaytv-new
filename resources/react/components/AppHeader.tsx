import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, Menu, Search, X } from 'lucide-react'

import { BrandLogo } from '@/components/BrandLogo'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import type { ApiEnvelope, LiveTvDashboard, MediaItem, PaginatedData } from '@/modules/home/types'
import { loadVideosPage } from '@/modules/videos/videosApi'

type AppHeaderProps = {
  active?: 'home' | 'on-demand' | 'livetv' | 'videos' | 'castcrew' | 'search' | 'distribution'
}

const navItems = [
  { key: 'home', label: 'Home', href: '/' },
  { key: 'on-demand', label: 'On Demand', href: '/on-demand', dropdown: 'ondemand' },
  { key: 'livetv', label: 'Live TV', href: '/livetv', dropdown: 'livetv' },
  { key: 'videos', label: 'Videos', href: '/videos', dropdown: 'videos' },
  { key: 'distribution', label: 'Distribution', href: '/distribution' },
] as const

export function AppHeader({ active }: AppHeaderProps) {
  const activeKey = active ?? inferActiveKey()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const navQuery = useQuery({
    queryKey: ['header-nav'],
    queryFn: loadHeaderNavData,
    staleTime: 5 * 60_000,
  })
  const navData = navQuery.data ?? { videos: [], liveTv: [], ondemand: [] }

  const dropdowns = useMemo(() => ({
    videos: navData.videos,
    livetv: navData.liveTv,
    ondemand: navData.ondemand,
  }), [navData])

  return (
    <header className="sticky top-0 z-40 border-b border-white/8 bg-[#050505]/94 px-4 backdrop-blur-xl sm:px-8 lg:px-12">
      <div className="mx-auto flex h-16 max-w-[1800px] items-center justify-between gap-4">
        <div className="flex min-w-0 items-center gap-6">
          <BrandLogo imageClassName="max-h-11 max-w-[190px]" textClassName="text-2xl" />
          <nav className="hidden items-center gap-1 text-sm font-semibold text-white/62 md:flex">
            {navItems.map((item) => {
              const items = item.dropdown ? dropdowns[item.dropdown] : []

              return (
                <div key={item.key} className="group relative">
                  <a
                    href={item.href}
                    className={[
                      'inline-flex items-center gap-1.5 rounded-md px-3 py-2 transition',
                      activeKey === item.key
                        ? 'bg-white text-black shadow-sm'
                        : 'hover:bg-white/[0.08] hover:text-white',
                    ].join(' ')}
                  >
                    {item.label}
                    {item.dropdown ? <ChevronDown className="h-3.5 w-3.5" /> : null}
                  </a>
                  {item.dropdown ? (
                    <NavDropdown
                      title={item.label}
                      href={item.href}
                      items={items}
                      kind={item.dropdown}
                    />
                  ) : null}
                </div>
              )
            })}
          </nav>
        </div>

        <div className="flex shrink-0 items-center gap-2">
          <a
            href="/search"
            aria-label="Search"
            className="ez-header-search inline-flex h-10 min-w-[112px] items-center justify-center gap-2 rounded-md border border-white/10 bg-white/[0.08] px-3 text-sm font-bold text-white transition hover:bg-white/[0.14]"
          >
            <Search className="ez-header-search-icon h-5 w-5 shrink-0" />
            <span className="ez-header-search-label">Search</span>
          </a>
          <Button
            asChild
            className="hidden h-9 rounded-md bg-primary px-3 text-white hover:bg-primary/90 lg:inline-flex"
          >
            <a href="https://ezwaynetwork.com/" target="_blank" rel="noreferrer">
              Join Our Family
            </a>
          </Button>
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/[0.08] text-white transition hover:bg-white/[0.14] md:hidden"
            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={mobileMenuOpen}
            onClick={() => setMobileMenuOpen((open) => !open)}
          >
            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>
      {mobileMenuOpen ? (
        <MobileMenu
          activeKey={activeKey}
          dropdowns={dropdowns}
          onNavigate={() => setMobileMenuOpen(false)}
        />
      ) : null}
    </header>
  )
}

function MobileMenu({
  activeKey,
  dropdowns,
  onNavigate,
}: {
  activeKey: AppHeaderProps['active']
  dropdowns: Record<'videos' | 'livetv' | 'ondemand', MediaItem[]>
  onNavigate: () => void
}) {
  return (
    <nav className="mx-auto max-h-[calc(100vh-4rem)] max-w-[1800px] overflow-y-auto border-t border-white/8 py-3 md:hidden">
      <div className="grid gap-2">
        <a
          href="/search"
          onClick={onNavigate}
          className="inline-flex min-h-11 items-center gap-3 rounded-md border border-white/10 bg-white/[0.07] px-3 text-sm font-bold text-white"
        >
          <Search className="h-4 w-4" />
          Search
        </a>
        {navItems.map((item) => {
          const items = item.dropdown ? dropdowns[item.dropdown] : []

          return (
            <div key={item.key} className="rounded-md border border-white/10 bg-white/[0.045]">
              <a
                href={item.href}
                onClick={onNavigate}
                className={[
                  'flex min-h-11 items-center justify-between gap-3 px-3 text-sm font-bold transition',
                  activeKey === item.key ? 'text-white' : 'text-white/72',
                ].join(' ')}
              >
                <span>{item.label}</span>
                {item.dropdown ? <ChevronDown className="h-4 w-4 text-white/44" /> : null}
              </a>
              {item.dropdown ? (
                <div className="border-t border-white/8 px-2 pb-2">
                  <a
                    href={item.href}
                    onClick={onNavigate}
                    className="mt-2 block rounded-sm px-2 py-2 text-xs font-bold uppercase text-primary"
                  >
                    View all {item.label}
                  </a>
                  {items.length > 0 ? (
                    <div className="grid gap-1">
                      {items.slice(0, 8).map((entry) => (
                        <a
                          key={`${item.dropdown}-${entry.id}`}
                          href={navItemHref(entry, item.dropdown)}
                          onClick={onNavigate}
                          className="flex min-h-12 items-center gap-3 rounded-sm px-2 py-2 text-white/74 hover:bg-white/[0.07] hover:text-white"
                        >
                          <span className="h-8 w-12 shrink-0 overflow-hidden rounded bg-white/[0.06]">
                            {navItemImage(entry) ? <img src={navItemImage(entry) ?? ''} alt="" className="h-full w-full object-cover" loading="lazy" /> : null}
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-bold">{entry.details?.name ?? entry.name}</span>
                            <span className="mt-0.5 block truncate text-xs text-white/42">{navItemMeta(entry, item.dropdown)}</span>
                          </span>
                        </a>
                      ))}
                    </div>
                  ) : (
                    <div className="px-2 py-4 text-sm text-white/42">No items loaded.</div>
                  )}
                </div>
              ) : null}
            </div>
          )
        })}
        <a
          href="https://ezwaynetwork.com/"
          target="_blank"
          rel="noreferrer"
          onClick={onNavigate}
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-3 text-sm font-bold text-white"
        >
          Join Our Family
        </a>
      </div>
    </nav>
  )
}

function NavDropdown({
  title,
  href,
  items,
  kind,
}: {
  title: string
  href: string
  items: MediaItem[]
  kind: 'videos' | 'livetv' | 'ondemand'
}) {
  return (
    <div className="invisible absolute left-0 top-full z-50 w-[360px] translate-y-2 pt-3 opacity-0 transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
      <div className="overflow-hidden rounded-md border border-white/10 bg-[#101010] shadow-2xl shadow-black/40">
        <div className="flex items-center justify-between border-b border-white/8 px-3 py-2">
          <span className="text-xs font-black uppercase tracking-wide text-white/42">{title}</span>
          <a href={href} className="text-xs font-bold text-white/62 hover:text-white">View all</a>
        </div>
        <div className="max-h-[420px] overflow-y-auto p-2 [scrollbar-width:thin]">
          {items.length > 0 ? (
            items.map((item) => (
              <a
                key={`${kind}-${item.id}`}
                href={navItemHref(item, kind)}
                className="flex min-h-12 items-center gap-3 rounded-md px-2 py-2 text-white/78 transition hover:bg-white/[0.07] hover:text-white"
              >
                <span className="h-8 w-12 shrink-0 overflow-hidden rounded bg-white/[0.06]">
                  {navItemImage(item) ? <img src={navItemImage(item) ?? ''} alt="" className="h-full w-full object-cover" loading="lazy" /> : null}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-bold">{item.details?.name ?? item.name}</span>
                  <span className="mt-0.5 block truncate text-xs text-white/42">{navItemMeta(item, kind)}</span>
                </span>
              </a>
            ))
          ) : (
            <div className="px-3 py-8 text-center text-sm text-white/46">No items loaded.</div>
          )}
        </div>
      </div>
    </div>
  )
}

async function loadHeaderNavData() {
  const [videos, liveTv, ondemand] = await Promise.allSettled([
    loadVideosPage('', 1, 14),
    api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard'),
    api.get<ApiEnvelope<PaginatedData<MediaItem>>>('/api/v3/ondemand?per_page=14'),
  ])

  return {
    videos: videos.status === 'fulfilled' ? videos.value.items : [],
    liveTv: liveTv.status === 'fulfilled'
      ? (liveTv.value.data?.category_data?.flatMap((category) => category.channel_data ?? []) ?? []).slice(0, 14)
      : [],
    ondemand: ondemand.status === 'fulfilled' ? ondemand.value.data?.data ?? [] : [],
  }
}

function navItemHref(item: MediaItem, kind: 'videos' | 'livetv' | 'ondemand') {
  if (kind === 'videos') {
    return item.slug ? `/video-details/${item.slug}?autoplay=1` : '/videos'
  }

  if (kind === 'livetv') {
    return `/livetv/${item.slug ?? item.details?.slug ?? item.id}`
  }

  return item.username ? `/on-demand/${item.username}` : '/on-demand'
}

function navItemImage(item: MediaItem) {
  return item.poster_tv_image
    ?? item.poster_image
    ?? item.cover_image_url
    ?? item.thumbnail_url
    ?? item.avatar_image_url
    ?? item.profile_image
    ?? item.details?.thumbnail_image
    ?? null
}

function navItemMeta(item: MediaItem, kind: 'videos' | 'livetv' | 'ondemand') {
  if (kind === 'videos') return [item.duration, item.access].filter(Boolean).join(' - ') || 'Video'
  if (kind === 'livetv') return item.details?.category ?? 'Live channel'

  return `${item.videos_count ?? 0} videos`
}

function inferActiveKey(): AppHeaderProps['active'] {
  const path = window.location.pathname
  if (path === '/') return 'home'
  if (path.startsWith('/on-demand')) return 'on-demand'
  if (path.startsWith('/livetv')) return 'livetv'
  if (path.startsWith('/videos')) return 'videos'
  if (path.startsWith('/castcrew')) return 'castcrew'
  if (path.startsWith('/search')) return 'search'
  if (path.startsWith('/distribution')) return 'distribution'

  return undefined
}
