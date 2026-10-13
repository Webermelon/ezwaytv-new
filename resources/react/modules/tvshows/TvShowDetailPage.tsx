import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, CalendarDays, ChevronDown, Clock3, Film, Play, Plus, Share2 } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { Button } from '@/components/ui/button'
import type { MediaItem } from '@/modules/home/types'
import { loadTvShowDetail, type EpisodeItem, type SeasonItem } from './tvshowsApi'

type Person = { id?: string | number; name?: string | null; profile_image?: string | null; designation?: string | null }

export function TvShowDetailPage() {
  const id = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const query = useQuery({ queryKey: ['tv-show-detail', id], queryFn: () => loadTvShowDetail(id), enabled: Boolean(id) })
  return <main className="min-h-screen bg-[#050505] text-white"><AppHeader active="tvshows" />{query.isLoading ? <div className="h-[55svh] animate-pulse bg-white/[0.04]" /> : null}{query.isError || (!query.isLoading && !query.data) ? <div className="flex min-h-[60svh] items-center justify-center px-4 text-white/55">This TV show could not be loaded.</div> : null}{query.data ? <ShowDetail show={query.data} /> : null}</main>
}

function ShowDetail({ show }: { show: NonNullable<Awaited<ReturnType<typeof loadTvShowDetail>>> }) {
  const seasons = show.tvShowLinks ?? []
  const [seasonId, setSeasonId] = useState(String(seasons[0]?.season_id ?? ''))
  const season = seasons.find((item) => String(item.season_id) === seasonId) ?? seasons[0]
  const episodes = season?.episodes ?? []
  const firstEpisode = episodes[0]
  const genre = show.genres?.map((item) => item.name).filter(Boolean).join(', ') || 'Not specified'
  const year = String(show.release_year ?? show.release_date ?? '').slice(0, 4) || 'Not specified'
  const totalEpisodes = seasons.reduce((total, item) => total + (item.episodes?.length ?? 0), 0)
  const description = textOnly(show.description)
  const coverImage = show.thumbnail_image ?? show.poster_tv_image ?? show.poster_image

  return <>
    <section className="relative isolate h-[clamp(400px,46svh,540px)] overflow-hidden border-b border-white/10">
      {coverImage ? <img src={coverImage} alt="" className="absolute inset-0 z-0 h-full w-full object-cover object-center" /> : null}
      <div className="absolute inset-0 z-10 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,.94)_28%,rgba(5,5,5,.45)_56%,rgba(5,5,5,.18)_100%),linear-gradient(0deg,#050505_0%,transparent_65%)]" />
      <div className="relative z-20 mx-auto flex h-full max-w-[1800px] flex-col justify-end px-4 pb-7 pt-16 sm:px-8 sm:pb-8 lg:px-12">
        <a href="/tv-shows" className="absolute top-6 z-30 inline-flex items-center gap-2 text-sm font-medium text-white/75 hover:text-white"><ArrowLeft className="h-4 w-4" />Back to Browse</a>
        <div className="max-w-[570px]"><h1 className="text-4xl font-black leading-none sm:text-6xl">{show.name}</h1>{description ? <p className="mt-4 line-clamp-3 text-sm leading-6 text-white/78 sm:text-base sm:leading-7">{description}</p> : null}<div className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-white/72"><span>{genre}</span><span className="text-white/25">|</span><span>{year}</span><span className="text-white/25">|</span><span>{seasons.length} {seasons.length === 1 ? 'Season' : 'Seasons'}</span><span className="text-white/25">|</span><span>{totalEpisodes} {totalEpisodes === 1 ? 'Episode' : 'Episodes'}</span></div><div className="mt-6 flex flex-wrap items-center gap-3">{firstEpisode ? <Button asChild className="h-11 bg-primary px-5 font-black text-black hover:bg-primary/90"><a href={episodeHref(firstEpisode)}><Play className="h-4 w-4 fill-current" />Watch Now</a></Button> : null}<span className="inline-flex items-center gap-2 rounded-md border border-primary/55 bg-black/30 px-1.5 py-0.5"><WatchlistToggleButton entertainmentId={show.id} type="tvshow" initialInWatchlist={show.is_watch_list} className="h-9 w-9 border-0 bg-transparent" /><span className="pr-2 text-sm font-bold text-white">{show.is_watch_list ? 'In Watchlist' : 'Add to Watchlist'}</span></span><Button variant="outline" className="h-11 border-white/22 bg-black/25" onClick={() => sharePage(show.name)}><Share2 className="h-4 w-4" />Share</Button></div></div>
      </div>
    </section>

    <div className="mx-auto max-w-[1800px] px-4 py-5 sm:px-8 lg:px-12">
      <nav className="flex gap-7 border-b border-white/12 text-sm font-bold"><span className="border-b-2 border-primary px-2 pb-3 text-white">Episodes</span><span className="px-2 pb-3 text-white/52">About</span><span className="px-2 pb-3 text-white/52">Cast</span></nav>
      <div className="grid gap-7 pt-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section className="min-w-0"><div className="mb-3 flex flex-wrap items-center gap-3">{seasons.length ? <SeasonSelect seasons={seasons} seasonId={String(season?.season_id ?? '')} onChange={setSeasonId} /> : null}<span className="text-sm text-white/54">{episodes.length} {episodes.length === 1 ? 'Episode' : 'Episodes'}</span></div><div className="divide-y divide-white/10 rounded-md border border-white/10 bg-white/[0.025]">{episodes.length ? episodes.map((episode, index) => <EpisodeRow key={episode.id} episode={episode} index={index} />) : <EmptyEpisodes />}</div></section>
        <aside className="space-y-4"><SeriesInformation genre={genre} year={year} seasons={seasons.length} episodes={totalEpisodes} description={description} />{firstEpisode ? <LatestEpisode episode={firstEpisode} /> : null}{show.more_items?.length ? <RelatedShows shows={show.more_items} /> : null}</aside>
      </div>
      {(show.casts?.length || show.directors?.length) ? <CastCrew casts={show.casts} directors={show.directors} /> : null}
    </div>
  </>
}

