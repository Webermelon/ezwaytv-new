import { useEffect, useState } from 'react'
import type { ReactNode } from 'react'
import { ArrowLeft, Calendar, Film, MapPin, Star, Tv } from 'lucide-react'

import { MediaThumbnail } from '@/components/MediaThumbnail'
import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { loadCastCrewDetail, type CastCrewDetail } from './castCrewApi'

export function CastCrewDetailPage() {
  const id = getIdFromPath()
  const type = new URLSearchParams(window.location.search).get('type')
  const [detail, setDetail] = useState<CastCrewDetail | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let mounted = true

    setLoading(true)
    loadCastCrewDetail(id, type)
      .then((data) => {
        if (mounted) setDetail(data)
      })
      .finally(() => {
        if (mounted) setLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [id, type])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="castcrew" />

      {loading ? (
        <section className="grid min-h-[72vh] items-center gap-8 px-4 py-16 sm:px-8 lg:grid-cols-[320px_1fr] lg:px-12">
          <div className="aspect-square animate-pulse rounded-full bg-white/[0.06]" />
          <div>
            <div className="h-14 max-w-xl animate-pulse rounded-md bg-white/[0.08]" />
            <div className="mt-5 h-32 max-w-3xl animate-pulse rounded-md bg-white/[0.05]" />
          </div>
        </section>
      ) : detail ? (
        <section className="relative overflow-hidden px-4 py-12 sm:px-8 lg:px-12">
          {detail.profile_image ? <img src={detail.profile_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-14 blur-md" /> : null}
          <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.94)_46%,#050505_100%)]" />

          <div className="relative z-10">
            <a href="/castcrew-list" className="mb-8 inline-flex w-fit items-center gap-2 text-sm font-semibold text-white/58 hover:text-white">
              <ArrowLeft className="h-4 w-4" />
              Personalities
            </a>

            <div className="grid items-center gap-8 lg:grid-cols-[320px_minmax(0,1fr)]">
              <div className="mx-auto aspect-square w-full max-w-[320px] overflow-hidden rounded-full border border-white/10 bg-white/[0.06] shadow-2xl">
                <MediaThumbnail src={detail.profile_image} alt={detail.name} className="aspect-square rounded-full" />
              </div>

              <div className="min-w-0">
                <Badge className="rounded-sm bg-primary text-white">{detail.role ?? 'Personality'}</Badge>
                <h1 className="mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">{detail.name}</h1>
                {detail.bio ? <p className="mt-5 max-w-4xl text-sm leading-7 text-white/66 sm:text-base">{stripHtml(detail.bio)}</p> : null}

                <div className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  <Stat icon={<Film className="h-4 w-4" />} label="Movies" value={String(detail.total_movies ?? 0)} />
                  <Stat icon={<Tv className="h-4 w-4" />} label="TV Shows" value={String(detail.total_tv_show ?? 0)} />
                  <Stat icon={<Star className="h-4 w-4" />} label="Rating" value={String(detail.rating ?? 0)} />
                  <Stat icon={<Calendar className="h-4 w-4" />} label="Birth Date" value={detail.birth_date ?? 'N/A'} />
                </div>

                <div className="mt-5 grid gap-3 lg:grid-cols-2">
                  {detail.birth_place ? <InfoRow icon={<MapPin className="h-4 w-4" />} label="Birth Place" value={detail.birth_place} /> : null}
                  {detail.top_genres ? <InfoRow icon={<Film className="h-4 w-4" />} label="Top Genres" value={detail.top_genres} /> : null}
                </div>
              </div>
            </div>
          </div>
        </section>
      ) : (
        <section className="px-4 py-16 sm:px-8 lg:px-12">
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-8 text-white/62">Cast/Crew detail could not be loaded.</div>
        </section>
      )}
    </main>
  )
}

function Stat({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <article className="rounded-md border border-white/10 bg-white/[0.045] p-4">
      <div className="mb-2 flex items-center gap-2 text-xs font-bold uppercase text-white/42">
        {icon}
        {label}
      </div>
      <div className="text-xl font-black text-white">{value}</div>
    </article>
  )
}

function InfoRow({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <article className="rounded-md border border-white/10 bg-white/[0.045] p-4">
      <div className="mb-2 flex items-center gap-2 text-xs font-bold uppercase text-white/42">
        {icon}
        {label}
      </div>
      <div className="text-sm leading-6 text-white/70">{value}</div>
    </article>
  )
}

function getIdFromPath() {
  const match = window.location.pathname.match(/^\/castcrew-detail\/([^/]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
