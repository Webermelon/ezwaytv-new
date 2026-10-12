import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Calendar, ChevronDown, Play, Share2, Star, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { MediaItem } from '@/modules/home/types'
import { loadTvShowDetail, type EpisodeItem } from './tvshowsApi'

export function TvShowDetailPage() {
  const showId = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const query = useQuery({ queryKey: ['tv-show-detail', showId], queryFn: () => loadTvShowDetail(showId), enabled: Boolean(showId) })
  return <main className="min-h-screen bg-[#050505] text-white"><AppHeader active="tvshows" />{query.isLoading ? <div className="h-[70svh] animate-pulse bg-white/[0.04]" /> : query.isError || !query.data ? <div className="flex min-h-[60svh] items-center justify-center px-4 text-white/55">This TV show could not be loaded.</div> : <ShowContent show={query.data} />}</main>
}

function ShowContent({ show }: { show: NonNullable<Awaited<ReturnType<typeof loadTvShowDetail>>> }) {
  const seasons = show.tvShowLinks ?? []
  const [seasonId, setSeasonId] = useState(String(seasons[0]?.season_id ?? ''))
  const season = seasons.find((item) => String(item.season_id) === seasonId) ?? seasons[0]
  const episodes = season?.episodes ?? []
  const latestEpisode = episodes[0]
  const genres = show.genres?.map((item) => item.name).filter(Boolean).join(', ')
  const year = String(show.release_year ?? show.release_date ?? '').slice(0, 4)

  return <>
    <section className="relative isolate h-[clamp(460px,58svh,600px)] overflow-hidden border-b border-white/10">
      <MediaThumbnail src={show.thumbnail_image ?? show.poster_tv_image ?? show.poster_image} alt={show.name} className="absolute inset-0 -z-20 h-full w-full !aspect-auto" imageClassName="object-cover object-center" />
      <div className="absolute inset-0 -z-10 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,.92)_26%,rgba(5,5,5,.28)_68%,rgba(5,5,5,.12)),linear-gradient(0deg,#050505_0%,transparent_55%)]" />
      <div className="mx-auto flex h-full max-w-[1800px] flex-col justify-end px-4 pb-8 pt-16 sm:px-8 sm:pb-10 lg:px-12">
        <a href="/tv-shows" className="absolute top-6 inline-flex items-center gap-2 text-sm font-semibold text-white/75 hover:text-white"><ArrowLeft className="h-4 w-4" />Back to Browse</a>
        <div className="max-w-xl">
          <Badge className="mb-5 rounded-sm bg-primary text-[11px] font-black text-black"><Tv className="mr-1.5 h-3.5 w-3.5" />TV SHOW</Badge>
          <h1 className="text-4xl font-black leading-none sm:text-6xl">{show.name}</h1>
          <div className="mt-4 flex flex-wrap items-center gap-3 text-sm text-white/72">{year && <span>{year}</span>}{genres && <><span className="text-white/25">|</span><span>{genres}</span></>}<span className="text-white/25">|</span><span>{seasons.length} {seasons.length === 1 ? 'Season' : 'Seasons'}</span>{show.content_rating && <><span className="text-white/25">|</span><span>{show.content_rating}</span></>}{show.imdb_rating && <span className="inline-flex items-center gap-1 rounded border border-white/35 px-1.5 py-0.5"><Star className="h-3 w-3 fill-primary text-primary" />{show.imdb_rating}</span>}</div>
          {show.description && <p className="mt-5 line-clamp-4 text-sm leading-7 text-white/72 sm:text-base">{stripHtml(show.description)}</p>}
          <div className="mt-7 flex flex-wrap gap-3">{latestEpisode && <Button asChild className="h-11 bg-primary px-5 font-black text-black hover:bg-primary/90"><a href={`/episode-details/${latestEpisode.id}?autoplay=1`}><Play className="h-4 w-4 fill-current" />Watch Latest Episode</a></Button>}<WatchlistToggleButton entertainmentId={show.id} type="tvshow" initialInWatchlist={show.is_watch_list} /><Button variant="outline" className="h-11 border-white/25 bg-black/25" onClick={() => sharePage(show.name)}><Share2 className="h-4 w-4" />Share</Button></div>
        </div>
      </div>
    </section>

    <div className="mx-auto max-w-[1800px] px-4 py-8 sm:px-8 lg:px-12">
      <div className="mb-6 flex gap-8 border-b border-white/12 text-sm font-bold"><span className="border-b-2 border-primary px-3 pb-4 text-white">Episodes</span><span className="px-3 pb-4 text-white/50">About</span><span className="px-3 pb-4 text-white/50">Cast</span></div>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section>
          <div className="mb-4 flex items-center gap-4"><label className="relative"><select value={String(season?.season_id ?? '')} onChange={(event) => setSeasonId(event.target.value)} className="h-11 min-w-40 appearance-none rounded border border-white/20 bg-[#111] px-4 pr-10 text-sm font-bold text-white outline-none focus:border-primary">{seasons.map((item) => <option key={item.season_id} value={item.season_id}>{item.name}</option>)}</select><ChevronDown className="pointer-events-none absolute right-3 top-3.5 h-4 w-4 text-white/60" /></label><span className="text-sm text-white/50">{episodes.length} Episodes</span></div>
          <div className="divide-y divide-white/10 border-y border-white/10">{episodes.length ? episodes.map((episode, index) => <EpisodeRow key={episode.id} episode={episode} index={index} featured={index === 0} />) : <div className="py-14 text-center text-white/45">No published episodes in this season.</div>}</div>
        </section>
        <aside className="space-y-5">
          <div className="rounded-md border border-white/12 bg-white/[0.035] p-5"><h2 className="font-black">Series Information</h2><dl className="mt-5 grid grid-cols-[90px_1fr] gap-y-3 text-sm"><dt className="text-white/48">Genre</dt><dd>{genres || 'Not specified'}</dd><dt className="text-white/48">Seasons</dt><dd>{seasons.length}</dd><dt className="text-white/48">Episodes</dt><dd>{seasons.reduce((total, item) => total + (item.episodes?.length ?? 0), 0)}</dd><dt className="text-white/48">First aired</dt><dd>{year || 'Not specified'}</dd></dl></div>
          {show.more_items?.length ? <RelatedShows shows={show.more_items} /> : null}
        </aside>
      </div>
      {(show.casts?.length || show.directors?.length) ? <section className="mt-10"><h2 className="mb-4 text-xl font-black">Cast &amp; Crew</h2><div className="grid gap-3 sm:grid-cols-2"><PeopleRow label="Cast" people={show.casts} /><PeopleRow label="Directors" people={show.directors} /></div></section> : null}
    </div>
  </>
}

