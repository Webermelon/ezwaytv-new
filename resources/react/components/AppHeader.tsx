import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Camera, ChevronDown, ChevronRight, Film, Home as HomeIcon, LogOut, Menu, Music2, Play, Radio, Search, Send, Settings, Share2, Tv, UsersRound, Video, X } from 'lucide-react'

import { BrandLogo } from '@/components/BrandLogo'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { useSpaNavigate } from '@/lib/spa-router'
import type { ApiEnvelope, DashboardData, LiveTvDashboard, MediaItem, PaginatedData } from '@/modules/home/types'
import { loadVideosPage } from '@/modules/videos/videosApi'

type AppHeaderProps = {
  active?: 'home' | 'on-demand' | 'livetv' | 'videos' | 'castcrew' | 'search' | 'distribution' | 'stream-music' | 'movies' | 'tvshows' | 'ppv'
}

type AuthUser = {
  id: number
  name?: string | null
  email?: string | null
  avatar?: string | null
  user_type?: string | null
  roles?: string[]
  is_admin?: boolean
  is_subscribe?: boolean
  current_profile?: {
    id?: number | null
    name?: string | null
    is_child_profile?: boolean
  } | null
  dashboard_url?: string | null
  logout_url?: string | null
}

type HeaderNavData = {
  videos: MediaItem[]
  liveTv: MediaItem[]
  ondemand: MediaItem[]
  hasMovies: boolean
  hasTvshows: boolean
  visibleMenuKeys: string[] | null
}

type NavigationMenuResponse = {
  burger_menu?: Array<{ key: string }>
}

declare global {
  interface Window {
    ezwayAuth?: AuthUser | null
    isAuthenticated?: boolean
    ezwayVisibleMenuKeys?: string[]
  }
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
  const [subscriberFormOpen, setSubscriberFormOpen] = useState(false)
  const navigate = useSpaNavigate()
  const authUser = getAuthUser()
  const navQuery = useQuery({
    queryKey: ['header-nav'],
    queryFn: loadHeaderNavData,
    staleTime: 5 * 60_000,
  })
  const navData: HeaderNavData = navQuery.data ?? {
    videos: [],
    liveTv: [],
    ondemand: [],
    hasMovies: false,
    hasTvshows: false,
    visibleMenuKeys: getInitialVisibleMenuKeys(),
  }
  const visibleNavItems = navItems.filter((item) => isMenuVisible(navData.visibleMenuKeys, item.key))

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

  useEffect(() => {
    if (!subscriberFormOpen) return

    const originalOverflow = document.body.style.overflow
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setSubscriberFormOpen(false)
      }
    }

    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', handleKeyDown)

    return () => {
      document.body.style.overflow = originalOverflow
      window.removeEventListener('keydown', handleKeyDown)
    }
  }, [subscriberFormOpen])

  return (
    <>
      <header className="sticky top-0 z-40 border-b border-white/8 bg-[#050505]/94 px-4 backdrop-blur-xl sm:px-8 lg:px-12">
        <div className="mx-auto flex h-16 max-w-[1800px] items-center justify-between gap-4">
          <div className="flex min-w-0 items-center gap-6">
            <BrandLogo imageClassName="max-h-11 max-w-[190px]" textClassName="text-2xl" placeholderClassName="h-10 w-[170px]" />
            <nav className="hidden items-center gap-1 text-sm font-semibold text-white/62 md:flex">
              {visibleNavItems.map((item) => {
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
              <button
                type="button"
                onClick={() => setSubscriberFormOpen(true)}
                className="relative inline-flex h-16 items-center gap-1.5 border-b-2 border-transparent px-3 text-sm text-white/62 transition hover:border-white/20 hover:text-white"
              >
                TV Subscriber
              </button>
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
              type="button"
              onClick={() => setSubscriberFormOpen(true)}
              className="hidden h-9 rounded-md bg-primary px-3 text-white hover:bg-primary/90 lg:inline-flex"
            >
              Subscribe
            </Button>
            {authUser ? <ProfileMenu user={authUser} /> : null}
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
          visibleMenuKeys={navData.visibleMenuKeys}
          authUser={authUser}
          navigate={navigate}
          onOpenSubscriberForm={() => {
            setMobileMenuOpen(false)
            setSubscriberFormOpen(true)
          }}
          onNavigate={() => setMobileMenuOpen(false)}
          onClose={() => setMobileMenuOpen(false)}
        />
      ) : null}
      {subscriberFormOpen ? <SubscriberFormModal onClose={() => setSubscriberFormOpen(false)} /> : null}
    </>
  )
}

