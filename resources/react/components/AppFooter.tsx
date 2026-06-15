import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Film, Globe2, Home, Mail, Phone, Search, Tv } from 'lucide-react'

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

  return (
    <>
      <footer className="border-t border-white/10 bg-[#050505] px-4 pb-24 pt-12 text-white sm:px-8 lg:px-12 lg:pb-0">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.9fr)_1px_minmax(0,2fr)]">
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

        <div className="mx-auto mt-10 max-w-5xl rounded-md border border-white/10 bg-white/[0.035] px-4 py-4">
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
    </>
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
    <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-black/92 px-2 py-2 text-white shadow-2xl backdrop-blur lg:hidden">
      <ul className="grid grid-cols-5 gap-1">
        {items.map((item) => {
          const Icon = item.icon

          return (
            <li key={item.label}>
              <a href={item.href} className="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[10px] font-bold text-white/62 hover:bg-white/[0.06] hover:text-white">
                <Icon className="h-4 w-4" />
                <span className="max-w-full truncate">{item.label}</span>
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
