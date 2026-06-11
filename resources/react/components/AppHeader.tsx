import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Camera, ChevronDown, ChevronRight, Film, Home as HomeIcon, Menu, Music2, Play, Radio, Search, Send, Share2, Tv, UsersRound, Video, X } from 'lucide-react'

import { BrandLogo } from '@/components/BrandLogo'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { useSpaNavigate } from '@/lib/spa-router'
import type { ApiEnvelope, DashboardData, LiveTvDashboard, MediaItem, PaginatedData } from '@/modules/home/types'
import { loadVideosPage } from '@/modules/videos/videosApi'

type AppHeaderProps = {
  active?: 'home' | 'on-demand' | 'livetv' | 'videos' | 'castcrew' | 'search' | 'distribution' | 'stream-music' | 'movies' | 'tvshows' | 'ppv'
}

const navItems = [
  { key: 'home', label: 'Home', href: '/' },
  // { key: 'movies', label: 'Movies', href: '/movies' },
  // { key: 'tvshows', label: 'TV Shows', href: '/tv-shows' },
  { key: 'videos', label: 'Videos', href: '/videos', dropdown: 'videos' },
  { key: 'on-demand', label: 'On Demand', href: '/on-demand', dropdown: 'ondemand' },
  { key: 'livetv', label: 'Live TV', href: '/livetv', dropdown: 'livetv' },
  { key: 'distribution', label: 'Distribution', href: '/distribution' },
  { key: 'stream-music', label: 'Stream Your Music', href: '/music' },
] as const

type DropdownKey = 'videos' | 'livetv' | 'ondemand'

const mobileNavItems = [
  { key: 'home', label: 'Home', href: '/', icon: HomeIcon },
  { key: 'movies', label: 'Movies', href: '/movies', icon: Film },
  { key: 'tvshows', label: 'TV Shows', href: '/tv-shows', icon: Tv },
  { key: 'videos', label: 'Videos', href: '/videos', dropdown: 'videos', icon: Video },
  { key: 'on-demand', label: 'On Demand', href: '/on-demand', dropdown: 'ondemand', icon: Film },
  { key: 'livetv', label: 'Live TV', href: '/livetv', dropdown: 'livetv', icon: Radio },
  { key: 'distribution', label: 'Distribution', href: '/distribution', icon: Share2 },
  { key: 'stream-music', label: 'Stream Your Music', href: '/music', icon: Music2 },
] as const

