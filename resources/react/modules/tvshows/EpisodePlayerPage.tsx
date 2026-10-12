import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calendar, ChevronDown, Clock, ListVideo, Play, Share2, UsersRound } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { PlayerBackButton } from '@/components/PlayerBackButton'
import { Button } from '@/components/ui/button'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'
import { loadEpisodeDetail, type EpisodeItem, type SeasonItem } from './tvshowsApi'

export function EpisodePlayerPage() {
  const id = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const autoplay = new URLSearchParams(window.location.search).get('autoplay') === '1'
  const query = useQuery({ queryKey: ['episode-detail', id], queryFn: () => loadEpisodeDetail(id), enabled: Boolean(id) })
  return <main className="min-h-screen bg-[#050505] text-white"><AppHeader active="tvshows" />{query.isLoading ? <div className="h-[70svh] animate-pulse bg-white/[0.04]" /> : query.isError || !query.data ? <div className="flex min-h-[60svh] items-center justify-center text-white/55">This episode could not be loaded.</div> : <EpisodeContent episode={query.data} autoplay={autoplay} />}</main>
}

function EpisodeContent({ episode, autoplay }: { episode: EpisodeItem & { tvShowLinks?: SeasonItem[] }; autoplay: boolean }) {
  const seasons = episode.tvShowLinks ?? []
  const [seasonId, setSeasonId] = useState(String(episode.season_id ?? seasons[0]?.season_id ?? ''))
  const activeSeason = seasons.find((item) => String(item.season_id) === seasonId) ?? seasons[0]
  const playlist = activeSeason?.episodes ?? []
  const source = episode.bunny_video_url ?? episode.video_url_input ?? episode.video_url ?? episode.video_links?.find((link) => link.url)?.url ?? null
  const poster = episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image
  const subtitles = useMemo(() => (episode.subtitle_info ?? []).filter((item) => item.subtitle_file).map((item, index) => ({ src: item.subtitle_file as string, label: item.language || item.language_code || 'Subtitles', language: item.language_code, default: item.is_default === true || item.is_default === 1 || item.is_default === '1' || index === 0 })), [episode.subtitle_info])

  return <>
    <div className="border-b border-white/10 px-3 py-3"><PlayerBackButton fallbackHref={episode.entertainment_id ? `/tvshow-details/${episode.entertainment_id}` : '/tv-shows'} /></div>
    <section className="grid border-b border-white/10 bg-black xl:grid-cols-[minmax(0,2.15fr)_520px]">
      <div className="relative aspect-video min-h-0 overflow-hidden bg-black xl:aspect-auto xl:h-[min(62svh,650px)]">{source ? <VideoJsPlayer source={source} poster={poster} autoplay={autoplay} muted={autoplay} vastAds={[]} subtitles={subtitles} /> : <div className="flex h-full items-center justify-center text-white/50">No playable episode source was returned.</div>}</div>
      <aside className="flex max-h-[540px] min-h-0 flex-col border-t border-white/12 bg-[#0b0b0b] xl:max-h-[min(62svh,650px)] xl:border-l xl:border-t-0">
        <div className="border-b border-white/10 p-4"><div className="flex items-center gap-3"><MediaThumbnail src={activeSeason?.poster_tv_image ?? activeSeason?.poster_image ?? poster} alt={episode.tvshow_name ?? 'TV show'} className="aspect-video w-32 shrink-0 overflow-hidden rounded-sm" imageClassName="object-cover" /><div><h2 className="text-xl font-black">{episode.tvshow_name ?? 'Episodes'}</h2><p className="mt-1 text-sm text-white/50">{seasons.length} Seasons</p></div></div><label className="relative mt-4 block"><select value={String(activeSeason?.season_id ?? '')} onChange={(event) => setSeasonId(event.target.value)} className="h-10 w-full appearance-none rounded border border-white/20 bg-[#141414] px-3 pr-9 text-sm font-bold outline-none focus:border-primary">{seasons.map((season) => <option key={season.season_id} value={season.season_id}>{season.name}</option>)}</select><ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-white/60" /></label></div>
        <div className="overflow-y-auto">{playlist.map((item, index) => <PlaylistRow key={item.id} item={item} index={index} active={String(item.id) === String(episode.id)} />)}</div>
      </aside>
    </section>
    <section className="mx-auto max-w-[1800px] px-4 py-6 sm:px-8 lg:px-12"><div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"><div className="max-w-5xl"><h1 className="text-3xl font-black sm:text-4xl">{episode.name}</h1><div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-white/55">{episode.tvshow_name && <span className="inline-flex items-center gap-1.5"><ListVideo className="h-4 w-4 text-primary" />{episode.tvshow_name}</span>}{episode.release_date && <span className="inline-flex items-center gap-1.5"><Calendar className="h-4 w-4 text-primary" />{formatDate(episode.release_date)}</span>}{episode.episode_number && <span>Episode {episode.episode_number}</span>}{episode.duration && <span className="inline-flex items-center gap-1.5"><Clock className="h-4 w-4 text-primary" />{episode.duration}</span>}</div>{episode.description || episode.short_desc ? <p className="mt-4 text-sm leading-7 text-white/68">{stripHtml(episode.description ?? episode.short_desc ?? '')}</p> : null}</div><div className="flex shrink-0 flex-wrap gap-2"><Button variant="outline" className="border-white/20" onClick={() => sharePage(episode.name)}><Share2 className="h-4 w-4" />Share</Button><Button asChild variant="outline" className="border-primary/45 text-primary"><a href="https://ezwayconnect.com" target="_blank" rel="noreferrer"><UsersRound className="h-4 w-4" />Connect</a></Button></div></div></section>
    {playlist.length > 1 ? <MoreEpisodes episodes={playlist.filter((item) => String(item.id) !== String(episode.id))} /> : null}
  </>
}