function SeasonSelect({ seasons, seasonId, onChange }: { seasons: SeasonItem[]; seasonId: string; onChange: (id: string) => void }) { return <label className="relative"><select value={seasonId} onChange={(event) => onChange(event.target.value)} className="h-10 min-w-36 appearance-none rounded-md border border-white/20 bg-[#141414] px-3 pr-9 text-sm font-bold outline-none focus:border-primary">{seasons.map((season) => <option key={season.season_id} value={season.season_id}>{season.name}</option>)}</select><ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-white/60" /></label> }

function EpisodeRow({ episode, index }: { episode: EpisodeItem; index: number }) {
  const featured = index === 0
  const classes = ['grid grid-cols-[28px_102px_minmax(0,1fr)] items-center gap-3 p-3 sm:grid-cols-[34px_128px_minmax(0,1fr)_96px]', featured ? 'bg-primary/[0.055] ring-1 ring-inset ring-primary/80' : ''].join(' ')
  return <article className={classes}><span className="text-center text-lg font-black text-white/75">{episode.episode_number ?? index + 1}</span><a href={episodeHref(episode)} className="relative aspect-video overflow-hidden rounded-sm bg-white/5"><MediaThumbnail src={episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image} alt={episode.name} className="h-full w-full !aspect-auto" imageClassName="object-cover" />{episode.duration ? <span className="absolute bottom-1 right-1 rounded-sm bg-black/85 px-1.5 py-0.5 text-[10px] font-bold">{episode.duration}</span> : null}</a><div className="min-w-0"><h2 className="line-clamp-1 font-bold">{episode.name}</h2><p className="mt-1 text-xs text-white/50">{episode.release_date ? formatDate(episode.release_date) : 'Episode'}</p>{episode.short_desc || episode.description ? <p className="mt-1 line-clamp-2 text-xs leading-5 text-white/61">{textOnly(episode.short_desc ?? episode.description ?? '')}</p> : null}</div><Button asChild variant={featured ? 'default' : 'outline'} className={['col-start-3 mt-2 h-9 justify-self-start sm:col-start-4 sm:row-start-1 sm:mt-0 sm:justify-self-end', featured ? 'bg-primary text-black hover:bg-primary/90' : 'border-white/25 bg-transparent'].join(' ')}><a href={episodeHref(episode)}><Play className="h-3.5 w-3.5 fill-current" />Watch</a></Button></article>
}

function SeriesInformation({ genre, year, seasons, episodes, description }: { genre: string; year: string; seasons: number; episodes: number; description: string }) { return <section className="rounded-md border border-white/12 bg-white/[0.035] p-4 sm:p-5"><h2 className="text-lg font-black">Series Information</h2><dl className="mt-4 space-y-3"><InfoRow icon={Film} label="Genre" value={genre} /><InfoRow icon={CalendarDays} label="Seasons" value={String(seasons)} /><InfoRow icon={Play} label="Episodes" value={String(episodes)} /><InfoRow icon={Clock3} label="First aired" value={year} /></dl>{description ? <p className="mt-4 border-t border-white/10 pt-4 text-sm leading-6 text-white/62">{description}</p> : null}</section> }
function InfoRow({ icon: Icon, label, value }: { icon: typeof Film; label: string; value: string }) { return <div className="grid grid-cols-[22px_92px_1fr] items-center gap-2 text-sm"><Icon className="h-4 w-4 text-primary" /><dt className="text-white/50">{label}</dt><dd className="font-medium">{value}</dd></div> }
function LatestEpisode({ episode }: { episode: EpisodeItem }) { return <section className="rounded-md border border-white/12 bg-white/[0.035] p-4"><h2 className="font-black">Latest Episode</h2><a href={episodeHref(episode)} className="mt-3 flex items-center gap-3"><MediaThumbnail src={episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image} alt={episode.name} className="aspect-video w-28 shrink-0 overflow-hidden rounded-sm !aspect-auto" imageClassName="object-cover" /><div className="min-w-0"><h3 className="line-clamp-1 text-sm font-bold">{episode.name}</h3><p className="mt-1 text-xs text-white/50">{episode.duration ?? 'Watch episode'}</p></div><Play className="ml-auto h-8 w-8 shrink-0 rounded-full border border-primary p-2 text-primary" /></a></section> }

function CastCrew({ casts, directors }: { casts?: Person[]; directors?: Person[] }) { const people = [...(casts ?? []).map((person) => ({ ...person, role: person.designation || 'Cast' })), ...(directors ?? []).map((person) => ({ ...person, role: person.designation || 'Director' }))]; return <section className="mt-8 border-t border-white/10 pt-6"><div className="mb-4 flex items-center justify-between"><h2 className="text-2xl font-black">Cast &amp; Crew</h2><span className="text-xs font-bold text-primary">View All</span></div><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">{people.map((person, index) => <article key={String(person.id ?? person.name) + '-' + index} className="flex min-w-0 items-center gap-3 rounded-md border border-white/10 bg-white/[0.035] p-3"><PersonAvatar person={person} /><div className="min-w-0"><h3 className="line-clamp-1 font-bold">{person.name || 'Crew member'}</h3><p className="mt-1 text-xs text-white/52">{person.role}</p><span className="mt-3 block h-0.5 w-7 bg-primary" /></div></article>)}</div></section> }
function PersonAvatar({ person }: { person: Person }) { return person.profile_image && !person.profile_image.includes('default-image') ? <img src={person.profile_image} alt="" className="h-14 w-14 shrink-0 rounded-full object-cover grayscale" loading="lazy" /> : <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-lg font-black text-primary">{person.name?.trim().charAt(0) || 'E'}</span> }
function RelatedShows({ shows }: { shows: MediaItem[] }) { return <section><h2 className="mb-3 text-lg font-black">You May Also Like</h2><div className="space-y-3">{shows.slice(0, 3).map((show) => <a key={show.id} href={'/tvshow-details/' + (show.slug ?? show.id)} className="flex gap-3"><MediaThumbnail src={show.poster_tv_image ?? show.poster_image} alt={show.name} className="aspect-video w-28 shrink-0 overflow-hidden rounded-sm !aspect-auto" imageClassName="object-cover" /><span className="self-center text-sm font-bold">{show.name}</span></a>)}</div></section> }
function EmptyEpisodes() { return <div className="px-4 py-12 text-center"><Plus className="mx-auto h-5 w-5 text-primary" /><p className="mt-3 font-bold">Episodes are coming soon.</p><p className="mt-1 text-sm text-white/52">This series is available now. Check back when episodes are published.</p></div> }
function episodeHref(episode: EpisodeItem) { return '/episode-details/' + episode.id + '?autoplay=1' }
function textOnly(value?: string | null) { const text = String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim(); return /^https?:\/\/\S+$/i.test(text) ? '' : text }
function formatDate(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }
async function sharePage(title: string) { if (navigator.share) await navigator.share({ title, url: window.location.href }); else await navigator.clipboard?.writeText(window.location.href) }
