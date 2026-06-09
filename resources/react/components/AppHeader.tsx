import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, Menu, Search, X } from 'lucide-react'

import { BrandLogo } from '@/components/BrandLogo'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { useSpaNavigate } from '@/lib/spa-router'
import type { ApiEnvelope, LiveTvDashboard, MediaItem, PaginatedData } from '@/modules/home/types'
import { loadVideosPage } from '@/modules/videos/videosApi'

type AppHeaderProps = {
  active?: 'home' | 'on-demand' | 'livetv' | 'videos' | 'castcrew' | 'search' | 'distribution' | 'movies' | 'tvshows' | 'ppv'
}

const navItems = [
  { key: 'home', label: 'Home', href: '/' },
  { key: 'movies', label: 'Movies', href: '/movies' },
  { key: 'tvshows', label: 'TV Shows', href: '/tv-shows' },
  { key: 'videos', label: 'Videos', href: '/videos', dropdown: 'videos' },
  { key: 'on-demand', label: 'On Demand', href: '/on-demand', dropdown: 'ondemand' },
  { key: 'livetv', label: 'Live TV', href: '/livetv', dropdown: 'livetv' },
  { key: 'distribution', label: 'Distribution', href: '/distribution' },
  { key: 'ppv', label: 'eZWay PPV', href: '/pay-per-view' },
] as const

type DropdownKey = 'videos' | 'livetv' | 'ondemand'

export function AppHeader({ active }: AppHeaderProps) {
  const activeKey = active ?? inferActiveKey()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const navigate = useSpaNavigate()
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

  useEffect(() => {
    if (!mobileMenuOpen) return

    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = originalOverflow
    }
  }, [mobileMenuOpen])

  return (
    <>
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
              className="ez-header-search-desktop hidden h-10 min-w-[112px] items-center justify-center gap-2 rounded-md border border-white/10 bg-white/[0.08] px-3 text-sm font-bold text-white transition hover:bg-white/[0.14] md:inline-flex"
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
      </header>
      {mobileMenuOpen ? (
        <MobileMenu
          activeKey={activeKey}
          dropdowns={dropdowns}
          navigate={navigate}
          onNavigate={() => setMobileMenuOpen(false)}
          onClose={() => setMobileMenuOpen(false)}
        />
      ) : null}
    </>
  )
}

