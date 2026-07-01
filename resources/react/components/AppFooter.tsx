import { useEffect, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Film, Globe2, Home, Mail, Phone, Search, Send, Tv, X } from 'lucide-react'

import { api } from '@/lib/api'
import { BrandLogo } from '@/components/BrandLogo'
import type { ApiEnvelope } from '@/modules/home/types'

type FooterLink = {
  id?: number | string | null
  name?: string | null
  slug?: string | null
  type?: string | null
  url?: string | null
}

type FooterData = {
  short_description?: string | null
  inquriy_email?: string | null
  helpline_number?: string | null
  facebook_url?: string | null
  instagram_url?: string | null
  youtube_url?: string | null
  x_url?: string | null
  play_store_url?: string | null
  app_store_url?: string | null
  copyright_text?: string | null
  premium_shows?: FooterLink[]
  top_movies?: FooterLink[]
  top_channels?: FooterLink[]
  live_tv_channels?: FooterLink[]
  pages?: FooterLink[]
}

export function AppFooter() {
  const [subscriberFormOpen, setSubscriberFormOpen] = useState(false)
  const footerQuery = useQuery({
    queryKey: ['footer-data'],
    queryFn: loadFooterData,
    staleTime: 5 * 60_000,
    initialData: readCachedFooterData,
  })
  const footer = footerQuery.data

  useEffect(() => {
    if (footer) {
      writeCachedFooterData(footer)
    }
  }, [footer])

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
      <SubscriberFooterSection onSubscribe={() => setSubscriberFormOpen(true)} />
      <footer className="border-t border-white/10 bg-[#050505] px-4 pb-24 pt-12 text-white sm:px-8 lg:px-12 lg:pb-0">
        <div className="grid w-full gap-10 lg:grid-cols-[minmax(0,0.9fr)_1px_minmax(0,2fr)]">
          <section className="min-w-0">
            <BrandLogo />
            <p className="mt-5 max-w-sm text-sm leading-6 text-white/58">
              {footer?.short_description ?? 'Stream movies, videos, live channels, and community stories on eZWay TV.'}
            </p>

            <div className="mt-7 space-y-3 text-sm text-white/68">
              {footer?.inquriy_email ? (
                <a href={`mailto:${footer.inquriy_email}`} className="flex w-fit items-center gap-2 hover:text-white">
                  <Mail className="h-4 w-4 text-primary" />
                  {footer.inquriy_email}
                </a>
              ) : null}
              {footer?.helpline_number ? (
                <a href={`tel:${footer.helpline_number}`} className="flex w-fit items-center gap-2 hover:text-white">
                  <Phone className="h-4 w-4 text-primary" />
                  {footer.helpline_number}
                </a>
              ) : null}
            </div>

            <SocialLinks footer={footer} />
          </section>

          <div className="hidden w-px bg-white/10 lg:block" />

          <section className="grid gap-8 sm:grid-cols-3">
            <FooterColumn title="On Demand Channels" links={footer?.top_channels ?? []} fallbackPrefix="/on-demand/" />
            <FooterColumn title="Live TV Channels" links={footer?.live_tv_channels ?? []} fallbackPrefix="/livetv/" />
            <section>
              <h2 className="text-base font-black text-white">Download App</h2>
              <p className="mt-5 text-sm leading-6 text-white/58">Download our app for the best streaming experience.</p>
              <div className="mt-6 flex flex-wrap gap-3">
                {footer?.play_store_url ? (
                  <a href={footer.play_store_url} target="_blank" rel="noreferrer" className="block">
                    <img src="/img/web-img/play_store.png" alt="Play Store" className="h-10 w-auto" loading="lazy" />
                  </a>
                ) : null}
                {footer?.app_store_url ? (
                  <a href={footer.app_store_url} target="_blank" rel="noreferrer" className="block">
                    <img src="/img/web-img/app_store.png" alt="App Store" className="h-10 w-auto" loading="lazy" />
                  </a>
                ) : null}
              </div>
            </section>
          </section>
        </div>

        <div className="mt-10 w-full rounded-md border border-white/10 bg-white/[0.035] px-4 py-4">
          <nav className="flex flex-wrap items-center justify-center gap-x-5 gap-y-3 text-sm font-semibold text-white/62">
            {(footer?.pages ?? []).map((page) => (
              <a key={`${page.id ?? page.slug}`} href={page.url ?? `/page/${page.slug}`} className="hover:text-white">
                {page.name}
              </a>
            ))}
            <a href="/faq" className="hover:text-white">FAQ</a>
          </nav>
        </div>

        <div className="mt-8 border-t border-white/10 py-5 text-center text-sm text-white/50">
          {footer?.copyright_text ? (
            <span dangerouslySetInnerHTML={{ __html: footer.copyright_text }} />
          ) : (
            <span>All Rights Reserved.</span>
          )}
        </div>
      </footer>

      <MobileFooterMenu />
      {subscriberFormOpen ? <SubscriberFormModal onClose={() => setSubscriberFormOpen(false)} /> : null}
    </>
  )
}