function PlaylistRow({ item, index, active }: { item: EpisodeItem; index: number; active: boolean }) { return <a href={`/episode-details/${item.id}?autoplay=1`} className={`grid grid-cols-[24px_112px_minmax(0,1fr)] items-center gap-3 border-b border-white/8 p-3 transition hover:bg-white/[0.06] ${active ? 'bg-primary/[0.09] ring-1 ring-inset ring-primary/70' : ''}`}><span className={`text-center font-bold ${active ? 'text-primary' : 'text-white/70'}`}>{active ? <Play className="mx-auto h-4 w-4 fill-current" /> : item.episode_number ?? index + 1}</span><MediaThumbnail src={item.poster_tv_image ?? item.thumbnail_image ?? item.poster_image} alt={item.name} className="aspect-video overflow-hidden rounded-sm" imageClassName="object-cover" /><div className="min-w-0"><h3 className="line-clamp-2 text-sm font-bold">{item.episode_number ? `${item.episode_number}. ` : ''}{item.name}</h3><div className="mt-1 flex justify-between text-xs text-white/45"><span>{item.release_date ? formatDate(item.release_date) : ''}</span><span>{item.duration}</span></div></div></a> }
function MoreEpisodes({ episodes }: { episodes: EpisodeItem[] }) { return <section className="mx-auto max-w-[1800px] px-4 pb-10 sm:px-8 lg:px-12"><h2 className="mb-4 border-t border-white/10 pt-5 text-xl font-black">More from this series</h2><div className="grid grid-flow-col auto-cols-[78%] gap-3 overflow-x-auto pb-4 [scrollbar-width:none] sm:auto-cols-[38%] lg:auto-cols-[23%] xl:auto-cols-[18%]">{episodes.map((item) => <a key={item.id} href={`/episode-details/${item.id}?autoplay=1`}><div className="relative aspect-video overflow-hidden rounded-sm border border-white/10"><MediaThumbnail src={item.poster_tv_image ?? item.thumbnail_image ?? item.poster_image} alt={item.name} className="h-full w-full" imageClassName="object-cover" />{item.duration && <span className="absolute bottom-2 right-2 rounded-sm bg-black/85 px-1.5 py-0.5 text-xs font-bold">{item.duration}</span>}</div><h3 className="mt-2 line-clamp-1 text-sm font-bold">{item.name}</h3><p className="mt-1 text-xs text-white/45">Episode {item.episode_number ?? '-'}</p></a>)}</div></section> }
function stripHtml(value: string) { return value.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/s+/g, ' ').trim() }
function formatDate(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }
async function sharePage(title: string) { if (navigator.share) await navigator.share({ title, url: window.location.href }); else await navigator.clipboard?.writeText(window.location.href) }