function MobileMenu({
  activeKey,
  dropdowns,
  navigate,
  onNavigate,
  onClose,
}: {
  activeKey: AppHeaderProps['active']
  dropdowns: Record<DropdownKey, MediaItem[]>
  navigate: (to: string, options?: { replace?: boolean }) => void
  onNavigate: () => void
  onClose: () => void
}) {
  const [openSection, setOpenSection] = useState<DropdownKey | null>(null)

  return (
    <div className="fixed inset-y-0 left-0 right-0 z-[9999] min-h-screen w-[100dvw] max-w-[100dvw] overflow-hidden bg-[#020b0d] md:hidden">
      <nav className="flex h-screen min-h-screen w-full max-w-full flex-col overflow-hidden bg-[#020b0d]">
        <div className="flex min-h-24 w-full min-w-0 max-w-full items-center justify-between gap-4 bg-[radial-gradient(circle_at_70%_50%,rgba(255,255,255,0.08),transparent_28%),linear-gradient(90deg,#050505,#130d08)] px-4">
          <BrandLogo imageClassName="max-h-14 max-w-[220px]" textClassName="text-3xl" />
          <button
            type="button"
            onClick={onClose}
            aria-label="Close menu"
            className="flex h-11 w-11 shrink-0 items-center justify-center text-white"
          >
            <X className="h-8 w-8" />
          </button>
        </div>

        <div className="min-h-0 w-full min-w-0 max-w-full flex-1 overflow-y-auto overflow-x-hidden bg-[#020b0d] px-4 pb-7 pt-4">
          <form
            className="mb-4 grid w-full min-w-0 max-w-full grid-cols-[minmax(0,1fr)_64px] gap-2 border-b border-[#edc342] pb-4"
            action="/search"
            method="get"
            onSubmit={(event) => {
              event.preventDefault()
              const formData = new FormData(event.currentTarget)
              const term = String(formData.get('q') ?? '').trim()
              navigate(term ? `/search?q=${encodeURIComponent(term)}` : '/search')
              onNavigate()
            }}
          >
            <input
              name="q"
              placeholder="Search..."
              className="h-12 min-w-0 rounded-md border border-white/15 bg-white/[0.045] px-4 text-base text-white outline-none placeholder:text-white/38 focus:border-[#edc342]"
            />
            <button
              type="submit"
              aria-label="Search"
              className="flex h-12 items-center justify-center rounded-md bg-[#edc342] text-black"
            >
              <Search className="h-5 w-5" />
            </button>
          </form>

          <div className="grid w-full min-w-0 max-w-full gap-0 overflow-hidden">
            {navItems.map((item) => {
              const items = item.dropdown ? dropdowns[item.dropdown] : []
              const isOpen = item.dropdown ? openSection === item.dropdown : false

              return (
                <div key={item.key} className="w-full min-w-0 max-w-full overflow-hidden">
                  {item.dropdown ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setOpenSection((current) => current === item.dropdown ? null : item.dropdown)}
                        className={[
                          'flex min-h-10 w-full min-w-0 max-w-full items-center justify-between gap-3 overflow-hidden py-2 text-left text-base font-bold',
                          activeKey === item.key ? 'text-[#edc342]' : 'text-white',
                        ].join(' ')}
                        aria-expanded={isOpen}
                      >
                        <span className="min-w-0 truncate">{item.label}</span>
                        <ChevronDown className={['h-5 w-5 shrink-0 transition', isOpen ? 'rotate-180' : ''].join(' ')} />
                      </button>
                      {isOpen ? (
                        <div className="w-full min-w-0 max-w-full overflow-hidden pb-3">
                          <a
                            href={item.href}
                            onClick={onNavigate}
                            className="block w-full min-w-0 truncate rounded-sm py-2 text-sm font-bold text-[#edc342]"
                          >
                            View all {item.label}
                          </a>
                          {items.length > 0 ? (
                            <div className="grid w-full min-w-0 max-w-full gap-1 overflow-hidden">
                              {items.slice(0, 8).map((entry) => (
                                <a
                                  key={`${item.dropdown}-${entry.id}`}
                                  href={navItemHref(entry, item.dropdown)}
                                  onClick={onNavigate}
                                  className="flex min-h-11 w-full min-w-0 max-w-full items-center gap-3 overflow-hidden rounded-sm py-2 text-white/76"
                                >
                                  <span className="h-8 w-12 shrink-0 overflow-hidden rounded bg-white/[0.06]">
                                    {navItemImage(entry) ? <img src={navItemImage(entry) ?? ''} alt="" className="h-full w-full object-cover" loading="lazy" /> : null}
                                  </span>
                                  <span className="min-w-0 flex-1 overflow-hidden">
                                    <span className="block truncate text-sm font-semibold">{entry.details?.name ?? entry.name}</span>
                                    <span className="mt-0.5 block truncate text-xs text-white/42">{navItemMeta(entry, item.dropdown)}</span>
                                  </span>
                                </a>
                              ))}
                            </div>
                          ) : (
                            <div className="py-3 text-sm text-white/42">No items loaded.</div>
                          )}
                        </div>
                      ) : null}
                    </>
                  ) : (
                    <a
                      href={item.href}
                      onClick={onNavigate}
                      className={[
                        'flex min-h-10 w-full min-w-0 max-w-full items-center overflow-hidden py-2 text-base font-bold',
                        activeKey === item.key ? 'text-[#edc342]' : 'text-white',
                      ].join(' ')}
                    >
                      <span className="min-w-0 truncate">{item.label}</span>
                    </a>
                  )}
                </div>
              )
            })}
            <a
              href="https://ezwaynetwork.com/"
              target="_blank"
              rel="noreferrer"
              onClick={onNavigate}
              className="mt-2 flex min-h-10 w-full min-w-0 max-w-full items-center overflow-hidden py-2 text-base font-black uppercase text-white"
            >
              <span className="min-w-0 truncate">Join Our Family</span>
            </a>
          </div>
        </div>
      </nav>
    </div>
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
  if (path.startsWith('/movies') || path.startsWith('/movie-details')) return 'movies'
  if (path.startsWith('/tv-shows') || path.startsWith('/tvshow-details')) return 'tvshows'
  if (path.startsWith('/pay-per-view')) return 'ppv'
  if (path.startsWith('/castcrew')) return 'castcrew'
  if (path.startsWith('/search')) return 'search'
  if (path.startsWith('/distribution')) return 'distribution'

  return undefined
}