function SubscriberFooterSection({ onSubscribe }: { onSubscribe: () => void }) {
  return (
    <section className="border-t border-white/10 bg-[radial-gradient(circle_at_78%_20%,rgba(212,168,67,0.16),transparent_30%),linear-gradient(180deg,#090909_0%,#050505_100%)] px-4 py-12 text-white sm:px-8 lg:px-12">
      <div className="mx-auto grid w-full max-w-[1800px] gap-6 rounded-2xl border border-[#d4a843]/22 bg-white/[0.035] p-5 shadow-2xl shadow-black/35 sm:p-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <div className="min-w-0">
          <p className="text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">eZWay TV updates</p>
          <h2 className="mt-3 text-3xl font-black leading-tight sm:text-4xl">Stay connected with new channels, shows, and announcements.</h2>
          <p className="mt-3 max-w-2xl text-sm leading-6 text-white/58">Subscribe for eZWay TV updates, channel news, and subscriber-only announcements. No payment required.</p>
        </div>
        <button
          type="button"
          onClick={onSubscribe}
          className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#d4a843] px-6 text-sm font-black text-black transition hover:bg-[#efc955] sm:w-auto"
        >
          <Send className="h-4 w-4" />
          Subscribe for updates
        </button>
      </div>
    </section>
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
    <div className="fixed inset-0 z-[10000] flex items-center justify-center bg-black/76 px-3 py-5 backdrop-blur-md sm:px-5" role="dialog" aria-modal="true" aria-labelledby="subscriber-form-title">
      <button type="button" className="absolute inset-0 cursor-default" aria-label="Close subscriber form" onClick={onClose} />
      <section className="relative flex max-h-[92dvh] w-full max-w-[560px] flex-col overflow-hidden rounded-lg border border-[#d4a843]/28 bg-[radial-gradient(circle_at_82%_0%,rgba(212,168,67,0.22),transparent_34%),linear-gradient(180deg,#111_0%,#050505_100%)] shadow-2xl shadow-black">
        <div className="flex min-h-14 items-center justify-between gap-3 border-b border-white/10 bg-white/[0.035] px-4 py-3 sm:px-5">
          <BrandLogo imageClassName="max-h-10 max-w-[170px]" textClassName="text-xl" placeholderClassName="h-9 w-[160px]" />
          <button type="button" onClick={onClose} aria-label="Close subscriber form" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-white/10 bg-white/[0.08] text-white transition hover:bg-white/[0.14]">
            <X className="h-5 w-5" />
          </button>
        </div>
        <form onSubmit={handleSubmit} className="grid gap-5 px-5 py-6 sm:px-7 sm:py-7">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">Subscribe eZWay TV</p>
            <h2 id="subscriber-form-title" className="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Stay connected with eZWay TV.</h2>
            <p className="mt-3 text-sm leading-6 text-white/62">Get updates, channel news, and subscriber-only announcements from eZWay TV.</p>
          </div>

          <div className="grid gap-4">
            <label className="grid gap-2">
              <span className="text-xs font-black uppercase tracking-wide text-white/54">Full Name</span>
              <input value={fullName} onChange={(event) => setFullName(event.target.value)} required maxLength={255} autoComplete="name" className="h-12 rounded-md border border-white/12 bg-white/[0.075] px-4 text-sm font-semibold text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70 focus:bg-white/[0.10]" placeholder="Your name" />
            </label>
            <label className="grid gap-2">
              <span className="text-xs font-black uppercase tracking-wide text-white/54">Email</span>
              <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required maxLength={255} autoComplete="email" className="h-12 rounded-md border border-white/12 bg-white/[0.075] px-4 text-sm font-semibold text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70 focus:bg-white/[0.10]" placeholder="you@example.com" />
            </label>
          </div>

          {message ? (
            <div className={['rounded-md border px-4 py-3 text-sm font-semibold', status === 'success' ? 'border-emerald-400/24 bg-emerald-500/12 text-emerald-100' : 'border-red-400/24 bg-red-500/12 text-red-100'].join(' ')}>{message}</div>
          ) : null}

          <button type="submit" disabled={status === 'submitting'} className="h-12 w-full rounded-md bg-[#d4a843] text-sm font-black text-black transition hover:bg-[#f3c84b] disabled:cursor-not-allowed disabled:opacity-70">
            {status === 'submitting' ? 'Subscribing...' : 'Subscribe'}
          </button>
        </form>
      </section>
    </div>
  )
}

