import * as React from 'react'
import { AlertCircle, ChevronDown, Loader2 } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { api } from '@/lib/api'
import { useSpaPath } from '@/lib/spa-router'
import type { ApiEnvelope } from '@/modules/home/types'

type CmsPage = {
  id: number | string
  slug: string
  name: string
  description?: string | null
  content_type?: string | null
  embed_code?: string | null
}

type FaqItem = {
  id: number | string
  question: string
  answer: string
}

type PublicState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'page'; page: CmsPage }
  | { status: 'faq'; items: FaqItem[] }

export function PublicPage() {
  const path = useSpaPath()
  const pathname = path.split(/[?#]/)[0] || '/'
  const [state, setState] = React.useState<PublicState>({ status: 'loading' })

  React.useEffect(() => {
    const controller = new AbortController()

    setState({ status: 'loading' })

    loadPublicContent(pathname, controller.signal)
      .then(setState)
      .catch((error) => {
        if (controller.signal.aborted) return

        setState({
          status: 'error',
          message: error instanceof Error ? error.message : 'This page could not be loaded.',
        })
      })

    return () => controller.abort()
  }, [pathname])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader />

      <section className="border-b border-white/10 bg-[#080808] px-4 pb-10 pt-28 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-primary">eZWay TV</p>
          <h1 className="mt-3 text-3xl font-black leading-tight sm:text-5xl">
            {pageTitle(pathname, state)}
          </h1>
        </div>
      </section>

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          {state.status === 'loading' ? <LoadingState /> : null}
          {state.status === 'error' ? <ErrorState message={state.message} /> : null}
          {state.status === 'page' ? <CmsPageContent page={state.page} /> : null}
          {state.status === 'faq' ? <FaqContent items={state.items} /> : null}
        </div>
      </section>
    </main>
  )
}

async function loadPublicContent(pathname: string, signal: AbortSignal): Promise<PublicState> {
  if (pathname === '/faq') {
    const response = await api.get<ApiEnvelope<FaqItem[]>>('/api/faq-list', { signal })

    return {
      status: 'faq',
      items: response.data ?? [],
    }
  }

  const slug = getPageSlug(pathname)

  if (!slug) {
    throw new Error('This public page is not available yet.')
  }

  const response = await api.get<ApiEnvelope<CmsPage>>(`/api/page-detail/${encodeURIComponent(slug)}`, { signal })

  if (!response.data) {
    throw new Error('This page could not be found.')
  }

  return {
    status: 'page',
    page: response.data,
  }
}

function CmsPageContent({ page }: { page: CmsPage }) {
  const isEmbed = page.content_type === 'embed'
  const html = isEmbed ? page.embed_code : page.description

  if (!html) {
    return <EmptyState message="No content has been added to this page yet." />
  }

  return (
    <article
      className={isEmbed ? 'public-content public-content-embed' : 'public-content'}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  )
}

function FaqContent({ items }: { items: FaqItem[] }) {
  if (items.length === 0) {
    return <EmptyState message="No FAQs are available yet." />
  }

  return (
    <div className="space-y-3">
      {items.map((item) => (
        <details key={item.id} className="group rounded-md border border-white/10 bg-white/[0.045] px-5 py-4">
          <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-bold text-white">
            <span>{item.question}</span>
            <ChevronDown className="h-5 w-5 shrink-0 text-primary transition-transform group-open:rotate-180" />
          </summary>
          <div
            className="public-content mt-4 border-t border-white/10 pt-4"
            dangerouslySetInnerHTML={{ __html: item.answer }}
          />
        </details>
      ))}
    </div>
  )
}

function LoadingState() {
  return (
    <div className="flex min-h-48 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
      <Loader2 className="h-7 w-7 animate-spin text-primary" />
    </div>
  )
}

function ErrorState({ message }: { message: string }) {
  return (
    <div className="flex items-center gap-3 rounded-md border border-red-500/30 bg-red-500/10 p-5 text-red-100">
      <AlertCircle className="h-5 w-5 shrink-0" />
      <p>{message}</p>
    </div>
  )
}

function EmptyState({ message }: { message: string }) {
  return (
    <div className="rounded-md border border-white/10 bg-white/[0.035] p-6 text-white/70">
      {message}
    </div>
  )
}

function getPageSlug(pathname: string) {
  if (!pathname.startsWith('/pages/')) return ''

  return decodeURIComponent(pathname.replace('/pages/', '').split('/')[0] ?? '')
}

function pageTitle(pathname: string, state: PublicState) {
  if (state.status === 'page') return state.page.name
  if (pathname === '/faq') return 'FAQ'

  return getPageSlug(pathname).replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) || 'Page'
}
