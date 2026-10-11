import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calendar, ChevronRight, Clock, Play, Star, Tv, UsersRound } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { MediaItem } from '@/modules/home/types'
import { loadTvShowDetail, type EpisodeItem, type SeasonItem } from './tvshowsApi'

export function TvShowDetailPage() {
  const showId = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const showQuery = useQuery({ queryKey: ['tv-show-detail', showId], queryFn: () => loadTvShowDetail(showId), enabled: Boolean(showId) })
  const show = showQuery.data

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="tvshows" />
      {showQuery.isLoading ? <div className="h-[70svh] animate-pulse bg-white/[0.04]" /> : showQuery.isError || !show ? (
        <div className="flex min-h-[60svh] items-center justify-center px-4 text-center text-white/55">This TV show could not be loaded.</div>
      ) : <ShowContent show={show} />}
    </main>
  )
}

function ShowContent({ show }: { show: NonNullable<Awaited<ReturnType<typeof loadTvShowDetail>>> }) {
  const seasons = show.tvShowLinks ?? []
  const [activeSeasonId, setActiveSeasonId] = useState<string>(() => String(seasons[0]?.season_id ?? ''))
  const activeSeason = seasons.find((season) => String(season.season_id) === activeSeasonId) ?? seasons[0]
  const backdrop = show.thumbnail_image ?? show.poster_tv_image ?? show.poster_image
  const castNames = names(show.casts)
  const directorNames = names(show.directors)

  return (
    <>
      <section className="relative min-h-[560px] overflow-hidden border-b border-white/10 sm:min-h-[620px]">
        <MediaThumbnail src={backdrop} alt={show.name} className="absolute inset-0 h-full w-full" imageClassName="object-cover" />
        <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(5,5,5,.98)_0%,rgba(5,5,5,.82)_38%,rgba(5,5,5,.25)_75%),linear-gradient(0deg,#050505_0%,transparent_45%)]" />
        <div className="relative mx-auto flex min-h-[560px] max-w-[1800px] items-end px-4 pb-12 pt-24 sm:min-h-[620px] sm:px-8 lg:px-12">
          <div className="flex max-w-4xl items-end gap-5 sm:gap-8">
            <MediaThumbnail src={show.poster_image ?? show.poster_url} alt={show.name} className="hidden aspect-[2/3] w-44 shrink-0 overflow-hidden rounded-md border border-white/15 shadow-2xl sm:block lg:w-52" imageClassName="object-cover" />
            <div className="min-w-0 pb-1">
              <Badge className="mb-4 rounded-sm bg-primary font-black text-black"><Tv className="mr-1.5 h-4 w-4" />TV Series</Badge>
              <h1 className="text-4xl font-black leading-none sm:text-6xl lg:text-7xl">{show.name}</h1>
              <div className="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/68">
                {show.release_date ? <span className="inline-flex items-center gap-1.5"><Calendar className="h-4 w-4 text-primary" />{String(show.release_date).slice(0, 4)}</span> : null}
                {show.imdb_rating ? <span className="inline-flex items-center gap-1.5"><Star className="h-4 w-4 fill-primary text-primary" />{show.imdb_rating}</span> : null}
                <span>{seasons.length} {seasons.length === 1 ? 'season' : 'seasons'}</span>
                {show.language ? <span>{show.language}</span> : null}
              </div>
              {show.description ? <p className="mt-5 line-clamp-4 max-w-3xl text-sm leading-7 text-white/70 sm:text-base">{stripHtml(show.description)}</p> : null}
              <div className="mt-6 flex flex-wrap gap-3">
                {activeSeason?.episodes?.[0] ? <Button asChild className="bg-primary font-black text-black hover:bg-primary/90"><a href={`/episode-details/${activeSeason.episodes[0].id}?autoplay=1`}><Play className="h-4 w-4 fill-current" />Watch now</a></Button> : null}
                <WatchlistToggleButton entertainmentId={show.id} type="tvshow" initialInWatchlist={show.is_watch_list} />
                <Button asChild variant="secondary"><a href="https://ezwayconnect.com" target="_blank" rel="noreferrer"><UsersRound className="h-4 w-4" />Connect</a></Button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-[1800px] px-4 py-10 sm:px-8 lg:px-12">
        <section>
          <div className="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-4">
            <h2 className="text-2xl font-black">Episodes</h2>
            {seasons.length > 1 ? <div className="flex max-w-full gap-2 overflow-x-auto pb-1 [scrollbar-width:none]">{seasons.map((season) => <button key={season.season_id} type="button" onClick={() => setActiveSeasonId(String(season.season_id))} className={`h-9 shrink-0 rounded-md px-4 text-sm font-bold transition ${String(season.season_id) === String(activeSeason?.season_id) ? 'bg-primary text-black' : 'bg-white/8 text-white/65 hover:bg-white/14'}`}>{season.name}</button>)}</div> : null}
          </div>
          {activeSeason ? <SeasonEpisodes season={activeSeason} /> : <div className="rounded-md border border-white/10 p-8 text-center text-white/50">No published episodes yet.</div>}
        </section>

        {(castNames || directorNames) ? <section className="mt-10 border-t border-white/10 pt-7"><h2 className="mb-4 text-xl font-black">Cast &amp; Crew</h2><dl className="grid gap-3 text-sm sm:grid-cols-2">{castNames ? <div><dt className="font-bold text-white">Cast</dt><dd className="mt-1 text-white/62">{castNames}</dd></div> : null}{directorNames ? <div><dt className="font-bold text-white">Directors</dt><dd className="mt-1 text-white/62">{directorNames}</dd></div> : null}</dl></section> : null}
        {show.more_items?.length ? <MoreShows shows={show.more_items} /> : null}
      </div>
    </>
  )
}

