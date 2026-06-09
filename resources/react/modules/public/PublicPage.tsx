import { ArrowLeft, Lock, Play } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

export function PublicPage() {
  const path = window.location.pathname
  const meta = pageMeta(path)

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader />

      <section className="relative min-h-[70vh] overflow-hidden">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_72%_0%,rgba(229,9,20,0.28),transparent_28%),linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.9)_36%,rgba(5,5,5,0.48)_74%,#050505_100%)]" />
        <div className="absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-[#050505] to-transparent" />

        <div className="relative z-10 flex min-h-[70vh] max-w-4xl flex-col justify-end px-4 pb-16 pt-24 sm:px-8 lg:px-12">
          <a href="/" className="mb-6 inline-flex w-fit items-center gap-2 text-sm font-semibold text-white/58 hover:text-white">
            <ArrowLeft className="h-4 w-4" />
            Home
          </a>
          <Badge className="w-fit rounded-sm bg-primary text-white">{meta.badge}</Badge>
          <h1 className="mt-4 max-w-3xl text-5xl font-black leading-none sm:text-6xl">{meta.title}</h1>
          <p className="mt-5 max-w-2xl text-sm leading-6 text-white/68 sm:text-base">{meta.description}</p>
          <div className="mt-7 flex flex-wrap gap-3">
            {meta.primaryHref ? (
              <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
                <a href={meta.primaryHref}>
                  <Play className="h-5 w-5 fill-current" />
                  {meta.primaryLabel}
                </a>
              </Button>
            ) : null}
            <Button asChild size="lg" variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
              <a href="/">
                Browse Home
              </a>
            </Button>
          </div>
        </div>
      </section>

      <section className="px-4 pb-16 sm:px-8 lg:px-12">
        <div className="grid gap-4 lg:grid-cols-3">
          {meta.panels.map((panel) => (
            <article key={panel.title} className="rounded-md border border-white/10 bg-white/[0.045] p-5">
              <div className="mb-3 flex items-center gap-2">
                <Lock className="h-4 w-4 text-primary" />
                <h2 className="font-bold">{panel.title}</h2>
              </div>
              <p className="text-sm leading-6 text-white/58">{panel.body}</p>
            </article>
          ))}
        </div>
      </section>
    </main>
  )
}

function pageMeta(path: string) {
  if (path.startsWith('/login') || path.startsWith('/register') || path.startsWith('/forget-password')) {
    return {
      badge: 'Account',
      title: path.startsWith('/register') ? 'Create your account' : path.startsWith('/forget-password') ? 'Reset password' : 'Sign in',
      description: 'Account screens are now routed through the React SPA shell. Backend auth endpoints remain available for the form integration pass.',
      primaryLabel: 'Home',
      primaryHref: '/',
      panels: servicePanels('Auth'),
    }
  }

  if (path.startsWith('/movies') || path.startsWith('/movie-details')) {
    return {
      badge: 'Movies',
      title: path.startsWith('/movie-details') ? 'Movie details' : 'Movies',
      description: 'The movies area is React-owned at the route level and ready for the next API-connected content rail implementation.',
      primaryLabel: 'Videos',
      primaryHref: '/videos',
      panels: servicePanels('Movies'),
    }
  }

  if (path.startsWith('/tv-shows') || path.startsWith('/tvshow-details') || path.startsWith('/episode-details')) {
    return {
      badge: 'TV Shows',
      title: path.startsWith('/episode-details') ? 'Episode details' : 'TV Shows',
      description: 'TV show routes now stay in the SPA shell while the existing backend remains the data and playback service layer.',
      primaryLabel: 'Live TV',
      primaryHref: '/livetv',
      panels: servicePanels('TV Shows'),
    }
  }

  if (path.startsWith('/pay-per-view') || path.startsWith('/unlock-videos')) {
    return {
      badge: 'Pay Per View',
      title: 'Pay Per View',
      description: 'Purchase and entitlement pages are routed through React. Payment processing routes are kept intact as service endpoints.',
      primaryLabel: 'Videos',
      primaryHref: '/videos',
      panels: servicePanels('Payments'),
    }
  }

  if (path.startsWith('/search')) {
    return {
      badge: 'Search',
      title: 'Search',
      description: 'Search now lives in the React app surface and can be wired to the existing search APIs without bringing Blade back.',
      primaryLabel: 'Videos',
      primaryHref: '/videos',
      panels: servicePanels('Search'),
    }
  }

  return {
    badge: 'eZWay TV',
    title: readableTitle(path),
    description: 'This public frontend route is now owned by the React SPA shell. Backend controllers remain as APIs and service endpoints where needed.',
    primaryLabel: 'Browse Videos',
    primaryHref: '/videos',
    panels: servicePanels('Frontend'),
  }
}

function servicePanels(scope: string) {
  return [
    {
      title: `${scope} route is SPA-owned`,
      body: 'Navigation stays inside React for public frontend pages, so the browser no longer jumps back into Blade for normal browsing.',
    },
    {
      title: 'Backend remains intact',
      body: 'Database, APIs, admin modules, ads, stats, streams, and payment endpoints are preserved as service contracts.',
    },
    {
      title: 'Next module pass',
      body: 'This route is ready for a dedicated API-connected React screen without changing database tables.',
    },
  ]
}

function readableTitle(path: string) {
  const segment = path.split('/').filter(Boolean).at(0) ?? 'Home'
  return segment.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