export function AppHeader({ active }: AppHeaderProps) {
  const activeKey = active ?? inferActiveKey()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const navigate = useSpaNavigate()
  const navQuery = useQuery({
    queryKey: ['header-nav'],
    queryFn: loadHeaderNavData,
    staleTime: 5 * 60_000,
  })
  const navData = navQuery.data ?? { videos: [], liveTv: [], ondemand: [], hasMovies: false, hasTvshows: false }

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
            <BrandLogo imageClassName="max-h-11 max-w-[190px]" textClassName="text-2xl" placeholderClassName="h-10 w-[170px]" />
            <nav className="hidden items-center gap-1 text-sm font-semibold text-white/62 md:flex">
              {navItems.map((item) => {
                const items = item.dropdown ? dropdowns[item.dropdown] : []

                return (
                  <div key={item.key} className="group relative">
                    <a
                      href={item.href}
                      className={[
                        'relative inline-flex h-16 items-center gap-1.5 border-b-2 px-3 text-sm transition',
                        activeKey === item.key
                          ? 'border-[#d4a843] text-white'
                          : 'border-transparent text-white/62 hover:border-white/20 hover:text-white',
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
          hasMovies={navData.hasMovies}
          hasTvshows={navData.hasTvshows}
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
  hasMovies,
  hasTvshows,
  navigate,
  onNavigate,
  onClose,
}: {
  activeKey: AppHeaderProps['active']
  dropdowns: Record<DropdownKey, MediaItem[]>
  hasMovies: boolean
  hasTvshows: boolean
  navigate: (to: string, options?: { replace?: boolean }) => void
  onNavigate: () => void
  onClose: () => void
}) {
  const [openSection, setOpenSection] = useState<DropdownKey | null>(null)
  const visibleMobileNavItems = mobileNavItems.filter((item) => {
    if (item.key === 'movies') return hasMovies
    if (item.key === 'tvshows') return hasTvshows

    return true
  })

  return (
    <div className="fixed inset-y-0 left-0 right-0 z-[9999] min-h-screen w-[100dvw] max-w-[100dvw] overflow-hidden bg-black/72 backdrop-blur-sm md:hidden">
      <nav className="flex h-screen min-h-screen w-full max-w-full flex-col overflow-hidden bg-[radial-gradient(circle_at_78%_12%,rgba(212,168,67,0.16),transparent_28%),radial-gradient(circle_at_10%_76%,rgba(255,255,255,0.07),transparent_24%),linear-gradient(135deg,#050807_0%,#091011_48%,#050505_100%)] text-white shadow-2xl shadow-black">
        <div className="flex w-full min-w-0 max-w-full items-center justify-between gap-4 px-5 pb-4 pt-5">
          <BrandLogo imageClassName="max-h-12 max-w-[200px]" textClassName="text-2xl" placeholderClassName="h-10 w-[180px]" />
          <button
            type="button"
            onClick={onClose}
            aria-label="Close menu"
            className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/8 bg-white/[0.07] text-white shadow-xl shadow-black/25 transition hover:bg-white/[0.12]"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        <div className="min-h-0 w-full min-w-0 max-w-full flex-1 overflow-y-auto overflow-x-hidden px-5 pb-6 pt-1">
          <form
            className="mb-4 grid w-full min-w-0 max-w-full grid-cols-[minmax(0,1fr)_56px] gap-3"
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
            <label className="flex h-12 min-w-0 items-center gap-3 rounded-xl border border-white/12 bg-white/[0.055] px-4 shadow-inner shadow-white/[0.03] focus-within:border-[#d4a843]/70">
              <Search className="h-5 w-5 shrink-0 text-white" />
              <input
                name="q"
                placeholder="Search movies, shows..."
                className="h-full min-w-0 flex-1 bg-transparent text-sm font-semibold text-white outline-none placeholder:text-white/48"
              />
            </label>
            <button
              type="submit"
              aria-label="Search"
              className="flex h-12 items-center justify-center rounded-xl bg-[#edc342] text-black shadow-xl shadow-[#d4a843]/20 transition hover:bg-[#f4ce4d]"
            >
              <Search className="h-5 w-5" />
            </button>
          </form>

          <div className="grid w-full min-w-0 max-w-full gap-1 overflow-hidden">
            {visibleMobileNavItems.map((item) => {
              const items = item.dropdown ? dropdowns[item.dropdown] : []
              const isOpen = item.dropdown ? openSection === item.dropdown : false
              const isActive = activeKey === item.key
              const Icon = item.icon

              return (
                <div key={item.key} className="w-full min-w-0 max-w-full overflow-hidden">
                  {item.dropdown ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setOpenSection((current) => current === item.dropdown ? null : item.dropdown)}
                        className={[
                          'flex min-h-12 w-full min-w-0 max-w-full items-center justify-between gap-3 overflow-hidden rounded-xl border px-4 text-left text-sm font-black transition',
                          isActive
                            ? 'border-[#d4a843]/45 bg-[#d4a843]/10 text-[#edc342] shadow-[0_0_24px_rgba(212,168,67,0.12)]'
                            : 'border-transparent text-white hover:border-white/10 hover:bg-white/[0.045]',
                        ].join(' ')}
                        aria-expanded={isOpen}
                      >
                        <span className="flex min-w-0 items-center gap-3">
                          <Icon className="h-5 w-5 shrink-0" />
                          <span className="min-w-0 truncate">{item.label}</span>
                        </span>
                        <ChevronDown className={['h-5 w-5 shrink-0 transition', isOpen ? 'rotate-180' : ''].join(' ')} />
                      </button>
                      {isOpen ? (
                        <div className="w-full min-w-0 max-w-full overflow-hidden pb-2 pl-12 pr-2 pt-1">
                          <a
                            href={item.href}
                            onClick={onNavigate}
                            className="block w-full min-w-0 truncate rounded-md py-1.5 text-xs font-black uppercase text-[#edc342]"
                          >
                            View all {item.label}
                          </a>
                          {items.length > 0 ? (
                            <div className="grid w-full min-w-0 max-w-full gap-1 overflow-hidden">
                              {items.slice(0, 5).map((entry) => (
                                <a
                                  key={`${item.dropdown}-${entry.id}`}
                                  href={navItemHref(entry, item.dropdown)}
                                  onClick={onNavigate}
                                  className="flex min-h-10 w-full min-w-0 max-w-full items-center gap-2 overflow-hidden rounded-lg py-1.5 text-white/76"
                                >
                                  <span className="h-7 w-10 shrink-0 overflow-hidden rounded bg-white/[0.06]">
                                    {navItemImage(entry) ? <img src={navItemImage(entry) ?? ''} alt="" className="h-full w-full object-cover" loading="lazy" /> : null}
                                  </span>
                                  <span className="min-w-0 flex-1 overflow-hidden">
                                    <span className="block truncate text-xs font-semibold">{entry.details?.name ?? entry.name}</span>
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
                        'flex min-h-12 w-full min-w-0 max-w-full items-center justify-between overflow-hidden rounded-xl border px-4 text-sm font-black transition',
                        isActive
                          ? 'border-[#d4a843]/45 bg-[#d4a843]/10 text-[#edc342] shadow-[0_0_24px_rgba(212,168,67,0.12)]'
                          : 'border-transparent text-white hover:border-white/10 hover:bg-white/[0.045]',
                      ].join(' ')}
                    >
                      <span className="flex min-w-0 items-center gap-3">
                        <Icon className="h-5 w-5 shrink-0" />
                        <span className="min-w-0 truncate">{item.label}</span>
                      </span>
                      {item.key === 'home' ? null : <ChevronRight className="h-5 w-5 shrink-0" />}
                    </a>
                  )}
                </div>
              )
            })}
          </div>

          <a
            href="https://ezwaynetwork.com/"
            target="_blank"
            rel="noreferrer"
            onClick={onNavigate}
            className="mt-5 grid min-h-20 grid-cols-[48px_minmax(0,1fr)_40px] items-center gap-3 rounded-xl border border-[#d4a843]/24 bg-[#d4a843]/8 px-4 py-3 shadow-[0_0_28px_rgba(212,168,67,0.10)]"
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-full border border-[#d4a843]/35 bg-[#d4a843]/15 text-[#edc342] shadow-inner shadow-[#d4a843]/20">
              <UsersRound className="h-5 w-5" />
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-black uppercase text-white">Join Our Family</span>
              <span className="mt-1 block text-xs font-semibold leading-4 text-white/54">Become part of the eZWay community.</span>
            </span>
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[#edc342] text-black">
              <ChevronRight className="h-5 w-5" />
            </span>
          </a>

          <div className="mt-5 flex items-center gap-3">
            {[
              { label: 'Facebook', icon: Share2, href: 'https://www.facebook.com/ezwaybroadcasting' },
              { label: 'Instagram', icon: Camera, href: 'https://www.instagram.com/ezwaytv' },
              { label: 'X', icon: Send, href: 'https://twitter.com/ezwaytv' },
              { label: 'YouTube', icon: Play, href: 'https://www.youtube.com/@ezwaytv' },
            ].map(({ label, icon: Icon, href }) => (
              <a
                key={label}
                href={href}
                target="_blank"
                rel="noreferrer"
                aria-label={label}
                title={label}
                className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.035] text-[#edc342] transition hover:border-[#d4a843]/50 hover:bg-[#d4a843]/12"
              >
                <Icon className="h-4 w-4" />
              </a>
            ))}
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
  const [videos, liveTv, ondemand, dashboard] = await Promise.allSettled([
    loadVideosPage('', 1, 14),
    api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard'),
    api.get<ApiEnvelope<PaginatedData<MediaItem>>>('/api/v3/ondemand?per_page=14'),
    api.get<ApiEnvelope<DashboardData>>('/api/v3/dashboard-detail'),
  ])
  const dashboardData = dashboard.status === 'fulfilled' ? dashboard.value.data : undefined
  const movieCount = [
    dashboardData?.latest_movie?.data,
    dashboardData?.popular_movie?.data,
    dashboardData?.free_movie?.data,
  ].reduce((total, items) => total + (Array.isArray(items) ? items.length : 0), 0)
  const tvshowCount = Array.isArray(dashboardData?.popular_tvshow?.data) ? dashboardData.popular_tvshow.data.length : 0

  return {
    videos: videos.status === 'fulfilled' ? videos.value.items : [],
    liveTv: liveTv.status === 'fulfilled'
      ? (liveTv.value.data?.category_data?.flatMap((category) => category.channel_data ?? []) ?? []).slice(0, 14)
      : [],
    ondemand: ondemand.status === 'fulfilled' ? ondemand.value.data?.data ?? [] : [],
    hasMovies: movieCount > 0,
    hasTvshows: tvshowCount > 0,
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
  if (path.startsWith('/music') || path.startsWith('/stream-your-music') || path.startsWith('/upload-your-videoes')) return 'stream-music'

  return undefined
}