function EpisodeRow({ episode, index, featured }: { episode: EpisodeItem; index: number; featured: boolean }) {
  return <article className={`grid grid-cols-[32px_112px_minmax(0,1fr)] items-center gap-3 px-2 py-3 sm:grid-cols-[36px_150px_minmax(0,1fr)_110px] ${featured ? 'rounded-sm border border-primary/80 bg-primary/[0.055]' : ''}`}><span className="text-center text-lg font-black text-white/75">{episode.episode_number ?? index + 1}</span><a href={`/episode-details/${episode.id}?autoplay=1`} className="relative aspect-video overflow-hidden rounded-sm bg-white/5"><MediaThumbnail src={episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image} alt={episode.name} className="h-full w-full" imageClassName="object-cover" />{episode.duration && <span className="absolute bottom-1 right-1 rounded-sm bg-black/85 px-1.5 py-0.5 text-[10px] font-bold">{episode.duration}</span>}</a><div className="min-w-0"><h3 className="line-clamp-1 font-bold">{episode.name}</h3>{episode.release_date && <p className="mt-1 text-xs text-white/48">{formatDate(episode.release_date)}</p>}{episode.short_desc || episode.description ? <p className="mt-1 line-clamp-2 text-xs leading-5 text-white/55">{stripHtml(episode.short_desc ?? episode.description ?? '')}</p> : null}</div><Button asChild variant={featured ? 'default' : 'outline'} className={`col-start-3 mt-2 h-9 justify-self-start sm:col-start-4 sm:row-start-1 sm:mt-0 sm:justify-self-end ${featured ? 'bg-primary text-black hover:bg-primary/90' : 'border-white/25'}`}><a href={`/episode-details/${episode.id}?autoplay=1`}><Play className="h-3.5 w-3.5 fill-current" />{featured ? 'Watch Now' : 'Watch'}</a></Button></article>
}

function RelatedShows({ shows }: { shows: MediaItem[] }) { return <section><h2 className="mb-3 text-lg font-black">You May Also Like</h2><div className="space-y-3">{shows.slice(0, 4).map((show) => <a key={show.id} href={`/tvshow-details/${show.slug ?? show.id}`} className="flex gap-3"><MediaThumbnail src={show.poster_tv_image ?? show.poster_image} alt={show.name} className="aspect-video w-36 shrink-0 overflow-hidden rounded-sm" imageClassName="object-cover" /><div className="min-w-0 py-1"><h3 className="line-clamp-2 text-sm font-bold">{show.name}</h3><span className="mt-2 block text-xs text-white/45">View series</span></div></a>)}</div></section> }
function PeopleRow({ label, people }: { label: string; people?: Array<{ name?: string | null }> }) { const value = people?.map((person) => person.name).filter(Boolean).join(', '); return value ? <div className="rounded-md border border-white/10 bg-white/[0.035] p-4 text-sm"><strong>{label}:</strong> <span className="text-white/62">{value}</span></div> : null }
function stripHtml(value: string) { return value.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/s+/g, ' ').trim() }
function formatDate(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }
async function sharePage(title: string) { if (navigator.share) await navigator.share({ title, url: window.location.href }); else await navigator.clipboard?.writeText(window.location.href) }