function SeasonEpisodes({ season }: { season: SeasonItem }) {
  const episodes = season.episodes ?? []
  return episodes.length ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{episodes.map((episode, index) => <EpisodeCard key={episode.id} episode={episode} fallbackNumber={index + 1} />)}</div> : <div className="rounded-md border border-white/10 p-8 text-center text-white/50">No published episodes in this season.</div>
}

function EpisodeCard({ episode, fallbackNumber }: { episode: EpisodeItem; fallbackNumber: number }) {
  return <a href={`/episode-details/${episode.id}?autoplay=1`} className="group min-w-0"><div className="relative aspect-video overflow-hidden rounded-md border border-white/10 bg-[#111]"><MediaThumbnail src={episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image} alt={episode.name} className="h-full w-full" imageClassName="object-cover" /><div className="absolute inset-0 bg-gradient-to-t from-black/75 via-transparent to-transparent" /><span className="absolute left-3 top-3 rounded-sm bg-black/75 px-2 py-1 text-[10px] font-black uppercase">Episode {episode.episode_number ?? fallbackNumber}</span><span className="absolute bottom-3 right-3 flex h-10 w-10 items-center justify-center rounded-full bg-primary text-black transition group-hover:scale-110"><Play className="h-4 w-4 fill-current" /></span></div><h3 className="mt-2 line-clamp-1 font-bold">{episode.name}</h3><div className="mt-1 flex items-center gap-3 text-xs text-white/48">{episode.duration ? <span className="inline-flex items-center gap-1"><Clock className="h-3.5 w-3.5" />{episode.duration}</span> : null}{episode.release_date ? <span>{String(episode.release_date).slice(0, 4)}</span> : null}</div></a>
}

function MoreShows({ shows }: { shows: MediaItem[] }) {
  return <section className="mt-12"><h2 className="mb-4 border-b border-white/10 pb-3 text-xl font-black">More like this</h2><div className="grid grid-flow-col auto-cols-[42%] gap-3 overflow-x-auto pb-4 [scrollbar-width:none] sm:auto-cols-[22%] lg:auto-cols-[15%]">{shows.map((show) => <a key={show.id} href={`/tvshow-details/${show.id}`} className="group"><div className="aspect-[2/3] overflow-hidden rounded-md border border-white/10"><MediaThumbnail src={show.poster_image ?? show.poster_url} alt={show.name} className="h-full w-full" imageClassName="object-cover" /></div><h3 className="mt-2 line-clamp-2 text-sm font-bold">{show.name}</h3><span className="mt-1 inline-flex items-center text-xs text-white/45">View series<ChevronRight className="h-3.5 w-3.5" /></span></a>)}</div></section>
}

function names(people?: Array<{ name?: string | null }>) { return people?.map((person) => person.name?.trim()).filter(Boolean).join(', ') ?? '' }
function stripHtml(value: string) { return value.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim() }
