import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calendar, Clock, ListVideo, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { PlayerBackButton } from '@/components/PlayerBackButton'
import { Badge } from '@/components/ui/badge'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'
import { loadEpisodeDetail, type EpisodeItem } from './tvshowsApi'

export function EpisodePlayerPage() {
  const episodeId = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const autoplay = new URLSearchParams(window.location.search).get('autoplay') === '1'
  const episodeQuery = useQuery({ queryKey: ['episode-detail', episodeId], queryFn: () => loadEpisodeDetail(episodeId), enabled: Boolean(episodeId) })
  const episode = episodeQuery.data

  return <main className="min-h-screen bg-[#050505] text-white"><AppHeader active="tvshows" /><section className="bg-black"><div className="flex min-h-12 items-center border-b border-white/10 bg-[#050505] px-3 py-2 sm:min-h-14 sm:px-5"><PlayerBackButton fallbackHref={episode?.entertainment_id ? `/tvshow-details/${episode.entertainment_id}` : '/tv-shows'} /></div>{episodeQuery.isLoading ? <div className="aspect-video w-full animate-pulse bg-white/[0.05] sm:h-[76svh]" /> : episodeQuery.isError || !episode ? <div className="flex min-h-[55svh] items-center justify-center p-8 text-white/55">This episode could not be loaded.</div> : <EpisodeContent episode={episode} autoplay={autoplay} />}</section></main>
}

function EpisodeContent({ episode, autoplay }: { episode: EpisodeItem & { tvShowLinks?: import('./tvshowsApi').SeasonItem[] }; autoplay: boolean }) {
  const source = episode.bunny_video_url ?? episode.video_url_input ?? episode.video_url ?? episode.video_links?.find((link) => link.url)?.url ?? null
  const poster = episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image
  const subtitles = useMemo(() => (episode.subtitle_info ?? []).filter((item) => item.subtitle_file).map((item, index) => ({ src: item.subtitle_file as string, label: item.language || item.language_code || 'Subtitles', language: item.language_code, default: item.is_default === true || item.is_default === 1 || item.is_default === '1' || index === 0 })), [episode.subtitle_info])
  const otherEpisodes = (episode.tvShowLinks ?? []).flatMap((season) => season.episodes ?? []).filter((item) => String(item.id) !== String(episode.id))

  return <><div className="relative aspect-video w-full overflow-hidden bg-black sm:aspect-auto sm:h-[76svh] sm:min-h-[430px]">{source ? <VideoJsPlayer source={source} poster={poster} autoplay={autoplay} muted={autoplay} vastAds={[]} subtitles={subtitles} /> : <div className="flex h-full items-center justify-center text-white/55">No playable episode source was returned.</div>}</div><div className="border-t border-white/10 bg-[#050505] px-4 py-5 sm:px-8"><div className="mx-auto flex max-w-[1800px] gap-4"><MediaThumbnail src={episode.poster_image} alt={episode.name} className="hidden h-24 w-16 shrink-0 overflow-hidden rounded-sm sm:block" imageClassName="object-cover" /><div className="min-w-0"><div className="mb-2 flex flex-wrap gap-2"><Badge className="rounded-sm bg-primary text-black"><Tv className="mr-1 h-3.5 w-3.5" />Episode</Badge>{episode.episode_number ? <Badge className="rounded-sm bg-white/12 text-white">Episode {episode.episode_number}</Badge> : null}</div><h1 className="text-2xl font-black sm:text-3xl">{episode.name}</h1><div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/55">{episode.tvshow_name ? <span className="inline-flex items-center gap-1.5"><ListVideo className="h-4 w-4 text-primary" />{episode.tvshow_name}</span> : null}{episode.release_date ? <span className="inline-flex items-center gap-1.5"><Calendar className="h-4 w-4 text-primary" />{String(episode.release_date).slice(0, 4)}</span> : null}{episode.duration ? <span className="inline-flex items-center gap-1.5"><Clock className="h-4 w-4 text-primary" />{episode.duration}</span> : null}</div></div></div></div><div className="mx-auto max-w-[1800px] px-4 py-8 sm:px-8">{episode.description || episode.short_desc ? <section className="rounded-md border border-white/10 bg-white/[0.035] p-5"><h2 className="mb-3 text-lg font-black">About this episode</h2><p className="max-w-5xl text-sm leading-7 text-white/68">{stripHtml(episode.description ?? episode.short_desc ?? '')}</p></section> : null}{otherEpisodes.length ? <section className="mt-8"><h2 className="mb-4 border-b border-white/10 pb-3 text-xl font-black">More episodes</h2><div className="grid grid-flow-col auto-cols-[80%] gap-3 overflow-x-auto pb-4 [scrollbar-width:none] sm:auto-cols-[38%] lg:auto-cols-[24%]">{otherEpisodes.map((item) => <a key={item.id} href={`/episode-details/${item.id}?autoplay=1`}><div className="aspect-video overflow-hidden rounded-md border border-white/10"><MediaThumbnail src={item.poster_tv_image ?? item.poster_image} alt={item.name} className="h-full w-full" imageClassName="object-cover" /></div><h3 className="mt-2 line-clamp-1 text-sm font-bold">{item.name}</h3></a>)}</div></section> : null}</div></>
}

function stripHtml(value: string) { return value.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim() }