function FooterColumn({ title, links, fallbackPrefix }: { title: string; links: FooterLink[]; fallbackPrefix: string }) {
  return (
    <section>
      <h2 className="text-base font-black text-white">{title}</h2>
      <ul className="mt-5 space-y-3 text-sm font-semibold text-white/58">
        {links.slice(0, 4).map((link) => (
          <li key={`${link.id ?? link.slug}`}>
            <a href={link.url ?? `${fallbackPrefix}${link.slug}`} className="line-clamp-1 hover:text-white">
              {link.name}
            </a>
          </li>
        ))}
      </ul>
    </section>
  )
}

function SocialLinks({ footer }: { footer?: FooterData }) {
  const links = [
    { label: 'Facebook', value: footer?.facebook_url },
    { label: 'Instagram', value: footer?.instagram_url },
    { label: 'YouTube', value: footer?.youtube_url },
    { label: 'X', value: footer?.x_url },
  ].filter((item) => item.value)

  if (links.length === 0) return null

  return (
    <div className="mt-7 flex flex-wrap gap-3">
      {links.map((link) => (
        <a
          key={link.label}
          href={link.value}
          target="_blank"
          rel="noreferrer"
          className="flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/[0.04] text-xs font-black text-white/70 hover:border-primary/60 hover:text-primary"
          aria-label={link.label}
        >
          {link.label.slice(0, 1)}
        </a>
      ))}
    </div>
  )
}

function MobileFooterMenu() {
  const items = [
    { label: 'Home', href: '/', icon: Home },
    { label: 'Search', href: '/search', icon: Search },
    { label: 'On Demand', href: '/on-demand', icon: Film },
    { label: 'Live TV', href: '/livetv', icon: Tv },
    { label: 'Distribution', href: '/distribution', icon: Globe2 },
  ]

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 w-screen max-w-[100dvw] overflow-hidden border-t border-white/10 bg-black/92 px-1.5 py-2 text-white shadow-2xl backdrop-blur lg:hidden">
      <ul className="grid w-full min-w-0 grid-cols-5 gap-1 overflow-hidden">
        {items.map((item) => {
          const Icon = item.icon

          return (
            <li key={item.label} className="min-w-0 overflow-hidden">
              <a href={item.href} className="flex min-h-14 min-w-0 max-w-full flex-col items-center justify-center gap-1 overflow-hidden rounded-md px-0.5 text-[9px] font-bold text-white/62 hover:bg-white/[0.06] hover:text-white">
                <Icon className="h-4 w-4" />
                <span className="block max-w-full truncate leading-none">{item.label}</span>
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}

async function loadFooterData() {
  const response = await api.get<ApiEnvelope<FooterData>>('/api/v3/footer-data')

  return response.data
}

function readCachedFooterData() {
  try {
    const cached = window.localStorage.getItem('ezway_footer_data_v3')

    return cached ? JSON.parse(cached) as FooterData : undefined
  } catch {
    return undefined
  }
}

function writeCachedFooterData(footer: FooterData) {
  try {
    window.localStorage.setItem('ezway_footer_data_v3', JSON.stringify(footer))
  } catch {
    // Ignore storage failures; the live API data is still rendered.
  }
}
