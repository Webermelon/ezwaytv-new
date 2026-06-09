import { useEffect, useMemo, useRef, useState } from 'react'
import { Calendar, Clock, Lock, Play, Share2, Star, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import type { MediaItem } from '@/modules/home/types'
import { PublicPage } from '@/modules/public/PublicPage'
import { VideoJsPlayer } from './VideoJsPlayer'
import { loadVideoAds, loadVideoDetail, trackVideoPlay, trackVideoView, updateWatchTime, type VideoAd } from './videoDetailApi'

type AuthorChannel = {
  id?: number | string
  name?: string
  username?: string
  avatar?: string
  avatar_image_url?: string
}

type VideoDetail = MediaItem & {
  author_channels?: AuthorChannel[]
  categories?: Array<{ id?: number | string; name?: string; slug?: string }>
  genres?: Array<{ id?: number | string; name?: string; slug?: string }>
  is_restricted?: boolean | number
  is_pay_per_view?: boolean
  is_purchased?: boolean
  price?: string | number
  discounted_price?: string | number
  release_date?: string | null
  more_items?: MediaItem[]
  video_upload_type?: string | null
  video_url_input?: string | null
  trailer_url?: string | null
  trailer_url_type?: string | null
  video_links?: Array<{
    url?: string | null
    server_url?: string | null
    quality?: string | null
    url_type?: string | null
  }> | null
  ondemand_channel_context?: {
    id?: number | string
    name?: string
    username?: string
    url?: string
  }
}

export function VideoDetailPage() {
  const slug = getSlugFromPath()
  const ondemandChannel = getQueryValue('ondemand_channel')
  const autoplay = getQueryValue('autoplay') === '1'
  const [video, setVideo] = useState<VideoDetail | null>(null)
  const [ads, setAds] = useState<{ vast: VideoAd[]; custom: VideoAd[] }>({ vast: [], custom: [] })
  const [adsLoading, setAdsLoading] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [playId, setPlayId] = useState<number | null>(null)
  const [playTrigger, setPlayTrigger] = useState(0)
  const playIdRef = useRef<number | null>(null)
  const lastWatchUpdateRef = useRef(0)

  useEffect(() => {
    let mounted = true

    setLoading(true)
    setError(null)
    loadVideoDetail(slug, ondemandChannel)
      .then((data) => {
        if (!mounted) return

        if (!data) {
          setError('Video details could not be loaded.')
          return
        }

        setVideo(data as VideoDetail)
        setAds({ vast: [], custom: [] })
        setAdsLoading(true)
        trackVideoView(data, ondemandChannel).catch(() => undefined)
        loadVideoAds(data.id)
          .then((nextAds) => {
            if (mounted) setAds(nextAds)
          })
          .catch(() => undefined)
          .finally(() => {
            if (mounted) setAdsLoading(false)
          })
      })
      .catch(() => {
        if (mounted) setError('Video details could not be loaded.')
      })
      .finally(() => {
        if (mounted) setLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [ondemandChannel, slug])

  useEffect(() => {
    playIdRef.current = playId
  }, [playId])

  const related = video?.more_items ?? []
  const isPayPerViewLocked = video?.access === 'pay-per-view' && !video.is_purchased
  const playerUrl = video ? resolvePlayerUrl(video) : null
  const channelId = video?.ondemand_channel_context?.id ?? ondemandChannel

  if (!slug) {
    return <PublicPage />
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader />

      {loading ? (
        <VideoDetailSkeleton />
      ) : error || !video ? (
        <section className="px-4 py-16 sm:px-8 lg:px-12">
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-8 text-white/68">{error ?? 'Video not found.'}</div>
        </section>
      ) : (
        <>
          <section className="relative overflow-hidden">
            {video.poster_tv_image || video.poster_image ? (
              <img src={video.poster_tv_image ?? video.poster_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-34" />
            ) : null}
            <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.92)_40%,rgba(5,5,5,0.68)_76%,#050505_100%)]" />
            <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#050505] to-transparent" />

            <div className="relative z-10 grid min-h-[72vh] items-center gap-8 px-4 py-8 sm:px-8 lg:grid-cols-[1.06fr_0.94fr] lg:px-12">
              <section className="min-w-0 py-10">
                <div className="mb-4 flex flex-wrap gap-2">
                  <Badge className="rounded-sm bg-primary text-white">{video.access ?? 'video'}</Badge>
                  {video.is_restricted ? <Badge variant="outline" className="border-white/16 text-white/76">Age restricted</Badge> : null}
                  {channelId ? <Badge variant="outline" className="border-white/16 text-white/76">On Demand</Badge> : null}
                </div>

                <h1 className="max-w-4xl text-3xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">
                  {video.name}
                </h1>

                <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-white/62">
                  {video.release_date ? (
                    <span className="inline-flex items-center gap-2">
                      <Calendar className="h-4 w-4" />
                      {new Date(video.release_date).getFullYear()}
                    </span>
                  ) : null}
                  {video.duration ? (
                    <span className="inline-flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      {video.duration}
                    </span>
                  ) : null}
                  {video.imdb_rating ? (
                    <span className="inline-flex items-center gap-2">
                      <Star className="h-4 w-4" />
                      {video.imdb_rating}
                    </span>
                  ) : null}
                </div>

                <ChannelBadges channels={video.author_channels ?? []} />

                <p className="mt-5 max-w-4xl text-sm leading-7 text-white/68 sm:text-base">
                  {stripHtml(video.description ?? video.short_desc ?? '')}
                </p>

                <div className="mt-7 flex flex-wrap gap-3">
                  {isPayPerViewLocked ? (
                    <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
                      <a href="/pay-per-view">
                        <Lock className="h-5 w-5" />
                        Rent / Buy
                      </a>
                    </Button>
                  ) : (
                    <Button
                      type="button"
                      size="lg"
                      className="bg-white text-black hover:bg-white/85"
                      onClick={() => {
                        setPlayTrigger((value) => value + 1)
                        document.querySelector<HTMLElement>('.video-detail-player')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                      }}
                    >
                      <Play className="h-5 w-5 fill-current" />
                      Watch Now
                    </Button>
                  )}
                  <Button asChild size="lg" variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
                    <a href={shareHref()}>
                      <Share2 className="h-5 w-5" />
                      Share
                    </a>
                  </Button>
                </div>
              </section>

              <aside className="video-detail-player min-w-0 self-center overflow-hidden rounded-md border border-white/10 bg-black shadow-2xl">
                {isPayPerViewLocked ? (
                  <div className="flex aspect-video flex-col items-center justify-center gap-3 bg-black p-8 text-center">
                    <Lock className="h-10 w-10 text-primary" />
                    <h2 className="text-2xl font-bold">Purchase required</h2>
                    <p className="max-w-md text-sm text-white/58">This video is protected by the existing pay-per-view access rules.</p>
                  </div>
                ) : playerUrl ? (
                  adsLoading ? (
                    <PlayerPreparing poster={video.poster_image} />
                  ) : (
                    <VideoJsPlayer
                      source={playerUrl}
                      poster={video.poster_image}
                      autoplay={autoplay}
                      playTrigger={playTrigger}
                      vastAds={ads.vast}
                      onPlay={() => {
                        if (playIdRef.current) return
                        trackVideoPlay(video, channelId).then((result) => {
                          if (result?.play_id) setPlayId(result.play_id)
                        }).catch(() => undefined)
                      }}
                      onTimeUpdate={(seconds) => {
                        if (playIdRef.current && seconds - lastWatchUpdateRef.current >= 15) {
                          lastWatchUpdateRef.current = seconds
                          updateWatchTime(playIdRef.current, seconds).catch(() => undefined)
                        }
                      }}
                    />
                  )
                ) : (
                  <div className="flex aspect-video items-center justify-center bg-black p-8 text-center text-white/56">
                    No playable source was returned for this video.
                  </div>
                )}
              </aside>
            </div>
          </section>

          <AdStrip ads={ads.custom} />

          {related.length > 0 ? (
            <section className="px-4 pb-16 sm:px-8 lg:px-12">
              <h2 className="mb-4 text-2xl font-bold">
                {video.ondemand_channel_context?.name ? `More from ${video.ondemand_channel_context.name}` : 'More Like This'}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
                {related.map((item) => (
                  <RelatedCard key={item.id} item={item} channelId={channelId} />
                ))}
              </div>
            </section>
          ) : null}
        </>
      )}
    </main>
  )
}

function PlayerPreparing({ poster }: { poster?: string | null }) {
  return (
    <div className="relative flex aspect-video items-center justify-center overflow-hidden bg-black">
      {poster ? <img src={poster} alt="" className="absolute inset-0 h-full w-full object-cover opacity-35" /> : null}
      <div className="absolute inset-0 bg-black/58" />
      <div className="relative flex items-center gap-3 text-sm font-semibold text-white/72">
        <span className="h-6 w-6 animate-spin rounded-full border-2 border-white/20 border-t-primary" />
        Preparing player
      </div>
    </div>
  )
}

function ChannelBadges({ channels }: { channels: AuthorChannel[] }) {
  if (channels.length === 0) return null

  return (
    <div className="mt-5 flex flex-wrap gap-3">
      {channels.map((channel) => (
        <a
          key={`${channel.id}-${channel.username}`}
          href={channel.username ? `/on-demand/${channel.username}` : '/on-demand'}
          className="inline-flex items-center gap-2 rounded-md border border-white/10 bg-white/[0.06] px-3 py-2 text-sm font-semibold text-white/82 hover:bg-white/[0.1]"
        >
          <Tv className="h-4 w-4 text-primary" />
          {channel.name}
        </a>
      ))}
    </div>
  )
}

function RelatedCard({ item, channelId }: { item: MediaItem; channelId?: string | number | null }) {
  const href = buildVideoHref(item, channelId)

  return (
    <a href={href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <MediaThumbnail src={item.poster_image} alt={item.name} previewSrc={previewHref(item)} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/88 via-black/10 to-transparent" />
        <div className="absolute left-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white/20 backdrop-blur">
          <Play className="h-5 w-5 fill-white text-white" />
        </div>
        {item.duration ? <Badge className="absolute bottom-3 right-3 rounded-sm bg-black/70 text-white">{item.duration}</Badge> : null}
      </div>
      <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug">{item.name}</h3>
    </a>
  )
}

function AdStrip({ ads, label = 'Custom ads available' }: { ads: VideoAd[]; label?: string }) {
  if (ads.length === 0) return null

  return (
    <section className="px-4 pb-8 sm:px-8 lg:px-12">
      <div className="mb-3 text-sm font-bold uppercase text-primary">{label}</div>
      <div className="grid gap-3 md:grid-cols-3">
        {ads.slice(0, 3).map((ad, index) => (
          <a
            key={`${ad.id ?? index}`}
            href={ad.redirect_url ?? ad.url ?? ad.vast_url ?? '#'}
            className="rounded-md border border-white/10 bg-white/[0.045] p-4 text-sm font-semibold text-white/78 hover:bg-white/[0.08]"
          >
            {ad.title ?? ad.name ?? `Ad ${index + 1}`}
          </a>
        ))}
      </div>
    </section>
  )
}

function VideoDetailSkeleton() {
  return (
    <section className="grid min-h-[72vh] items-center gap-8 px-4 py-10 sm:px-8 lg:grid-cols-[1.06fr_0.94fr] lg:px-12">
      <div>
        <div className="h-6 w-24 animate-pulse rounded-sm bg-white/10" />
        <div className="mt-5 h-16 max-w-2xl animate-pulse rounded-md bg-white/10" />
        <div className="mt-4 h-24 max-w-3xl animate-pulse rounded-md bg-white/8" />
      </div>
      <div className="aspect-video self-center animate-pulse rounded-md bg-white/8" />
    </section>
  )
}

function getSlugFromPath() {
  const match = window.location.pathname.match(/^\/video-details\/([^/]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

function getQueryValue(key: string) {
  return new URLSearchParams(window.location.search).get(key)
}

function resolvePlayerUrl(video: VideoDetail) {
  const qualitySource = Array.isArray(video.video_links)
    ? video.video_links.find((item) => {
      const url = item.url ?? item.server_url
      return Boolean(url && item.url_type !== 'Embedded')
    })
    : null

  return video.video_url_input ?? qualitySource?.url ?? qualitySource?.server_url ?? video.trailer_url ?? null
}

function buildVideoHref(video: MediaItem, channelId?: string | number | null) {
  if (!video.slug) return '/videos'

  const params = new URLSearchParams({ autoplay: '1' })
  if (video.ondemand_channel_id ?? channelId) {
    params.set('ondemand_channel', String(video.ondemand_channel_id ?? channelId))
  }

  return `/video-details/${video.slug}?${params.toString()}`
}

function previewHref(video: MediaItem) {
  return video.video_url_input ?? video.video_url ?? video.trailer_url ?? null
}

function shareHref() {
  return `https://www.facebook.com/sharer?u=${encodeURIComponent(window.location.href)}`
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