function MobileMenu({
  activeKey,
  dropdowns,
  hasMovies,
  hasTvshows,
  visibleMenuKeys,
  authUser,
  navigate,
  onOpenSubscriberForm,
  onNavigate,
  onClose,
}: {
  activeKey: AppHeaderProps['active']
  dropdowns: Record<DropdownKey, MediaItem[]>
  hasMovies: boolean
  hasTvshows: boolean
  visibleMenuKeys: string[] | null
  authUser: AuthUser | null
  navigate: (to: string, options?: { replace?: boolean }) => void
  onOpenSubscriberForm: () => void
  onNavigate: () => void
  onClose: () => void
}) {
  const [openSection, setOpenSection] = useState<DropdownKey | null>(null)
  const visibleMobileNavItems = mobileNavItems.filter((item) => {
    if (!isMenuVisible(visibleMenuKeys, item.key)) return false
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
            <button
              type="button"
              onClick={onOpenSubscriberForm}
              className="flex min-h-12 w-full min-w-0 max-w-full items-center justify-between overflow-hidden rounded-xl border border-transparent px-4 text-left text-sm font-black text-white transition hover:border-white/10 hover:bg-white/[0.045]"
            >
              <span className="flex min-w-0 items-center gap-3">
                <UsersRound className="h-5 w-5 shrink-0" />
                <span className="min-w-0 truncate">TV Subscriber</span>
              </span>
              <ChevronRight className="h-5 w-5 shrink-0" />
            </button>
          </div>

          {authUser ? (
            <MobileProfileMenu user={authUser} onNavigate={onNavigate} />
          ) : (
            <button
              type="button"
              onClick={onOpenSubscriberForm}
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
            </button>
          )}

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

function SubscriberFormModal({ onClose }: { onClose: () => void }) {
  const [fullName, setFullName] = useState('')
  const [email, setEmail] = useState('')
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle')
  const [message, setMessage] = useState('')

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setStatus('submitting')
    setMessage('')

    try {
      const response = await api.post<{ success?: boolean; message?: string }>('/tv-subscriber-form', {
        full_name: fullName.trim(),
        email: email.trim(),
      })

      if (!response?.success) {
        throw new Error(response?.message || 'Subscriber form could not be submitted right now.')
      }

      setStatus('success')
      setMessage(response.message || 'Thank you for subscribing to eZWay TV.')
      setFullName('')
      setEmail('')
    } catch (error) {
      setStatus('error')
      setMessage(error instanceof Error ? error.message : 'Subscriber form could not be submitted right now.')
    }
  }

  return (
    <div
      className="fixed inset-0 z-[10000] flex items-center justify-center bg-black/76 px-3 py-5 backdrop-blur-md sm:px-5"
      role="dialog"
      aria-modal="true"
      aria-labelledby="subscriber-form-title"
    >
      <button
        type="button"
        className="absolute inset-0 cursor-default"
        aria-label="Close subscriber form"
        onClick={onClose}
      />
      <section className="relative flex max-h-[92dvh] w-full max-w-[560px] flex-col overflow-hidden rounded-lg border border-[#d4a843]/28 bg-[radial-gradient(circle_at_82%_0%,rgba(212,168,67,0.22),transparent_34%),linear-gradient(180deg,#111_0%,#050505_100%)] shadow-2xl shadow-black">
        <div className="flex min-h-14 items-center justify-between gap-3 border-b border-white/10 bg-white/[0.035] px-4 py-3 sm:px-5">
          <BrandLogo imageClassName="max-h-10 max-w-[170px]" textClassName="text-xl" placeholderClassName="h-9 w-[160px]" />
          <button
            type="button"
            onClick={onClose}
            aria-label="Close subscriber form"
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-white/10 bg-white/[0.08] text-white transition hover:bg-white/[0.14]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <form onSubmit={handleSubmit} className="grid gap-5 px-5 py-6 sm:px-7 sm:py-7">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">Subscribe eZWay TV</p>
            <h2 id="subscriber-form-title" className="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">
              Stay connected with eZWay TV.
            </h2>
            <p className="mt-3 text-sm leading-6 text-white/62">
              Get updates, channel news, and subscriber-only announcements from eZWay TV.
            </p>
          </div>

          <div className="grid gap-4">
            <label className="grid gap-2">
              <span className="text-xs font-black uppercase tracking-wide text-white/54">Full Name</span>
              <input
                value={fullName}
                onChange={(event) => setFullName(event.target.value)}
                required
                maxLength={255}
                autoComplete="name"
                className="h-12 rounded-md border border-white/12 bg-white/[0.075] px-4 text-sm font-semibold text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70 focus:bg-white/[0.10]"
                placeholder="Your name"
              />
            </label>

            <label className="grid gap-2">
              <span className="text-xs font-black uppercase tracking-wide text-white/54">Email</span>
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
                maxLength={255}
                autoComplete="email"
                className="h-12 rounded-md border border-white/12 bg-white/[0.075] px-4 text-sm font-semibold text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70 focus:bg-white/[0.10]"
                placeholder="you@example.com"
              />
            </label>
          </div>

          {message ? (
            <div
              className={[
                'rounded-md border px-4 py-3 text-sm font-semibold',
                status === 'success'
                  ? 'border-emerald-400/24 bg-emerald-500/12 text-emerald-100'
                  : 'border-red-400/24 bg-red-500/12 text-red-100',
              ].join(' ')}
            >
              {message}
            </div>
          ) : null}

          <Button
            type="submit"
            disabled={status === 'submitting'}
            className="h-12 w-full rounded-md bg-primary text-sm font-black text-black hover:bg-[#f3c84b] disabled:cursor-not-allowed disabled:opacity-70"
          >
            {status === 'submitting' ? 'Subscribing...' : 'Subscribe'}
          </Button>
        </form>
      </section>
    </div>
  )
}

function ProfileMenu({ user }: { user: AuthUser }) {
  const menuItems = profileMenuItems(user)

  return (
    <div className="group relative hidden lg:block">
      <button
        type="button"
        className="flex h-10 items-center gap-2 rounded-md border border-white/10 bg-white/[0.08] pl-2 pr-3 text-left text-sm font-bold text-white transition hover:bg-white/[0.14]"
      >
        <UserAvatar user={user} sizeClassName="h-7 w-7" />
        <span className="max-w-[120px] truncate">{displayUserName(user)}</span>
        <ChevronDown className="h-4 w-4 text-white/54 transition group-hover:rotate-180" />
      </button>

      <div className="invisible absolute right-0 top-full z-50 w-[320px] translate-y-2 pt-3 opacity-0 transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
        <div className="overflow-hidden rounded-md border border-white/10 bg-[#101010] shadow-2xl shadow-black/45">
          <div className="border-b border-white/8 bg-white/[0.035] p-4">
            <div className="flex items-center gap-3">
              <UserAvatar user={user} sizeClassName="h-12 w-12" />
              <div className="min-w-0">
                <div className="truncate text-sm font-black text-white">{displayUserName(user)}</div>
                <div className="mt-1 truncate text-xs font-semibold text-white/48">{user.email ?? 'Signed in'}</div>
                <div className="mt-2 inline-flex rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-[#edc342]">
                  {user.is_admin ? 'Admin' : user.is_subscribe ? 'Subscriber' : 'Member'}
                </div>
              </div>
            </div>
          </div>

          <div className="grid gap-1 p-2">
            {menuItems.map(({ label, href, icon: Icon }) => (
              <a
                key={label}
                href={href}
                className="flex min-h-11 items-center justify-between gap-3 rounded-md px-3 py-2 text-sm font-bold text-white/76 transition hover:bg-white/[0.07] hover:text-white"
              >
                <span className="flex min-w-0 items-center gap-3">
                  <Icon className="h-4 w-4 shrink-0 text-[#d4a843]" />
                  <span className="truncate">{label}</span>
                </span>
                <ChevronRight className="h-4 w-4 shrink-0 text-white/28" />
              </a>
            ))}
            <button
              type="button"
              onClick={() => logoutUser(user)}
              className="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm font-bold text-red-100 transition hover:bg-red-500/12"
            >
              <span className="flex min-w-0 items-center gap-3">
                <LogOut className="h-4 w-4 shrink-0 text-red-300" />
                <span className="truncate">Logout</span>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function MobileProfileMenu({ user, onNavigate }: { user: AuthUser; onNavigate: () => void }) {
  const menuItems = profileMenuItems(user)

  return (
    <div className="mt-5 overflow-hidden rounded-xl border border-[#d4a843]/24 bg-[#d4a843]/8 shadow-[0_0_28px_rgba(212,168,67,0.10)]">
      <div className="grid min-h-20 grid-cols-[48px_minmax(0,1fr)] items-center gap-3 px-4 py-3">
        <UserAvatar user={user} sizeClassName="h-11 w-11" />
        <span className="min-w-0">
          <span className="block truncate text-sm font-black uppercase text-white">{displayUserName(user)}</span>
          <span className="mt-1 block truncate text-xs font-semibold leading-4 text-white/54">{user.email ?? (user.is_admin ? 'Admin account' : 'Member account')}</span>
        </span>
      </div>
      <div className="grid border-t border-white/8 p-2">
        {menuItems.map(({ label, href, icon: Icon }) => (
          <a
            key={label}
            href={href}
            onClick={onNavigate}
            className="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-black text-white/78 transition hover:bg-white/[0.07] hover:text-white"
          >
            <span className="flex min-w-0 items-center gap-3">
              <Icon className="h-4 w-4 shrink-0 text-[#edc342]" />
              <span className="truncate">{label}</span>
            </span>
            <ChevronRight className="h-4 w-4 shrink-0 text-white/32" />
          </a>
        ))}
        <button
          type="button"
          onClick={() => logoutUser(user)}
          className="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-black text-red-100 transition hover:bg-red-500/12"
        >
          <LogOut className="h-4 w-4 shrink-0 text-red-300" />
          Logout
        </button>
      </div>
    </div>
  )
}

function UserAvatar({ user, sizeClassName }: { user: AuthUser; sizeClassName: string }) {
  const initials = displayUserName(user)
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  return (
    <span className={`${sizeClassName} flex shrink-0 overflow-hidden rounded-full border border-[#d4a843]/35 bg-[#d4a843]/15 text-[#edc342]`}>
      {user.avatar ? (
        <img src={user.avatar} alt="" className="h-full w-full object-cover" loading="lazy" />
      ) : (
        <span className="flex h-full w-full items-center justify-center text-xs font-black">{initials || 'EZ'}</span>
      )}
    </span>
  )
}

function profileMenuItems(user: AuthUser) {
  if (user.is_admin) {
    return [
      { label: 'Admin Dashboard', href: user.dashboard_url || '/app/dashboard', icon: HomeIcon },
      { label: 'My Profile', href: '/app/my-profile', icon: Settings },
      { label: 'View Site', href: '/', icon: Tv },
    ]
  }

  return [
    { label: 'My Dashboard', href: user.dashboard_url || '/account-setting', icon: HomeIcon },
    { label: 'Account Settings', href: '/account-setting', icon: Settings },
    { label: 'Watchlist', href: '/watch-list', icon: Film },
    { label: 'Subscription', href: '/subscription-plan', icon: Radio },
    { label: 'Payment History', href: '/payment-history', icon: Share2 },
    { label: 'Manage Profiles', href: '/manage-profile', icon: UsersRound },
  ]
}

function displayUserName(user: AuthUser) {
  return user.current_profile?.name || user.name || user.email || 'My Account'
}

function getAuthUser() {
  if (typeof window === 'undefined') return null
  return window.ezwayAuth && window.isAuthenticated !== false ? window.ezwayAuth : null
}

function logoutUser(user: AuthUser) {
  if (user.is_admin) {
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = user.logout_url || '/admin/logout'

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    if (csrfToken) {
      const input = document.createElement('input')
      input.type = 'hidden'
      input.name = '_token'
      input.value = csrfToken
      form.appendChild(input)
    }

    document.body.appendChild(form)
    form.submit()
    return
  }

  window.location.href = user.logout_url || '/logout'
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
  const [videos, liveTv, ondemand, dashboard, navigationMenu] = await Promise.allSettled([
    loadVideosPage('', 1, 14),
    api.get<ApiEnvelope<LiveTvDashboard>>('/api/v3/livetv-dashboard'),
    api.get<ApiEnvelope<PaginatedData<MediaItem>>>('/api/v3/ondemand?per_page=14'),
    api.get<ApiEnvelope<DashboardData>>('/api/v3/dashboard-detail'),
    api.get<ApiEnvelope<NavigationMenuResponse>>('/api/v3/navigation-menu'),
  ])
  const dashboardData = dashboard.status === 'fulfilled' ? dashboard.value.data : undefined
  const navigationMenuData = navigationMenu.status === 'fulfilled' ? navigationMenu.value.data : undefined
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
    visibleMenuKeys: navigationMenuData?.burger_menu?.map((item) => item.key) ?? null,
  }
}

function isMenuVisible(visibleMenuKeys: string[] | null, key: string) {
  return visibleMenuKeys === null || visibleMenuKeys.includes(key)
}

function getInitialVisibleMenuKeys() {
  return Array.isArray(window.ezwayVisibleMenuKeys) ? window.ezwayVisibleMenuKeys : null
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
