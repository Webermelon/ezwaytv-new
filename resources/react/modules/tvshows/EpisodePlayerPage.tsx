import { useEffect, useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calendar, Check, ChevronDown, Clock, Copy, ListVideo, MessageSquareText, Play, Send, Share2, UsersRound, X } from 'lucide-react'

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
  return <main className="min-h-screen bg-[#050505] text-white"><AppHeader active="tvshows" />{query.isLoading ? <div className="h-[56svh] animate-pulse bg-white/[0.04]" /> : query.isError || !query.data ? <div className="flex min-h-[60svh] items-center justify-center text-white/55">This episode could not be loaded.</div> : <EpisodeContent episode={query.data} autoplay={autoplay} />}</main>
}

function EpisodeContent({ episode, autoplay }: { episode: EpisodeItem & { tvShowLinks?: SeasonItem[] }; autoplay: boolean }) {
  const seasons = episode.tvShowLinks ?? []
  const [seasonId, setSeasonId] = useState(String(episode.season_id ?? seasons[0]?.season_id ?? ''))
  const activeSeason = seasons.find((item) => String(item.season_id) === seasonId) ?? seasons[0]
  const playlist = activeSeason?.episodes ?? []
  const parentShow = episode.more_items?.find((item) => String(item.id) === String(episode.entertainment_id)) ?? episode.more_items?.[0]
  const showId = parentShow?.slug ?? episode.entertainment_id ?? 'tv-shows'
  const showDescription = stripHtml(parentShow?.description ?? '')
  const source = episode.bunny_video_url ?? episode.video_url_input ?? episode.video_url ?? episode.video_links?.find((link) => link.url)?.url ?? null
  const poster = episode.poster_tv_image ?? episode.thumbnail_image ?? episode.poster_image
  const playerRef = useRef<HTMLDivElement>(null)
  const playlistRef = useRef<HTMLDivElement>(null)
  const activeEpisodeRef = useRef<HTMLAnchorElement>(null)
  const [playerHeight, setPlayerHeight] = useState<number>()
  const [copiedShareUrl, setCopiedShareUrl] = useState(false)
  const subtitles = useMemo(() => (episode.subtitle_info ?? []).filter((item) => item.subtitle_file).map((item, index) => ({ src: item.subtitle_file as string, label: item.language || item.language_code || 'Subtitles', language: item.language_code, default: item.is_default === true || item.is_default === 1 || item.is_default === '1' || index === 0 })), [episode.subtitle_info])

  useEffect(() => {
    const player = playerRef.current
    if (!player) return
    const syncHeight = () => setPlayerHeight(window.matchMedia('(min-width: 1280px)').matches ? player.getBoundingClientRect().height : undefined)
    const observer = new ResizeObserver(syncHeight)
    observer.observe(player)
    window.addEventListener('resize', syncHeight)
    syncHeight()
    return () => { observer.disconnect(); window.removeEventListener('resize', syncHeight) }
  }, [])

  useEffect(() => {
    const playlistElement = playlistRef.current
    const activeElement = activeEpisodeRef.current
    if (!playlistElement || !activeElement) return
    const centerActiveEpisode = () => {
      playlistElement.scrollTop = Math.max(0, activeElement.offsetTop - (playlistElement.clientHeight - activeElement.offsetHeight) / 2)
    }
    const frame = requestAnimationFrame(() => requestAnimationFrame(centerActiveEpisode))
    return () => cancelAnimationFrame(frame)
  }, [episode.id, seasonId, playlist.length, playerHeight])

  return <>
    <div className="mx-auto max-w-[1800px] px-4 pb-3 pt-4 sm:px-8 lg:px-12"><PlayerBackButton fallbackHref={episode.entertainment_id ? `/tv-shows/${showId}` : '/tv-shows'} /></div>
    <section className="mx-auto grid max-w-[1800px] gap-4 px-4 pb-5 sm:px-8 xl:grid-cols-[minmax(0,1fr)_360px] lg:px-12">
      <div ref={playerRef} className="relative aspect-video min-h-0 overflow-hidden rounded-md border border-white/14 bg-black shadow-2xl shadow-black/40">{source ? <VideoJsPlayer source={source} poster={poster} autoplay={autoplay} muted={autoplay} vastAds={[]} subtitles={subtitles} /> : <div className="flex h-full items-center justify-center text-white/50">No playable episode source was returned.</div>}</div>
      <aside style={playerHeight ? { height: playerHeight } : undefined} className="flex max-h-[540px] min-h-0 flex-col overflow-hidden rounded-md border border-white/12 bg-[#101010] xl:max-h-none">
        <div className="border-b border-white/10 p-3.5"><div className="flex items-center gap-3"><MediaThumbnail src={activeSeason?.poster_tv_image ?? activeSeason?.poster_image ?? poster} alt={episode.tvshow_name ?? 'TV show'} className="aspect-video w-24 shrink-0 overflow-hidden rounded-sm !aspect-auto" imageClassName="object-cover" /><div className="min-w-0"><h2 className="line-clamp-1 text-lg font-black">{episode.tvshow_name ?? 'Episodes'}</h2><p className="mt-1 text-xs text-white/55">{seasons.length} {seasons.length === 1 ? 'Season' : 'Seasons'} · {playlist.length} Episodes</p>{showDescription ? <p className="mt-2 line-clamp-3 text-xs leading-5 text-white/62">{showDescription}</p> : null}</div></div><label className="relative mt-3 block"><select value={String(activeSeason?.season_id ?? '')} onChange={(event) => setSeasonId(event.target.value)} className="h-10 w-full appearance-none rounded border border-white/20 bg-[#141414] px-3 pr-9 text-sm font-bold outline-none focus:border-primary">{seasons.map((season) => <option key={season.season_id} value={season.season_id}>{season.name}</option>)}</select><ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-white/60" /></label></div>
        <div ref={playlistRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain">{playlist.map((item, index) => <PlaylistRow key={item.id} item={item} showId={showId} index={index} active={String(item.id) === String(episode.id)} activeRef={String(item.id) === String(episode.id) ? activeEpisodeRef : undefined} />)}</div>
      </aside>
    </section>
    <section className="mx-auto max-w-[1800px] px-4 pb-5 pt-1 sm:px-8 lg:px-12"><div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"><div className="max-w-5xl"><h1 className="text-3xl font-black sm:text-4xl">{episode.name}</h1><div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-white/55">{episode.tvshow_name && <span className="inline-flex items-center gap-1.5"><ListVideo className="h-4 w-4 text-primary" />{episode.tvshow_name}</span>}{episode.release_date && <span className="inline-flex items-center gap-1.5"><Calendar className="h-4 w-4 text-primary" />{formatDate(episode.release_date)}</span>}{episode.episode_number && <span>Season {activeSeason?.name ?? '1'} · Episode {episode.episode_number}</span>}{episode.duration && <span className="inline-flex items-center gap-1.5"><Clock className="h-4 w-4 text-primary" />{episode.duration}</span>}</div>{episode.description || episode.short_desc ? <p className="mt-4 text-sm leading-7 text-white/68">{stripHtml(episode.description ?? episode.short_desc ?? '')}</p> : null}</div><div className="flex shrink-0 flex-wrap gap-2"><EpisodeShareMenu title={episode.name} copied={copiedShareUrl} onCopy={() => { copyText(window.location.href).then(() => { setCopiedShareUrl(true); window.setTimeout(() => setCopiedShareUrl(false), 1800) }).catch(() => undefined) }} /><Button asChild variant="outline" className="h-10 min-w-28 border-primary/45 text-primary"><a href="https://ezwayconnect.com" target="_blank" rel="noreferrer"><UsersRound className="h-4 w-4" />Connect</a></Button></div></div></section>
    {playlist.length > 1 ? <MoreEpisodes episodes={playlist.filter((item) => String(item.id) !== String(episode.id))} showId={showId} /> : null}
  </>
}

function PlaylistRow({ item, showId, index, active, activeRef }: { item: EpisodeItem; showId: string | number; index: number; active: boolean; activeRef?: React.RefObject<HTMLAnchorElement | null> }) { return <a ref={activeRef} href={episodeHref(item, showId)} className={`grid grid-cols-[24px_112px_minmax(0,1fr)] items-center gap-3 border-b border-white/8 p-3 transition hover:bg-white/[0.06] ${active ? 'bg-primary/[0.09] ring-1 ring-inset ring-primary/70' : ''}`}><span className={`text-center font-bold ${active ? 'text-primary' : 'text-white/70'}`}>{active ? <Play className="mx-auto h-4 w-4 fill-current" /> : item.episode_number ?? index + 1}</span><MediaThumbnail src={item.poster_tv_image ?? item.thumbnail_image ?? item.poster_image} alt={item.name} className="aspect-video overflow-hidden rounded-sm" imageClassName="object-cover" /><div className="min-w-0"><h3 className="line-clamp-2 text-sm font-bold">{item.episode_number ? `${item.episode_number}. ` : ''}{item.name}</h3><div className="mt-1 flex justify-between text-xs text-white/45"><span>{item.release_date ? formatDate(item.release_date) : ''}</span><span>{item.duration}</span></div></div></a> }
function EpisodeShareMenu({ title, copied, onCopy }: { title: string; copied: boolean; onCopy: () => void }) { const [open, setOpen] = useState(false); const menuRef = useRef<HTMLDivElement>(null); const shareUrl = window.location.href; const shareText = `Watch ${title} on eZWay TV`; const targets = [{ label: 'LinkedIn', symbol: 'in', href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}` }, { label: 'Facebook', symbol: 'f', href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}` }, { label: 'X', icon: X, href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}` }, { label: 'WhatsApp', icon: Send, href: `https://wa.me/?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}` }, { label: 'SMS', icon: MessageSquareText, href: `sms:?&body=${encodeURIComponent(`${shareText} ${shareUrl}`)}` }]; useEffect(() => { if (!open) return; const close = (event: PointerEvent) => { if (!menuRef.current?.contains(event.target as Node)) setOpen(false) }; document.addEventListener('pointerdown', close); return () => document.removeEventListener('pointerdown', close) }, [open]); return <div ref={menuRef} className="relative z-[90]"><Button type="button" variant="secondary" className="h-10 min-w-28 bg-white/14 text-white hover:bg-white/24" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}><Share2 className="h-4 w-4" />Share</Button><div role="menu" className={`absolute right-0 top-full z-[120] mt-3 w-[min(19rem,calc(100vw-2rem))] rounded-md border border-white/12 bg-[#111]/98 p-3 shadow-2xl shadow-black/50 backdrop-blur transition ${open ? 'visible opacity-100' : 'pointer-events-none invisible opacity-0'}`}><p className="mb-3 text-sm font-black">Share episode</p><div className="grid grid-cols-6 gap-2">{targets.map(({ label, href, icon: Icon, symbol }) => <a key={label} href={href} target={href.startsWith('http') ? '_blank' : undefined} rel={href.startsWith('http') ? 'noreferrer' : undefined} role="menuitem" onClick={() => setOpen(false)} aria-label={`Share on ${label}`} title={label} className="inline-flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:border-primary/70 hover:bg-primary/16 hover:text-white">{Icon ? <Icon className="h-[18px] w-[18px]" /> : <span className="text-base font-black leading-none">{symbol}</span>}</a>)}<button type="button" role="menuitem" onClick={() => { onCopy(); setOpen(false) }} aria-label={copied ? 'Link copied' : 'Copy link'} title={copied ? 'Copied' : 'Copy link'} className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:border-primary/70 hover:bg-primary/16 hover:text-white">{copied ? <Check className="h-[18px] w-[18px]" /> : <Copy className="h-[18px] w-[18px]" />}</button></div></div></div> }
function MoreEpisodes({ episodes, showId }: { episodes: EpisodeItem[]; showId: string | number }) { return <section className="mx-auto max-w-[1800px] px-4 pb-10 sm:px-8 lg:px-12"><h2 className="mb-4 border-t border-white/10 pt-5 text-xl font-black">More from this series</h2><div className="grid grid-flow-col auto-cols-[78%] gap-3 overflow-x-auto pb-4 [scrollbar-width:none] sm:auto-cols-[38%] lg:auto-cols-[23%] xl:auto-cols-[18%]">{episodes.map((item) => <a key={item.id} href={episodeHref(item, showId)} className="group"><div className="relative aspect-video overflow-hidden rounded-sm border border-white/10 transition group-hover:border-primary/70"><MediaThumbnail src={item.poster_tv_image ?? item.thumbnail_image ?? item.poster_image} previewSrc={episodePreviewSource(item)} alt={item.name} className="h-full w-full" imageClassName="object-cover" /><div className="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100"><span className="inline-flex items-center gap-2 rounded-sm border border-white/45 bg-black/75 px-3 py-1.5 text-xs font-bold text-white"><Play className="h-3.5 w-3.5 fill-current" />Preview</span></div>{item.duration && <span className="absolute bottom-2 right-2 rounded-sm bg-black/85 px-1.5 py-0.5 text-xs font-bold">{item.duration}</span>}</div><h3 className="mt-2 line-clamp-1 text-sm font-bold">{item.name}</h3><p className="mt-1 text-xs text-white/45">Episode {item.episode_number ?? '-'}</p></a>)}</div></section> }
function stripHtml(value: string) { return value.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim() }
function formatDate(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }
function episodePreviewSource(episode: EpisodeItem) { return episode.bunny_video_url ?? episode.video_url_input ?? episode.video_url ?? episode.video_links?.find((link) => link.url)?.url ?? undefined }
function episodeHref(episode: EpisodeItem, showId: string | number) { return `/tv-shows/${showId}/episodes/${episode.id}?autoplay=1` }
async function copyText(value: string) { if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(value); const input = document.createElement('textarea'); input.value = value; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove() }
