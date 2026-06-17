import { useEffect, useMemo, useRef, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Calendar, Check, Clock, Copy, Lock, MessageCircle, Play, Share2, Star, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { AdBannerSlider } from '@/components/AdBannerSlider'
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

type ShareIconProps = {
  className?: string
}

type VideoDetail = MediaItem & {
  author_channels?: AuthorChannel[]
  categories?: Array<{ id?: number | string; name?: string; slug?: string }>
  genres?: Array<{ id?: number | string; name?: string; slug?: string }>
  is_restricted?: boolean | number
  has_content_access?: boolean | number | null
  is_premium?: boolean | number | null
  show_premium_badge?: boolean | number | null
  is_pay_per_view?: boolean
  is_purchased?: boolean
  price?: string | number
  discounted_price?: string | number
  plan_id?: string | number | null
  plan_level?: string | number | null
  required_plan_level?: string | number | null
  required_plan_name?: string | null
  current_plan_level?: string | number | null
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
  const [playId, setPlayId] = useState<number | null>(null)
  const [playTrigger, setPlayTrigger] = useState(0)
  const [copiedShareUrl, setCopiedShareUrl] = useState(false)
  const playIdRef = useRef<number | null>(null)
  const lastWatchUpdateRef = useRef(0)
  const trackedViewKeyRef = useRef<string | null>(null)

  useEffect(() => {
    playIdRef.current = playId
  }, [playId])

  const videoQuery = useQuery({
    queryKey: ['video-detail', slug, ondemandChannel],
    queryFn: () => loadVideoDetail(slug, ondemandChannel),
    enabled: Boolean(slug),
  })
  const video = videoQuery.data as VideoDetail | null | undefined
  const videoId = video?.id
  const channelId = video?.ondemand_channel_context?.id ?? ondemandChannel
  const adsQuery = useQuery({
    queryKey: ['video-ads', videoId],
    queryFn: () => loadVideoAds(videoId as string | number),
    enabled: Boolean(videoId),
    staleTime: 30_000,
  })
  const ads = adsQuery.data ?? { vast: [], custom: [] }
  const trackViewMutation = useMutation({
    mutationFn: ({ nextVideo, nextChannelId }: { nextVideo: VideoDetail; nextChannelId?: string | number | null }) => (
      trackVideoView(nextVideo, nextChannelId)
    ),
  })
  const trackPlayMutation = useMutation({
    mutationFn: ({ nextVideo, nextChannelId }: { nextVideo: VideoDetail; nextChannelId?: string | number | null }) => (
      trackVideoPlay(nextVideo, nextChannelId)
    ),
    onSuccess: (result) => {
      if (result?.play_id) setPlayId(result.play_id)
    },
  })
  const updateWatchTimeMutation = useMutation({
    mutationFn: ({ nextPlayId, seconds }: { nextPlayId: number; seconds: number }) => updateWatchTime(nextPlayId, seconds),
  })

  useEffect(() => {
    if (!video?.id) return

    const viewKey = `${video.id}:${channelId ?? 'video'}`
    if (trackedViewKeyRef.current === viewKey) return

    trackedViewKeyRef.current = viewKey
    trackViewMutation.mutate({ nextVideo: video, nextChannelId: channelId })
  }, [channelId, trackViewMutation, video])

  const related = video?.more_items ?? []
  const isPayPerViewLocked = video?.access === 'pay-per-view' && !video.is_purchased
  const isSubscriptionLocked = Boolean(video && video.access === 'paid' && !hasVideoAccess(video))
  const isLocked = isPayPerViewLocked || isSubscriptionLocked
  const playerUrl = video ? resolvePlayerUrl(video) : null

  if (!slug) {
    return <PublicPage />
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader />

      {videoQuery.isLoading ? (
        <VideoDetailSkeleton />
      ) : videoQuery.isError || !video ? (
        <section className="px-4 py-16 sm:px-8 lg:px-12">
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-8 text-white/68">
            {videoQuery.isError ? 'Video details could not be loaded.' : 'Video not found.'}
          </div>
        </section>
      ) : (
        <>
          <section className="relative z-30 overflow-visible">
            {video.poster_tv_image || video.poster_image ? (
              <img src={video.poster_tv_image ?? video.poster_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-34" />
            ) : null}
            <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.92)_40%,rgba(5,5,5,0.68)_76%,#050505_100%)]" />
            <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#050505] to-transparent" />

            <div className="relative z-10 grid min-h-[72vh] items-start gap-6 px-4 py-5 sm:px-8 lg:grid-cols-[1.06fr_0.94fr] lg:items-center lg:gap-8 lg:px-12 lg:py-8">
              <section className="order-2 min-w-0 pb-8 pt-0 lg:order-1 lg:py-10">
                <div className="mb-4 flex flex-wrap gap-2">
                  <Badge className="rounded-sm bg-primary text-white">{video.access ?? 'video'}</Badge>
                  {isSubscriptionLocked ? <Badge variant="outline" className="border-primary/50 bg-primary/10 text-primary">Premium</Badge> : null}
                  {video.is_restricted ? <Badge variant="outline" className="border-white/16 text-white/76">Age restricted</Badge> : null}
                  {channelId ? <Badge variant="outline" className="border-white/16 text-white/76">On Demand</Badge> : null}
                </div>

                <h1 className="max-w-4xl text-2xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">
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

                {isSubscriptionLocked ? <PremiumAccessNotice video={video} /> : null}

                <div className="relative z-[80] mt-7 flex flex-wrap gap-3 pb-2">
                  {isPayPerViewLocked ? (
                    <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
                      <a href="/pay-per-view">
                        <Lock className="h-5 w-5" />
                        Rent / Buy
                      </a>
                    </Button>
                  ) : isSubscriptionLocked ? (
                    <PremiumActionButton video={video} />
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
                  <ShareMenu
                    title={video.name}
                    copied={copiedShareUrl}
                    onCopy={() => {
                      copyShareUrl().then(() => {
                        setCopiedShareUrl(true)
                        window.setTimeout(() => setCopiedShareUrl(false), 1800)
                      }).catch(() => undefined)
                    }}
                  />
                </div>
              </section>

              <aside className="video-detail-player order-1 min-w-0 self-center overflow-hidden rounded-md border border-white/10 bg-black shadow-2xl lg:order-2">
                {isSubscriptionLocked ? (
                  <PremiumPlayerLock video={video} />
                ) : isPayPerViewLocked ? (
                  <div className="flex aspect-video flex-col items-center justify-center gap-3 bg-black p-8 text-center">
                    <Lock className="h-10 w-10 text-primary" />
                    <h2 className="text-2xl font-bold">Purchase required</h2>
                    <p className="max-w-md text-sm text-white/58">This video is protected by the existing pay-per-view access rules.</p>
                  </div>
                ) : playerUrl && !isLocked ? (
                  adsQuery.isLoading ? (
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
                        trackPlayMutation.mutate({ nextVideo: video, nextChannelId: channelId })
                      }}
                      onTimeUpdate={(seconds) => {
                        if (playIdRef.current && seconds - lastWatchUpdateRef.current >= 15) {
                          lastWatchUpdateRef.current = seconds
                          updateWatchTimeMutation.mutate({ nextPlayId: playIdRef.current, seconds })
                        }
                      }}
                      onPause={(seconds) => {
                        if (!playIdRef.current || seconds <= lastWatchUpdateRef.current) return

                        lastWatchUpdateRef.current = seconds
                        updateWatchTimeMutation.mutate({ nextPlayId: playIdRef.current, seconds })
                      }}
                      onEnded={(seconds) => {
                        if (!playIdRef.current) return

                        const finalSeconds = Math.max(seconds, lastWatchUpdateRef.current)
                        lastWatchUpdateRef.current = finalSeconds
                        updateWatchTimeMutation.mutate({ nextPlayId: playIdRef.current, seconds: finalSeconds })
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
          <AdBannerSlider placement="video" />
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

function PremiumAccessNotice({ video }: { video: VideoDetail }) {
  return (
    <div className="mt-6 max-w-3xl rounded-md border border-primary/26 bg-primary/10 p-4 text-sm text-white/76">
      <div className="flex items-start gap-3">
        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary text-black">
          <Lock className="h-4 w-4" />
        </span>
        <div>
          <h2 className="text-base font-black text-white">Premium plan required</h2>
          <p className="mt-1 leading-6">
            This video is included with {requiredPlanLabel(video)}. Upgrade your plan to unlock playback.
          </p>
          <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-white/58">
            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/30 px-3 py-1">
              <Check className="h-3.5 w-3.5 text-primary" />
              Premium video access
            </span>
            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/30 px-3 py-1">
              <Check className="h-3.5 w-3.5 text-primary" />
              Watch on supported devices
            </span>
          </div>
        </div>
      </div>
    </div>
  )
}

function PremiumActionButton({ video }: { video: VideoDetail }) {
  if (!isAuthenticated()) {
    return (
      <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
        <a href={`/login?redirect=${encodeURIComponent(window.location.href)}`}>
          <Lock className="h-5 w-5" />
          Sign In to Watch
        </a>
      </Button>
    )
  }

  return (
    <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
      <a href="/subscription-plan">
        <Lock className="h-5 w-5" />
        Upgrade Plan
      </a>
    </Button>
  )
}

function PremiumPlayerLock({ video }: { video: VideoDetail }) {
  return (
    <div className="relative flex aspect-video min-h-[260px] flex-col items-center justify-center overflow-hidden bg-black p-8 text-center">
      {video.poster_image || video.poster_tv_image ? (
        <img src={video.poster_image ?? video.poster_tv_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-28" />
      ) : null}
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,168,67,0.18),transparent_36%),linear-gradient(180deg,rgba(0,0,0,0.50),#000_100%)]" />
      <div className="relative flex max-w-md flex-col items-center">
        <span className="flex h-14 w-14 items-center justify-center rounded-full border border-primary/40 bg-primary/14 text-primary shadow-2xl shadow-primary/15">
          <Lock className="h-7 w-7" />
        </span>
        <h2 className="mt-5 text-2xl font-black">Premium Content</h2>
        <p className="mt-3 text-sm leading-6 text-white/62">
          {isAuthenticated()
            ? `${requiredPlanLabel(video)} is required to play this video.`
            : 'Sign in or choose a subscription plan to play this video.'}
        </p>
        <div className="mt-6">
          <PremiumActionButton video={video} />
        </div>
      </div>
    </div>
  )
}

function ChannelBadges({ channels }: { channels: AuthorChannel[] }) {
  if (channels.length === 0) return null

  return (
    <div className="mt-5 flex flex-wrap gap-3">
      {channels.map((channel) => (
        <ChannelBadge key={`${channel.id}-${channel.username}`} channel={channel} />
      ))}
    </div>
  )
}

function ChannelBadge({ channel }: { channel: AuthorChannel }) {
  const image = channel.avatar_image_url ?? channel.avatar ?? null
  const name = channel.name ?? 'On Demand Channel'

  return (
    <a
      href={channel.username ? `/on-demand/${channel.username}` : '/on-demand'}
      className="group inline-flex min-h-14 max-w-full items-center gap-3 rounded-md border border-white/12 bg-white/[0.055] px-3 py-2 text-left shadow-lg shadow-black/20 transition hover:border-primary/55 hover:bg-white/[0.095]"
    >
      <span className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-primary/45 bg-primary/14 text-sm font-black text-primary ring-2 ring-black/35">
        {image ? <img src={image} alt="" className="h-full w-full object-cover" loading="lazy" /> : initials(name)}
      </span>
      <span className="min-w-0">
        <span className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-[0.12em] text-primary/88">
          <Tv className="h-3.5 w-3.5" />
          On Demand
        </span>
        <span className="mt-0.5 block max-w-[240px] truncate text-sm font-black text-white group-hover:text-white">
          {name}
        </span>
      </span>
    </a>
  )
}

function RelatedCard({ item, channelId }: { item: MediaItem; channelId?: string | number | null }) {
  const href = buildVideoHref(item, channelId)
  const image = item.poster_image ?? item.poster_tv_image ?? item.thumbnail_url ?? item.cover_image_url ?? item.details?.thumbnail_image

  return (
    <a href={href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <div className="aspect-video bg-black">
          {image ? (
            <img src={image} alt={item.name} className="h-full w-full object-cover" loading="lazy" />
          ) : (
            <div className="flex h-full w-full items-center justify-center text-sm font-semibold text-white/42">No image</div>
          )}
        </div>
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

function initials(value: string) {
  const letters = value
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')

  return letters || 'OD'
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

function ShareMenu({ title, copied, onCopy }: { title: string; copied: boolean; onCopy: () => void }) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement | null>(null)
  const shareUrl = currentShareUrl()
  const shareText = `Watch ${title} on EZWay TV`
  const shareTargets = [
    {
      label: 'LinkedIn',
      icon: LinkedInIcon,
      tone: 'hover:border-[#0a66c2]/70 hover:bg-[#0a66c2]/18',
      href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}`,
    },
    {
      label: 'Facebook',
      icon: FacebookIcon,
      tone: 'hover:border-[#1877f2]/70 hover:bg-[#1877f2]/18',
      href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`,
    },
    {
      label: 'X',
      icon: XIcon,
      tone: 'hover:border-white/50 hover:bg-white/14',
      href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`,
    },
    {
      label: 'WhatsApp',
      icon: WhatsAppIcon,
      tone: 'hover:border-[#25d366]/70 hover:bg-[#25d366]/18',
      href: `https://wa.me/?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}`,
    },
    {
      label: 'SMS',
      icon: MessageCircle,
      tone: 'hover:border-primary/70 hover:bg-primary/16',
      href: `sms:?&body=${encodeURIComponent(`${shareText} ${shareUrl}`)}`,
    },
  ]

  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={menuRef} className="relative z-[90] max-sm:static">
      <Button
        type="button"
        size="lg"
        variant="secondary"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        className="bg-white/14 text-white hover:bg-white/24"
      >
        <Share2 className="h-5 w-5" />
        Share
      </Button>
      <div
        role="menu"
        className={[
          'absolute right-0 top-full z-[120] mt-3 w-[min(13.5rem,calc(100vw-2rem))] rounded-md border border-white/12 bg-[#111]/98 p-3 shadow-2xl shadow-black/50 backdrop-blur transition sm:left-0 sm:right-auto',
          open ? 'visible opacity-100' : 'pointer-events-none invisible opacity-0',
          'max-sm:static max-sm:w-full max-sm:basis-full max-sm:shadow-none',
          open ? 'max-sm:block' : 'max-sm:hidden',
        ].join(' ')}
      >
        <div className="grid grid-cols-3 gap-2">
          {shareTargets.map(({ label, icon: Icon, href, tone }) => (
            <a
              key={label}
              href={href}
              target={href.startsWith('http') ? '_blank' : undefined}
              rel={href.startsWith('http') ? 'noreferrer' : undefined}
              role="menuitem"
              onClick={() => setOpen(false)}
              aria-label={`Share on ${label}`}
              title={label}
              className={[
                'inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:text-white',
                tone,
              ].join(' ')}
            >
              <Icon className="h-[18px] w-[18px] shrink-0" />
              <span className="sr-only">{label}</span>
            </a>
          ))}
          <button
            type="button"
            role="menuitem"
            onClick={() => {
              onCopy()
              setOpen(false)
            }}
            aria-label={copied ? 'Link copied' : 'Copy link'}
            title={copied ? 'Copied' : 'Copy link'}
            className="inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:border-primary/70 hover:bg-primary/16 hover:text-white"
          >
            {copied ? <Check className="h-[18px] w-[18px] shrink-0" /> : <Copy className="h-[18px] w-[18px] shrink-0" />}
            <span className="sr-only">{copied ? 'Copied' : 'Copy Link'}</span>
          </button>
        </div>
      </div>
    </div>
  )
}

function FacebookIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M14.2 8.1V6.6c0-.7.5-.9.9-.9h2.2V2.2L14.2 2c-3.4 0-4.2 2.1-4.2 4.1v2H7.3v3.9H10V22h4.2v-10h3.1l.5-3.9h-3.6Z" />
    </svg>
  )
}

function XIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M13.8 10.5 21 2h-1.7l-6.2 7.3L8.1 2H2.3l7.6 11.1L2.3 22h1.7l6.7-7.8 5.3 7.8h5.8l-8-11.5Zm-2.4 2.8-.8-1.1L4.5 3.3h2.8l4.9 7 .8 1.1 6.4 9.2h-2.8l-5.2-7.3Z" />
    </svg>
  )
}

function WhatsAppIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M12 2.2A9.7 9.7 0 0 0 3.7 17L2.5 21.8l4.9-1.3A9.7 9.7 0 1 0 12 2.2Zm0 17.5c-1.6 0-3.1-.5-4.4-1.3l-.3-.2-2.9.8.8-2.8-.2-.3a7.8 7.8 0 1 1 7 3.8Zm4.3-5.8c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1l-.7.9c-.1.2-.3.2-.5.1a6.4 6.4 0 0 1-1.9-1.2 7.4 7.4 0 0 1-1.3-1.7c-.1-.2 0-.4.1-.5l.4-.5c.1-.1.1-.2.2-.4.1-.1 0-.3 0-.4l-.7-1.6c-.2-.4-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.8 2.3.9 2.4c.1.2 1.7 2.7 4.2 3.7.6.3 1 .4 1.4.5.6.2 1.1.1 1.5.1.5-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1 0-.2-.2-.3-.4-.4Z" />
    </svg>
  )
}

function LinkedInIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M5.3 8.9H2.1V22h3.2V8.9ZM3.7 2.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8Zm18.2 12.2V22h-3.2v-6.8c0-1.7-.6-2.8-2.1-2.8-1.2 0-1.8.8-2.1 1.5-.1.3-.1.7-.1 1V22h-3.2s.1-11.5 0-12.7h3.2v1.8c.4-.7 1.2-1.6 3-1.6 2.2 0 4.5 1.4 4.5 5.2Z" />
    </svg>
  )
}

function VideoDetailSkeleton() {
  return (
    <section className="grid min-h-[72vh] items-start gap-6 px-4 py-5 sm:px-8 lg:grid-cols-[1.06fr_0.94fr] lg:items-center lg:gap-8 lg:px-12 lg:py-10">
      <div className="order-2 lg:order-1">
        <div className="h-6 w-24 animate-pulse rounded-sm bg-white/10" />
        <div className="mt-5 h-16 max-w-2xl animate-pulse rounded-md bg-white/10" />
        <div className="mt-4 h-24 max-w-3xl animate-pulse rounded-md bg-white/8" />
      </div>
      <div className="order-1 aspect-video self-center animate-pulse rounded-md bg-white/8 lg:order-2" />
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

function hasVideoAccess(video: VideoDetail) {
  if (video.access === 'free') return true
  if (video.access === 'pay-per-view') return Boolean(video.is_purchased)
  if (video.access !== 'paid') return true

  if (typeof video.has_content_access !== 'undefined' && video.has_content_access !== null) {
    return Boolean(video.has_content_access)
  }

  return Boolean(isAuthenticated() && window.ezwayAuth?.is_subscribe)
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}

function requiredPlanLabel(video: VideoDetail) {
  if (video.required_plan_name) return video.required_plan_name
  if (video.required_plan_level || video.plan_level) return `Plan Level ${video.required_plan_level ?? video.plan_level}`

  return 'a premium plan'
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

function currentShareUrl() {
  return window.location.href
}

async function copyShareUrl() {
  const url = currentShareUrl()

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(url)
    return
  }

  const input = document.createElement('input')
  input.value = url
  input.setAttribute('readonly', '')
  input.style.position = 'fixed'
  input.style.opacity = '0'
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}
