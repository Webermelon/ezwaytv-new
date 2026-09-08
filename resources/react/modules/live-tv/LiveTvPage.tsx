import { useEffect, useMemo, useRef, useState, type MouseEvent, type ReactNode } from 'react'
import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CalendarClock, Check, ChevronDown, ChevronLeft, ChevronRight, Copy, Eye, Film, Lock, MessageCircle, Play, Radio, Search, Send, Share2 } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { AdBannerSlider } from '@/components/AdBannerSlider'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { PlayerBackButton } from '@/components/PlayerBackButton'
import { useSpaPath } from '@/lib/spa-router'
import { isNativeIosApp } from '@/lib/native-platform'
import type { LiveTvDashboard, MediaItem, ProgramInfo } from '@/modules/home/types'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'
import { loadContentStats, type ContentStats, type VideoAd } from '@/modules/video-detail/videoDetailApi'
import {
  loadLiveTvAds,
  loadLiveTvChat,
  loadLiveTvDashboard,
  loadLiveTvDetail,
  loadLiveTvGuideChannel,
  loadLiveTvSchedule,
  sendLiveTvChatMessage,
  trackLiveTvPlay,
  trackLiveTvView,
  updateLiveTvWatchTime,
  type LiveTvGuideChannel,
  type LiveTvScheduleItem,
  type LiveTvChatState,
} from './liveTvApi'

type ShareIconProps = {
  className?: string
}

const liveTvHeroImage = 'https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/caa4d6ec_3f9c_4f51_8e9c_95153c5d2b98_6a16d19e8d157.jpg'

export function LiveTvPage() {
  const path = useSpaPath()
  const pathname = path.split(/[?#]/)[0]
  const channelKey = decodeURIComponent(pathname.replace(/^\/(?:spa\/live-tv|livetv)\/?/, '')).replace(/^\/+|\/+$/g, '')
  const [activeCategory, setActiveCategory] = useState('all')
  const [query, setQuery] = useState('')
  const dashboardQuery = useQuery({
    queryKey: ['livetv-dashboard'],
    queryFn: loadLiveTvDashboard,
    staleTime: 60_000,
  })
  const dashboard = dashboardQuery.data ?? {}

  const categories = dashboard.category_data ?? []
  const allChannels = useMemo(() => liveTvChannelsFromDashboard(dashboard), [dashboard])
  const matchedChannel = useMemo(
    () => (channelKey ? findChannel(allChannels, dashboard.slider ?? [], channelKey) : undefined),
    [allChannels, channelKey, dashboard.slider],
  )

  const detailLookupId = matchedChannel?.id ?? channelKey
  const detailQuery = useQuery({
    queryKey: ['livetv-detail', detailLookupId],
    queryFn: () => loadLiveTvDetail(detailLookupId),
    enabled: Boolean(channelKey),
    retry: false,
    staleTime: 30_000,
  })
  const detail = detailQuery.data ?? null

  const channels = useMemo(() => {
    const term = query.trim().toLowerCase()
    const source = activeCategory === 'all'
      ? allChannels
      : categories.find((category) => String(category.id) === activeCategory)?.channel_data ?? []

    if (!term) return sortByDashboardOrder(source)

    return sortByDashboardOrder(source.filter((channel) => {
      const name = channel.details?.name ?? channel.name
      const category = channel.details?.category

      return [name, category].filter(Boolean).some((value) => String(value).toLowerCase().includes(term))
    }))
  }, [activeCategory, allChannels, categories, query])

  if (channelKey) {
    if (!dashboardQuery.isLoading && !detailQuery.isLoading && !detail && !matchedChannel) {
      return <LiveTvNotFound fallbackKey={channelKey} />
    }

    return (
      <LiveTvDetailPage
        channel={detail ?? matchedChannel}
        channelNumber={liveTvChannelNumber(detail ?? matchedChannel, allChannels)}
        fallbackKey={channelKey}
        loading={dashboardQuery.isLoading || detailQuery.isLoading}
        suggestions={detail?.suggested_content ?? relatedChannels(allChannels, detail ?? matchedChannel)}
      />
    )
  }

  const featured = dashboard.slider?.[0] ?? channels[0]
  const scrollToChannels = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    document.getElementById('channels')?.scrollIntoView({ block: 'start', behavior: 'smooth' })
    window.history.replaceState(null, '', '/livetv#channels')
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="livetv" />

      <section className="relative min-h-[66vh] overflow-hidden">
        <img src={liveTvHeroImage} alt="" className="absolute inset-0 h-full w-full object-cover opacity-78" />
        <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.86)_34%,rgba(5,5,5,0.38)_70%,#050505_100%)]" />
        <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#050505] to-transparent" />

        <div className="relative z-10 flex min-h-[66vh] max-w-4xl flex-col justify-end px-4 pb-16 pt-20 sm:px-8 lg:px-12">
          <Badge className="w-fit rounded-sm bg-primary text-white">
            <Radio className="mr-1 h-3.5 w-3.5" />
            Live TV
          </Badge>
          <h1 className="mt-4 max-w-3xl text-5xl font-black leading-none sm:text-6xl">
            {featured?.details?.name ?? featured?.name ?? 'Live TV'}
          </h1>
          <p className="mt-5 max-w-2xl text-sm leading-6 text-white/66 sm:text-base">
            Watch live channels, music, entertainment, and original programming from the eZWay TV network.
          </p>
          <div className="mt-7 flex flex-wrap gap-3">
            <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
              <a href={liveTvSpaHref(featured)}>
                <Play className="h-5 w-5 fill-current" />
                View Channel
              </a>
            </Button>
            <Button asChild size="lg" variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
              <a href="/livetv#channels" onClick={scrollToChannels}>All Live TV</a>
            </Button>
          </div>
        </div>
      </section>

      <section className="px-3 pb-16 sm:px-6 lg:px-8">
        <TvGuide channels={allChannels} loading={dashboardQuery.isLoading} />

        <div id="channels" className="mb-6 scroll-mt-24 grid gap-3 rounded-md border border-white/10 bg-white/[0.045] p-3 lg:grid-cols-[1fr_auto]">
          <div className="flex min-h-11 items-center gap-2 rounded-md bg-black/38 px-3">
            <Search className="h-4 w-4 text-white/44" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search live channels"
              className="h-10 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
            />
          </div>
          <div className="flex flex-wrap gap-2">
            <CategoryButton active={activeCategory === 'all'} onClick={() => setActiveCategory('all')}>
              All
            </CategoryButton>
            {categories.map((category) => (
              <CategoryButton
                key={category.id}
                active={activeCategory === String(category.id)}
                onClick={() => setActiveCategory(String(category.id))}
              >
                {category.name}
              </CategoryButton>
            ))}
          </div>
        </div>

        <div className="mb-3 flex items-center justify-between gap-4">
          <h2 className="text-2xl font-bold">Channels</h2>
          <span className="text-sm text-white/50">{channels.length} shown</span>
        </div>

        {dashboardQuery.isLoading ? (
          <ChannelGridSkeleton />
        ) : channels.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
            {channels.map((channel) => (
              <LiveTvCard key={`${channel.id}-${channel.details?.name}`} channel={channel} channelNumber={liveTvChannelNumber(channel, allChannels)} />
            ))}
          </div>
        ) : (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">
            No live channels matched.
          </div>
        )}
      </section>
      <AdBannerSlider placement="livetv" />
    </main>
  )
}

function LiveTvDetailPage({
  channel,
  channelNumber,
  fallbackKey,
  loading,
  suggestions,
}: {
  channel?: MediaItem
  channelNumber?: number
  fallbackKey: string
  loading: boolean
  suggestions: MediaItem[]
}) {
  const title = channel?.details?.name ?? channel?.name ?? 'Live channel'
  const image = channel?.poster_tv_image ?? channel?.poster_image ?? channel?.details?.thumbnail_image
  const heroBackgroundImage = channel && isEzWayTvChannel(channel) ? liveTvHeroImage : (image ?? liveTvHeroImage)
  const channelBadge = channelNumber ? channelLabel(channelNumber) : null
  const description = cleanLiveTvDescription(channel?.details?.description ?? channel?.description)
  const category = channel?.details?.category
  const stream = resolveLiveTvStream(channel)
  const isSubscriptionLocked = isPremiumMediaLocked(channel)
  const vodChannel = channel?.details?.vod_channel
  const vodChannelUrl = vodChannel?.url || (vodChannel?.username ? `/on-demand/${vodChannel.username}` : null)
  const vodChannelButtonName = vodChannel?.button_name || vodChannel?.name || 'View On Demand'
  const [playId, setPlayId] = useState<number | null>(null)
  const [playerStarted, setPlayerStarted] = useState(false)
  const [playTrigger, setPlayTrigger] = useState(0)
  const [copiedShareUrl, setCopiedShareUrl] = useState(false)
  const playIdRef = useRef<number | null>(null)
  const lastWatchUpdateRef = useRef(0)
  const trackedViewKeyRef = useRef<string | number | null>(null)
  const channelId = channel?.id
  const statsQuery = useQuery({
    queryKey: ['content-stats', 'livetv', channelId],
    queryFn: () => loadContentStats('livetv', channelId as string | number),
    enabled: Boolean(channelId),
    staleTime: 30_000,
  })
  const contentStats = statsQuery.data as ContentStats | null | undefined
  const adsQuery = useQuery({
    queryKey: ['livetv-ads', channelId],
    queryFn: () => loadLiveTvAds(channelId as string | number),
    enabled: Boolean(channelId),
    staleTime: 30_000,
  })
  const chatQuery = useQuery({
    queryKey: ['livetv-chat', channelId],
    queryFn: () => loadLiveTvChat(channelId as string | number),
    enabled: Boolean(channelId),
    staleTime: 10_000,
  })
  const detailSchedule = Array.isArray(channel?.full_schedule) ? channel.full_schedule : []
  const scheduleEnabled = channel?.schedule_enabled !== false && channel?.schedule_enabled !== 0
  const scheduleQuery = useQuery({
    queryKey: ['livetv-schedule', channelId, channel?.schedules_url],
    queryFn: () => loadLiveTvSchedule(channelId as string | number, channel?.schedules_url),
    enabled: Boolean(channelId) && scheduleEnabled && detailSchedule.length === 0,
    staleTime: 60_000,
  })
  const ads = adsQuery.data ?? { vast: [], custom: [] }
  const chat = chatQuery.data ?? null
  const schedule = scheduleQuery.data ?? []
  const displayedSchedule = detailSchedule.length > 0 ? detailSchedule : schedule
  const hasScheduleUi = scheduleEnabled && Boolean(channel?.now_playing?.title || channel?.next_playing?.title || displayedSchedule.length > 0 || scheduleQuery.isLoading)
  const trackViewMutation = useMutation({
    mutationFn: (nextChannel: MediaItem) => trackLiveTvView(nextChannel),
    onSuccess: () => {
      statsQuery.refetch().catch(() => undefined)
    },
  })
  const trackPlayMutation = useMutation({
    mutationFn: (nextChannel: MediaItem) => trackLiveTvPlay(nextChannel),
    onSuccess: (result) => {
      if (result?.play_id) setPlayId(result.play_id)
    },
  })
  const updateWatchTimeMutation = useMutation({
    mutationFn: ({ nextPlayId, seconds }: { nextPlayId: number; seconds: number }) => updateLiveTvWatchTime(nextPlayId, seconds),
  })

  useEffect(() => {
    playIdRef.current = playId
  }, [playId])

  useEffect(() => {
    if (!channel?.id) return

    setPlayerStarted(false)
    setPlayTrigger(0)
    setPlayId(null)
    lastWatchUpdateRef.current = 0
  }, [channel?.id])

  useEffect(() => {
    if (!channel?.id) return

    if (trackedViewKeyRef.current === channel.id) return

    trackedViewKeyRef.current = channel.id
    trackViewMutation.mutate(channel)
  }, [channel, trackViewMutation])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="livetv" />
      <section className="relative left-1/2 w-screen -translate-x-1/2 bg-black">
        <div className="w-screen">
          <PlayerHeader fallbackHref="/livetv" />
          <div className="livetv-player relative aspect-video h-auto min-h-0 w-full overflow-hidden bg-black sm:aspect-auto sm:h-[76svh] sm:min-h-[430px]">
            {isSubscriptionLocked ? (
              <PremiumPlayerLock
                image={image}
                title={title}
                label={`${requiredPlanLabel(channel)} is required to watch this live channel.`}
              />
            ) : stream && playerStarted ? (
              <VideoJsPlayer
                source={stream.url}
                poster={image}
                autoplay={false}
                muted={false}
                playTrigger={playTrigger}
                vastAds={ads.vast}
                isLive
                onPlay={() => {
                  if (!channel || playIdRef.current) return
                  trackPlayMutation.mutate(channel)
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
            ) : stream ? (
              <LiveTvStartScreen image={image} title={title} onStart={() => {
                setPlayerStarted(true)
                setPlayTrigger((value) => value + 1)
              }} />
            ) : loading ? (
              <LiveTvPlayerPreparing image={image} title={title} />
            ) : (
              <div className="flex h-full w-full items-center justify-center bg-black p-8 text-center text-white/56">
                No playable Live TV stream was returned for this channel.
              </div>
            )}
          </div>

          <div className="border-t border-white/10 bg-[#050505] px-3 pb-4 pt-4 sm:px-6 lg:px-8">
            <div className="flex w-full flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="min-w-0">
                <div className="mb-3 flex flex-wrap items-center gap-2">
                  {channelBadge ? <Badge className="w-fit rounded-sm bg-[#d4a843] text-black">{channelBadge}</Badge> : null}
                  {category ? <Badge className="rounded-sm bg-white/14 text-white">{category}</Badge> : null}
                  {channel?.details?.access ? <Badge className="rounded-sm bg-white/14 text-white">{channel.details.access}</Badge> : null}
                  {isSubscriptionLocked ? <Badge variant="outline" className="border-[#d4a843]/50 bg-[#d4a843]/10 text-[#f2d16f]">Premium</Badge> : null}
                </div>
                <div className="flex min-w-0 items-center gap-3">
                  <span className="flex h-12 w-12 shrink-0 overflow-hidden rounded-md border border-white/10 bg-white/[0.06]">
                    {image ? <img src={image} alt="" className="h-full w-full object-cover" loading="lazy" decoding="async" /> : null}
                  </span>
                  <div className="min-w-0">
                    <h1 className="line-clamp-2 text-xl font-black leading-snug text-white sm:text-2xl lg:text-[1.65rem]">{title}</h1>
                    <LiveTvPlayerStats stats={contentStats} compact />
                  </div>
                </div>
              </div>

              <div className="relative z-[80] flex flex-wrap gap-2 lg:justify-end">
                {isSubscriptionLocked ? (
                  <PremiumActionButton />
                ) : !playerStarted ? (
                  <Button
                    type="button"
                    className="bg-white text-black hover:bg-white/85"
                    disabled={!stream && !loading}
                    onClick={() => {
                      setPlayerStarted(true)
                      setPlayTrigger((value) => value + 1)
                    }}
                  >
                    <Play className="h-5 w-5 fill-current" />
                    Watch Live
                  </Button>
                ) : null}
                {vodChannelUrl ? (
                  <Button asChild className="bg-[#d4a843] text-black hover:bg-[#e5bd58]">
                    <a href={vodChannelUrl}>
                      <Film className="h-5 w-5" />
                      {vodChannelButtonName}
                    </a>
                  </Button>
                ) : null}
                <Button asChild variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
                  <a href="/livetv">
                    <Radio className="h-5 w-5" />
                    All Channels
                  </a>
                </Button>
                <LiveTvShareMenu
                  title={title}
                  copied={copiedShareUrl}
                  onCopy={() => {
                    copyLiveTvShareUrl().then(() => {
                      setCopiedShareUrl(true)
                      window.setTimeout(() => setCopiedShareUrl(false), 1800)
                    }).catch(() => undefined)
                  }}
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="px-3 pb-8 pt-3 sm:px-6 lg:px-8">
        <div className="w-full">
          <a href="/livetv" className="mb-4 inline-flex w-fit items-center gap-2 text-sm font-semibold text-white/62 hover:text-white">
            <ArrowLeft className="h-4 w-4" />
            Live TV
          </a>
          {description ? (
            <div className="rounded-md border border-white/10 bg-white/[0.045] p-4 text-sm leading-7 text-white/68">
              {description}
            </div>
          ) : null}
          {isSubscriptionLocked ? <LiveTvPremiumNotice channel={channel} /> : null}
        </div>
      </section>

      <AdStrip ads={ads.custom} />

      {hasScheduleUi || (channel?.id && chat?.enabled) ? (
        <section className="px-3 pb-16 sm:px-6 lg:px-8">
          <div className={chat?.enabled && hasScheduleUi ? 'grid gap-5 lg:grid-cols-[minmax(0,1fr)_390px]' : undefined}>
            {hasScheduleUi ? (
              <SchedulePanel
                now={channel?.now_playing}
                next={channel?.next_playing}
                items={displayedSchedule}
                loading={loading || scheduleQuery.isLoading}
              />
            ) : null}
            {channel?.id && chat?.enabled ? <LiveTvChat channelId={channel.id} chat={chat} /> : null}
          </div>
        </section>
      ) : null}

      {suggestions.length > 0 ? (
        <section className="px-3 pb-16 sm:px-6 lg:px-8">
          <div className="mb-3 flex items-center justify-between gap-4">
            <h2 className="text-2xl font-bold">More Live Channels</h2>
            <a href="/livetv" className="text-sm font-semibold text-white/58 hover:text-white">View all</a>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
            {suggestions.slice(0, 12).map((item) => (
              <LiveTvCard key={`suggestion-${item.id}`} channel={item} channelNumber={liveTvChannelNumber(item, suggestions)} />
            ))}
          </div>
        </section>
      ) : null}
      <AdBannerSlider placement="livetv" />
    </main>
  )
}

function LiveTvNotFound({ fallbackKey }: { fallbackKey: string }) {
  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="livetv" />
      <section className="border-b border-white/10 bg-black">
        <PlayerHeader fallbackHref="/livetv" />
      </section>
      <section className="flex min-h-[58vh] items-center justify-center px-4 py-16">
        <div className="max-w-md text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-md border border-white/10 bg-white/[0.06] text-white/68">
            <Radio className="h-6 w-6" />
          </div>
          <h1 className="mt-5 text-2xl font-black text-white">Live channel not found</h1>
          <p className="mt-3 text-sm leading-6 text-white/56">
            We could not load this live channel. It may be unavailable or the app opened an old link.
          </p>
          {fallbackKey ? <p className="mt-2 text-xs text-white/34">Channel key: {fallbackKey}</p> : null}
          <Button asChild className="mt-6 bg-[#d4a843] text-black hover:bg-[#e5bd58]">
            <a href="/livetv">
              <Radio className="h-5 w-5" />
              All Channels
            </a>
          </Button>
        </div>
      </section>
    </main>
  )
}

function LiveTvShareMenu({ title, copied, onCopy }: { title: string; copied: boolean; onCopy: () => void }) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement | null>(null)
  const shareUrl = currentLiveTvShareUrl()
  const shareText = `Watch ${title} live on EZWay TV`
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
          'absolute right-0 top-full z-[120] mt-3 w-[min(13.5rem,calc(100vw-2rem))] rounded-md border border-white/12 bg-[#111]/98 p-3 shadow-2xl shadow-black/50 backdrop-blur transition',
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

function LiveTvPlayerStats({ stats, compact = false }: { stats?: ContentStats | null; compact?: boolean }) {
  if (!stats?.show_views_frontend) return null

  const views = Number(stats.display_views ?? stats.total_views ?? 0)

  return (
    <div className={compact ? "mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold text-white/50" : "mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-semibold text-white/62"}>
      <span className="inline-flex items-center gap-2">
        <Eye className="h-4 w-4" />
        {formatCompactCount(views)} views
      </span>
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

function LiveTvPlayerPreparing({ image, title }: { image?: string; title: string }) {
  return (
    <div className="relative flex h-full min-h-[320px] w-full items-center justify-center overflow-hidden bg-black">
      {image ? <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-22 blur-sm" /> : null}
      <div className="absolute inset-0 bg-black/60" />
      <div className="relative flex flex-col items-center gap-4 text-center">
        <span className="h-12 w-12 animate-spin rounded-full border-4 border-white/18 border-t-primary" />
        <div>
          <p className="text-sm font-semibold text-white">Preparing live stream</p>
          <p className="mt-1 max-w-sm px-6 text-xs text-white/48">{title}</p>
        </div>
      </div>
    </div>
  )
}

function PlayerHeader({ fallbackHref }: { fallbackHref: string }) {
  return (
    <div className="flex min-h-12 items-center border-b border-white/10 bg-[#050505] px-3 py-2 sm:min-h-14 sm:px-5">
      <PlayerBackButton fallbackHref={fallbackHref} />
    </div>
  )
}

function SchedulePanel({
  now,
  next,
  items,
  loading,
}: {
  now?: ProgramInfo | null
  next?: ProgramInfo | null
  items: LiveTvScheduleItem[]
  loading: boolean
}) {
  const [showFullSchedule, setShowFullSchedule] = useState(false)
  const rows = normalizeScheduleItems(items, now)
  const hasSummary = Boolean(now?.title || next?.title)
  const progress = now?.duration_seconds && now.duration_seconds > 0
    ? Math.min(100, Math.max(0, ((now.elapsed_seconds ?? 0) / now.duration_seconds) * 100))
    : 0

  if (!loading && !hasSummary && rows.length === 0) return null

  return (
    <section className="overflow-hidden rounded-md border border-white/10 bg-white/[0.045]">
      <div className="border-b border-white/10 p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-sm font-bold uppercase tracking-normal text-primary">
            <CalendarClock className="h-4 w-4" />
            Schedule
          </div>
          {rows.length > 0 ? <span className="text-xs font-semibold text-white/42">{rows.length} programs</span> : null}
        </div>

        {hasSummary || loading ? (
          <div className="grid gap-4 md:grid-cols-2">
            {now?.title || loading ? <ProgramSummary label="Now Playing" program={now} progress={progress} loading={loading} /> : null}
            {next?.title || loading ? <ProgramSummary label="Up Next" program={next} loading={loading} /> : null}
          </div>
        ) : null}
      </div>

      {rows.length > 0 ? (
        <div className="p-5">
          <button
            type="button"
            onClick={() => setShowFullSchedule((value) => !value)}
            className="flex w-full items-center justify-between gap-4 rounded-md border border-white/10 bg-black/24 px-4 py-3 text-left transition hover:bg-white/[0.06]"
            aria-expanded={showFullSchedule}
          >
            <span>
              <span className="block text-base font-bold text-white">Full Schedule</span>
              <span className="mt-1 block text-xs text-white/44">{rows.length} current and upcoming programs</span>
            </span>
            <ChevronDown className={['h-5 w-5 shrink-0 text-white/58 transition', showFullSchedule ? 'rotate-180' : ''].join(' ')} />
          </button>

          {showFullSchedule ? (
            <div className="mt-3 max-h-[460px] overflow-y-auto pr-1">
              {rows.map((item, index) => {
                const active = Boolean(now?.title && item.title && item.title === now.title && item.start === now.start_time)

                return (
                  <article
                    key={`${item.id ?? index}`}
                    className={[
                      'grid gap-3 border-b border-white/8 px-1 py-4 last:border-b-0 sm:grid-cols-[150px_1fr_auto]',
                      active ? 'text-white' : 'text-white/72',
                    ].join(' ')}
                  >
                    <div className="text-xs font-semibold text-white/46">
                      {[formatScheduleTime(item.start, item.timezone), formatScheduleTime(item.end, item.timezone)].filter(Boolean).join(' - ')}
                    </div>
                    <h3 className="min-w-0 text-sm font-bold leading-5">{cleanScheduleTitle(item.title)}</h3>
                    {active ? <Badge className="h-fit w-fit rounded-sm bg-red-600 text-white">On Air</Badge> : null}
                  </article>
                )
              })}
            </div>
          ) : null}
        </div>
      ) : null}
    </section>
  )
}

function ProgramSummary({
  label,
  program,
  progress = 0,
  loading,
}: {
  label: string
  program?: ProgramInfo | null
  progress?: number
  loading: boolean
}) {
  return (
    <article className="rounded-md border border-white/10 bg-black/32 p-4">
      <div className="mb-2 text-xs font-bold uppercase text-white/42">{label}</div>
      {program?.title ? (
        <>
          <h3 className="line-clamp-2 text-lg font-bold leading-snug text-white">{cleanScheduleTitle(program.title)}</h3>
          <p className="mt-2 text-sm text-white/56">
            {[formatTime(program.start_time, program.timezone), formatTime(program.end_time, program.timezone)].filter(Boolean).join(' - ')}
          </p>
          {progress > 0 ? (
            <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/12">
              <div className="h-full rounded-full bg-red-600" style={{ width: `${progress}%` }} />
            </div>
          ) : null}
        </>
      ) : (
        loading ? (
          <div className="space-y-2">
            <span className="block h-4 w-3/4 animate-pulse rounded-sm bg-white/10" />
            <span className="block h-3 w-1/2 animate-pulse rounded-sm bg-white/8" />
          </div>
        ) : (
          <p className="text-sm leading-6 text-white/54">No program data is available for this slot.</p>
        )
      )}
    </article>
  )
}

function LiveTvStartScreen({ image, title, onStart }: { image?: string | null; title: string; onStart: () => void }) {
  return (
    <button
      type="button"
      onClick={onStart}
      className="group relative block h-full min-h-[320px] w-full overflow-hidden bg-black text-left"
      aria-label={`Watch ${title} live`}
    >
      {image ? (
        <>
          <img src={image} alt="" className="absolute inset-0 h-full w-full scale-105 object-cover opacity-40 blur-lg" />
          <img src={image} alt={title} className="absolute inset-0 h-full w-full object-contain" />
        </>
      ) : null}
      <div className="absolute inset-0 bg-gradient-to-t from-black/88 via-black/26 to-black/10" />
      <div className="absolute inset-0 flex items-center justify-center">
        <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white text-black shadow-2xl transition group-hover:scale-105 group-hover:bg-primary group-hover:text-white">
          <Play className="h-7 w-7 fill-current" />
        </span>
      </div>
      <div className="absolute inset-x-0 bottom-0 p-4">
        <Badge className="mb-2 rounded-sm bg-red-600 text-white">Live</Badge>
        <h2 className="line-clamp-2 text-lg font-black text-white">{title}</h2>
      </div>
    </button>
  )
}

function CategoryButton({ active, children, onClick }: { active: boolean; children: ReactNode; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={[
        'h-9 rounded-md px-3 text-xs font-bold transition',
        active ? 'bg-primary text-white' : 'bg-white/8 text-white/62 hover:bg-white/14 hover:text-white',
      ].join(' ')}
    >
      {children}
    </button>
  )
}

function LiveTvCard({ channel, channelNumber }: { channel: MediaItem; channelNumber?: number }) {
  const name = channel.details?.name ?? channel.name
  const label = channelNumber ? channelLabel(channelNumber) : null

  return (
    <a href={liveTvSpaHref(channel)} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <MediaThumbnail src={channel.poster_tv_image ?? channel.poster_image ?? channel.details?.thumbnail_image} alt={name} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/34 via-transparent to-transparent" />
        <LiveChannelBadge />
      </div>
      <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{name}</h3>
      <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold">
        {label ? <span className="text-[#d4a843]">{label}</span> : null}
        {channel.details?.category ? <span className="text-white/58">{channel.details.category}</span> : null}
      </div>
    </a>
  )
}

function LiveChannelBadge() {
  return (
    <span className="absolute right-2 top-2 z-30 inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-black/72 px-2.5 py-1 text-[11px] font-extrabold uppercase leading-none tracking-normal text-white shadow-lg backdrop-blur-md">
      <span className="h-2 w-2 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.95)]" aria-hidden="true" />
      LIVE
    </span>
  )
}

function channelArtwork(channel?: MediaItem | null) {
  return channel?.poster_tv_image
    ?? channel?.poster_image
    ?? channel?.details?.thumbnail_image
    ?? channel?.poster_url
    ?? channel?.thumbnail_url
    ?? channel?.cover_image_url
    ?? channel?.avatar_image_url
    ?? channel?.profile_image
    ?? null
}

function ChannelGridSkeleton() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
      {Array.from({ length: 12 }).map((_, index) => (
        <div key={index} className="aspect-[3/2] animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </div>
  )
}

function TvGuide({ channels, loading }: { channels: MediaItem[]; loading: boolean }) {
  const [currentTime, setCurrentTime] = useState(() => Date.now())
  const guideQueries = useQueries({
    queries: channels.map((channel) => ({
      queryKey: ['livetv-guide-channel-detail-schedule-v3', channel.id],
      queryFn: () => loadLiveTvGuideChannel(channel),
      enabled: Boolean(channel.id),
      staleTime: 10 * 60_000,
      gcTime: 45 * 60_000,
    })),
  })
  const visibleRows = useMemo(() => (
    guideQueries
      .map((query, index) => ({ row: query.data, loadedAt: query.dataUpdatedAt || Date.now(), index }))
      .filter((item): item is { row: LiveTvGuideChannel; loadedAt: number; index: number } => Boolean(item.row))
      .filter(({ row }) => tvGuidePrograms(row, currentTime).length > 0)
      .sort((a, b) => {
        const aOrder = channelDashboardOrder(a.row.channel)
        const bOrder = channelDashboardOrder(b.row.channel)
        const nameCompare = channelName(a.row.channel).localeCompare(channelName(b.row.channel), undefined, { sensitivity: 'base' })
        const orderCompare = aOrder - bOrder

        return orderCompare || nameCompare || a.loadedAt - b.loadedAt || a.index - b.index
      })
  ), [currentTime, guideQueries])
  const loadedCount = guideQueries.filter((query) => Boolean(query.data)).length
  const programCount = visibleRows.reduce((total, { row }) => total + tvGuidePrograms(row, currentTime).length, 0)
  const pendingCount = guideQueries.filter((query) => query.isPending || query.isFetching).length
  const showSkeleton = loading && visibleRows.length === 0

  useEffect(() => {
    const timer = window.setInterval(() => setCurrentTime(Date.now()), 60_000)
    return () => window.clearInterval(timer)
  }, [])

  if (!loading && channels.length === 0) return null

  return (
    <section className="mb-8 overflow-hidden rounded-md border border-[#d4a843]/20 bg-[#101010] shadow-2xl shadow-[#d4a843]/10">
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 px-4 py-3">
        <div className="flex min-w-0 items-center gap-3">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-[#d4a843]/30 bg-[#d4a843]/10 text-[#e7bd43]">
            <CalendarClock className="h-5 w-5" />
          </span>
          <div className="min-w-0">
            <h2 className="text-xl font-black leading-tight text-white">TV Guide</h2>
            <p className="mt-0.5 text-xs font-semibold text-white/46">
              {pendingCount > 0
                ? `${loadedCount} of ${channels.length} channels loaded`
                : `${programCount} programs`}
            </p>
          </div>
          <span className="hidden items-center gap-2 rounded-full bg-red-600/10 px-3 py-1 text-[11px] font-black uppercase text-red-400 sm:inline-flex">
            <span className="h-2 w-2 rounded-full bg-red-500" />
            Live Now
          </span>
        </div>

      </div>

      <div className="max-h-[560px] overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {showSkeleton ? (
          <TvGuideSkeleton />
        ) : visibleRows.length > 0 ? (
          <div className="divide-y divide-white/8">
            {visibleRows.map(({ row }) => (
              <TvGuideRow
                key={row.channel.id}
                row={row}
                currentTime={currentTime}
                channelNumber={liveTvChannelNumber(row.channel, channels)}
              />
            ))}
            {pendingCount > 0 ? <TvGuidePendingRows count={Math.min(pendingCount, 3)} /> : null}
          </div>
        ) : pendingCount > 0 ? (
          <TvGuideSkeleton />
        ) : (
          <div className="p-8 text-center text-sm font-semibold text-white/48">No guide programs available.</div>
        )}
      </div>
    </section>
  )
}

function TvGuideRow({
  row,
  currentTime,
  channelNumber,
}: {
  row: LiveTvGuideChannel
  currentTime: number
  channelNumber?: number
}) {
  const scrollerRef = useRef<HTMLDivElement | null>(null)
  const name = row.channel.details?.name ?? row.channel.name
  const label = channelNumber ? channelLabel(channelNumber) : null
  const image = channelArtwork(row.channel)
  const programs = useMemo(() => tvGuidePrograms(row, currentTime), [currentTime, row])

  function scrollByProgram(direction: -1 | 1) {
    scrollerRef.current?.scrollBy({ left: direction * 320, behavior: 'smooth' })
  }

  return (
    <article className="grid gap-2 px-3 py-2.5 lg:grid-cols-[260px_minmax(0,1fr)] lg:items-center">
      <a
        href={liveTvSpaHref(row.channel)}
        className="grid min-w-0 grid-cols-[82px_minmax(0,1fr)] items-center gap-3 rounded-md border border-white/8 bg-white/[0.035] p-2 text-left transition hover:border-[#d4a843]/34 hover:bg-[#d4a843]/8 sm:grid-cols-[92px_minmax(0,1fr)]"
      >
        <span className="block aspect-video overflow-hidden rounded border border-white/10 bg-black">
          <MediaThumbnail src={image} alt={name} />
        </span>
        <span className="min-w-0">
          {label ? <span className="mb-1 block text-[11px] font-black uppercase tracking-wide text-white/40">{label}</span> : null}
          <span className="line-clamp-2 text-sm font-black leading-tight text-[#d4a843]">{name}</span>
          {row.channel.details?.category ? <span className="mt-1 block truncate text-xs font-semibold text-white/38">{row.channel.details.category}</span> : null}
        </span>
      </a>

      <div className="grid min-w-0 grid-cols-1 items-center sm:grid-cols-[32px_minmax(0,1fr)_32px] sm:gap-2">
          <button
            type="button"
            onClick={() => scrollByProgram(-1)}
            className="hidden h-8 w-8 items-center justify-center rounded-md border border-white/10 bg-white/[0.055] text-white/66 transition hover:border-[#d4a843]/50 hover:text-[#f2d16f] sm:flex"
            aria-label={`Scroll ${name} schedule left`}
          >
            <ChevronLeft className="h-4 w-4" />
          </button>
        <div
          ref={scrollerRef}
          className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mx-0 sm:px-0 sm:pb-0"
          tabIndex={0}
        >
          {programs.map((program, index) => {
            const onAir = isScheduleOnAir(program, currentTime)

            return (
              <a
                href={liveTvSpaHref(row.channel)}
                key={`${program.id ?? index}-${scheduleStart(program) ?? index}`}
                className={[
                  'min-h-16 w-[min(245px,78vw)] shrink-0 rounded-md border-l-4 px-3 py-2 transition hover:-translate-y-0.5 hover:border-[#f2d16f] hover:bg-[#d4a843]/16 sm:w-[245px]',
                  onAir
                    ? 'border-red-500 bg-red-500/12 ring-1 ring-red-500/35 shadow-[0_0_22px_rgba(220,38,38,0.18)] hover:border-red-400 hover:bg-red-500/16'
                    : 'border-[#d4a843] bg-[#d4a843]/10',
                ].join(' ')}
              >
                <div className="mb-1.5 flex items-center justify-between gap-2">
                  <span className="inline-flex rounded-sm bg-black/38 px-2 py-1 text-[11px] font-black leading-none text-[#f2d16f]">
                    {[formatTime(scheduleStart(program), program.timezone), formatTime(scheduleEnd(program), program.timezone)].filter(Boolean).join(' - ')}
                  </span>
                  {onAir ? (
                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-black uppercase leading-none text-white">
                      <span className="h-1.5 w-1.5 rounded-full bg-white" />
                      On Air
                    </span>
                  ) : null}
                </div>
                <h3 className="line-clamp-2 text-xs font-black leading-4 text-[#f1dc90]">{cleanScheduleTitle(program.title)}</h3>
              </a>
            )
          })}
        </div>
        <button
          type="button"
          onClick={() => scrollByProgram(1)}
          className="hidden h-8 w-8 items-center justify-center rounded-md border border-white/10 bg-white/[0.055] text-white/66 transition hover:border-[#d4a843]/50 hover:text-[#f2d16f] sm:flex"
          aria-label={`Scroll ${name} schedule right`}
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </article>
  )
}

function TvGuideSkeleton() {
  return (
    <div className="divide-y divide-white/8">
      {Array.from({ length: 5 }).map((_, rowIndex) => (
        <div key={rowIndex} className="grid gap-2 px-3 py-2.5 lg:grid-cols-[170px_minmax(0,1fr)]">
          <div className="min-h-12">
            <div className="h-4 w-20 animate-pulse rounded bg-white/10" />
          </div>
          <div className="flex gap-2 overflow-hidden">
            {Array.from({ length: 4 }).map((__, slotIndex) => (
              <div key={slotIndex} className="h-16 w-[245px] shrink-0 animate-pulse rounded-md bg-white/[0.06]" />
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

function TvGuidePendingRows({ count }: { count: number }) {
  return (
    <>
      {Array.from({ length: count }).map((_, rowIndex) => (
        <div key={rowIndex} className="grid gap-2 px-3 py-2.5 opacity-70 lg:grid-cols-[170px_minmax(0,1fr)]">
          <div className="flex min-h-12 items-center">
            <div className="h-4 w-24 animate-pulse rounded bg-white/8" />
          </div>
          <div className="flex gap-2 overflow-hidden">
            {Array.from({ length: 3 }).map((__, slotIndex) => (
              <div key={slotIndex} className="h-16 w-[245px] shrink-0 animate-pulse rounded-md bg-white/[0.045]" />
            ))}
          </div>
        </div>
      ))}
    </>
  )
}

function LiveTvChat({
  channelId,
  chat,
}: {
  channelId: string | number
  chat: LiveTvChatState | null
}) {
  const [message, setMessage] = useState('')
  const canUseLiveChat = isAuthenticated()
  const queryClient = useQueryClient()
  const sendMessageMutation = useMutation({
    mutationFn: (nextMessage: string) => sendLiveTvChatMessage(channelId, nextMessage),
    onSuccess: (response) => {
      queryClient.setQueryData<LiveTvChatState | null>(['livetv-chat', channelId], (current) => {
        if (!current) return current

        return { ...current, messages: [...(current.messages ?? []), response.message] }
      })
      setMessage('')
    },
  })

  return (
    <section className="rounded-md border border-white/10 bg-white/[0.045] p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2 text-sm font-bold">
          <MessageCircle className="h-4 w-4 text-primary" />
          Live Chat
        </div>
        {canUseLiveChat && chat?.guest_name ? <span className="text-xs text-white/48">{chat.guest_name}</span> : null}
      </div>

      <div className="max-h-72 space-y-3 overflow-y-auto pr-1">
        {chat?.messages?.length ? chat.messages.map((item) => (
          <article key={item.id} className="rounded-md bg-black/32 p-3">
            <div className="mb-1 flex items-center justify-between gap-2 text-xs">
              <span className="font-bold text-white/78">{item.guest_name ?? 'Guest'}</span>
              <span className="text-white/36">{item.time}</span>
            </div>
            <p className="text-sm leading-5 text-white/66">{item.message}</p>
          </article>
        )) : (
          <p className="rounded-md bg-black/32 p-4 text-sm text-white/50">
            No live chat messages yet.
          </p>
        )}
      </div>

      {canUseLiveChat ? (
        <form
          className="mt-4 flex gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            const nextMessage = message.trim()
            if (!nextMessage) return

            sendMessageMutation.mutate(nextMessage)
          }}
        >
          <input
            value={message}
            onChange={(event) => setMessage(event.target.value)}
            placeholder="Message live chat"
            className="h-10 min-w-0 flex-1 rounded-md border border-white/10 bg-black/38 px-3 text-sm text-white outline-none placeholder:text-white/40"
          />
          <Button type="submit" size="icon" disabled={sendMessageMutation.isPending} aria-label="Send message">
            <Send className="h-4 w-4" />
          </Button>
        </form>
      ) : (
        <div className="mt-4 flex flex-col gap-3 rounded-md border border-[#d4a843]/25 bg-[#d4a843]/10 p-4 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm font-semibold text-white">You need to login for live chat.</p>
          <Button asChild className="bg-[#d4a843] text-black hover:bg-[#e5bd58]">
            <a href="/login">Login</a>
          </Button>
        </div>
      )}
    </section>
  )
}

function LiveTvPremiumNotice({ channel }: { channel?: MediaItem }) {
  return (
    <div className="mt-6 max-w-2xl rounded-md border border-[#d4a843]/26 bg-[#d4a843]/10 p-4 text-sm text-white/76">
      <div className="flex items-start gap-3">
        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#d4a843] text-black">
          <Lock className="h-4 w-4" />
        </span>
        <div>
          <h2 className="text-base font-black text-white">Premium plan required</h2>
          <p className="mt-1 leading-6">
            This live channel is included with {requiredPlanLabel(channel)}. Upgrade your plan to unlock live playback.
          </p>
        </div>
      </div>
    </div>
  )
}

function PremiumActionButton() {
  if (isNativeIosApp()) return null

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

function PremiumPlayerLock({ image, title, label }: { image?: string | null; title: string; label: string }) {
  return (
    <div className="relative flex h-full min-h-[320px] w-full flex-col items-center justify-center overflow-hidden bg-black p-8 text-center">
      {image ? <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-28" /> : null}
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(212,168,67,0.18),transparent_36%),linear-gradient(180deg,rgba(0,0,0,0.50),#000_100%)]" />
      <div className="relative flex max-w-md flex-col items-center">
        <span className="flex h-14 w-14 items-center justify-center rounded-full border border-[#d4a843]/40 bg-[#d4a843]/14 text-[#f2d16f] shadow-2xl shadow-[#d4a843]/15">
          <Lock className="h-7 w-7" />
        </span>
        <h2 className="mt-5 text-2xl font-black">Premium Content</h2>
        <p className="mt-2 text-sm font-semibold text-white/76">{title}</p>
        <p className="mt-3 text-sm leading-6 text-white/62">{isAuthenticated() ? label : 'Sign in or choose a subscription plan to play this channel.'}</p>
        <div className="mt-6">
          <PremiumActionButton />
        </div>
      </div>
    </div>
  )
}

function normalizeScheduleItems(items: LiveTvScheduleItem[], now?: ProgramInfo | null) {
  const threshold = parseScheduleDate(now?.start_time, now?.timezone)?.getTime() ?? Date.now()

  return items
    .map((item) => ({
      ...item,
      start: item.start_at ?? item.start_time ?? null,
      end: item.end_at ?? item.end_time ?? null,
    }))
    .filter((item) => item.title || item.start)
    .filter((item) => {
      const endTime = parseScheduleDate(item.end, item.timezone)?.getTime()
      const startTime = parseScheduleDate(item.start, item.timezone)?.getTime()

      if (endTime) return endTime >= threshold
      if (startTime) return startTime >= threshold

      return true
    })
    .sort((a, b) => (parseScheduleDate(a.start, a.timezone)?.getTime() ?? 0) - (parseScheduleDate(b.start, b.timezone)?.getTime() ?? 0))
}

function tvGuidePrograms(row: LiveTvGuideChannel, currentTime: number) {
  const normalized = normalizeScheduleItems(row.schedule, row.channel.now_playing)

  if (normalized.length > 0) return normalized.slice(0, 100)

  return row.schedule
    .filter((item) => item.title || scheduleStart(item))
    .filter((item) => isCurrentOrUpcomingSchedule(item, currentTime))
    .sort((a, b) => (parseScheduleDate(scheduleStart(a), a.timezone)?.getTime() ?? 0) - (parseScheduleDate(scheduleStart(b), b.timezone)?.getTime() ?? 0))
    .slice(0, 100)
}

function AdStrip({ ads }: { ads: VideoAd[] }) {
  if (ads.length === 0) return null

  return (
    <section className="px-4 pb-8 sm:px-8 lg:px-12">
      <div className="mb-3 text-sm font-bold uppercase text-primary">Custom ads available</div>
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

function liveTvSpaHref(channel?: MediaItem) {
  if (!channel?.id) {
    return '/livetv'
  }

  return `/livetv/${channel.slug ?? channel.details?.slug ?? channel.id}`
}

function findChannel(channels: MediaItem[], sliders: MediaItem[], key: string) {
  const normalizedKey = key.toLowerCase()
  const source = [...channels, ...sliders]

  return source.find((channel) => {
    const matches = [channel.id, channel.slug, channel.details?.slug]
      .filter(Boolean)
      .map((value) => String(value).toLowerCase())

    return matches.includes(normalizedKey)
  })
}

function resolveLiveTvStream(channel?: MediaItem) {
  const qualities = channel?.video_qualities ?? []
  const stream = qualities.find((item) => item.url && item.url_type !== 'Embedded') ?? qualities.find((item) => item.url)
  const url = stream?.url ?? channel?.details?.server_url

  if (!url || stream?.url_type === 'Embedded') return null

  return { url, type: stream?.url_type ?? 'HLS' }
}

function isPremiumMediaLocked(item?: MediaItem | null) {
  if (!item) return false
  const access = item.details?.access ?? item.access
  if (access !== 'paid') return false

  const hasAccess = item.details?.has_content_access ?? item.has_content_access
  if (typeof hasAccess !== 'undefined' && hasAccess !== null) {
    return !Boolean(hasAccess)
  }

  return !Boolean(isAuthenticated() && window.ezwayAuth?.is_subscribe)
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}

function requiredPlanLabel(item?: MediaItem | null) {
  const requiredName = item?.details?.required_plan_name ?? item?.required_plan_name
  if (requiredName) return requiredName

  const requiredLevel = item?.details?.required_plan_level ?? item?.required_plan_level ?? item?.plan_level
  if (requiredLevel) return `Plan Level ${requiredLevel}`

  return 'a premium plan'
}

function relatedChannels(channels: MediaItem[], current?: MediaItem) {
  return channels.filter((channel) => String(channel.id) !== String(current?.id)).slice(0, 12)
}

function currentLiveTvShareUrl() {
  return window.location.href
}

function cleanLiveTvDescription(value?: string | null) {
  if (!value) return ''

  return decodeHtmlEntities(value)
    .replace(/<[^>]*>/g, ' ')
    .replace(/\u00a0/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function decodeHtmlEntities(value: string) {
  if (typeof document === 'undefined') {
    return value.replace(/&nbsp;/gi, ' ')
  }

  const textarea = document.createElement('textarea')
  textarea.innerHTML = value

  return textarea.value
}

async function copyLiveTvShareUrl() {
  const url = currentLiveTvShareUrl()

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

function formatCompactCount(value: number) {
  const count = Math.max(0, Number(value) || 0)

  if (count >= 1_000_000) {
    return `${Number((count / 1_000_000).toFixed(1)).toLocaleString()}M`
  }

  if (count >= 1_000) {
    return `${Number((count / 1_000).toFixed(1)).toLocaleString()}K`
  }

  return count.toLocaleString()
}

function formatTime(value?: string | null, timezone?: string | null) {
  if (!value) return null

  const date = parseScheduleDate(value, timezone)
  if (!date) return null

  return date.toLocaleTimeString([], { timeZone: scheduleDisplayTimeZone(timezone), hour: 'numeric', minute: '2-digit', hour12: true })
}

function cleanScheduleTitle(value?: string | null) {
  if (!value) return 'Untitled program'

  let title = value.replace(/\s+/g, ' ').trim()
  let previous = ''

  while (title && title !== previous) {
    previous = title
    title = title
      .replace(/(?:[\s_-]*\((?:converted|copy(?:[\s_-]*\d+)?)\))$/i, '')
      .replace(/(?:[\s_-]+(?:converted|copy(?:[\s_-]*\d+)?))$/i, '')
      .replace(/[\s_-]+$/g, '')
      .trim()
  }

  return title || 'Untitled program'
}

function scheduleStart(item?: LiveTvScheduleItem | ProgramInfo | null) {
  if (!item) return null

  return 'start_at' in item ? (item.start_at ?? item.start_time ?? null) : item.start_time ?? null
}

function scheduleEnd(item?: LiveTvScheduleItem | ProgramInfo | null) {
  if (!item) return null

  return 'end_at' in item ? (item.end_at ?? item.end_time ?? null) : item.end_time ?? null
}

function isScheduleOnAir(item: LiveTvScheduleItem | ProgramInfo, currentTime: number) {
  if ('status' in item && isScheduleStatusPlaying(item.status)) return true

  const timezone = 'timezone' in item ? item.timezone : null
  const start = parseScheduleDate(scheduleStart(item), timezone)?.getTime()
  const end = parseScheduleDate(scheduleEnd(item), timezone)?.getTime()

  if (!start || !end) return false

  return currentTime >= start && currentTime < end
}

function rowHasOnAirProgram(row: LiveTvGuideChannel, currentTime: number) {
  return row.schedule.some((item) => isScheduleOnAir(item, currentTime))
}

function isEzWayTvChannel(channel: MediaItem) {
  return [
    channel.slug,
    channel.details?.slug,
    channelName(channel),
  ].filter(Boolean).map((value) => normalizeLiveTvSlug(String(value))).includes('ezway-tv')
}

function channelName(channel: MediaItem) {
  return String(channel.details?.name ?? channel.name ?? '')
}

function liveTvChannelNumber(channel: MediaItem | null | undefined, channels: MediaItem[]) {
  if (!channel) return undefined
  const storedChannelNumber = Number(channel.channel_number)

  if (Number.isFinite(storedChannelNumber) && storedChannelNumber > 0) {
    return storedChannelNumber
  }

  const channelIndex = sortByDashboardOrder(channels)
    .findIndex((item) => String(item.id) === String(channel.id))

  return channelIndex >= 0 ? channelIndex + 1 : undefined
}

function liveTvChannelsFromDashboard(dashboard: LiveTvDashboard) {
  const channels = dashboard.channel_data ?? dashboard.category_data?.flatMap((category) => category.channel_data ?? []) ?? []

  return sortByDashboardOrder(channels)
}

function sortByDashboardOrder(channels: MediaItem[]) {
  return [...channels].sort((a, b) => {
    const aOrder = channelDashboardOrder(a)
    const bOrder = channelDashboardOrder(b)

    return aOrder - bOrder
  })
}

function channelDashboardOrder(channel: MediaItem) {
  return typeof channel.dashboard_order === 'number' ? channel.dashboard_order : Number.MAX_SAFE_INTEGER
}

function normalizeLiveTvSlug(value: string) {
  return value.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}

function channelLabel(index: number) {
  return `Channel ${String(Math.max(1, index)).padStart(2, '0')}`
}

function isCurrentOrUpcomingSchedule(item: LiveTvScheduleItem | ProgramInfo, currentTime: number) {
  if ('status' in item && isScheduleStatusPlaying(item.status)) return true

  const timezone = 'timezone' in item ? item.timezone : null
  const start = parseScheduleDate(scheduleStart(item), timezone)?.getTime()
  const end = parseScheduleDate(scheduleEnd(item), timezone)?.getTime()

  if (end) return end > currentTime
  if (start) return start >= currentTime

  return false
}

function isScheduleStatusPlaying(status?: string | null) {
  const normalized = String(status ?? '').trim().toLowerCase()

  return ['playing', 'on_air', 'on air', 'on-air', 'looping'].includes(normalized)
}

function parseScheduleDate(value?: string | null, timezone?: string | null) {
  if (!value) return null

  const trimmed = value.trim()
  const hasExplicitTimezone = /(?:z|[+-]\d{2}:?\d{2})$/i.test(trimmed)

  if (timezone?.toUpperCase() === 'UTC') {
    const normalizedUtc = trimmed.includes('T') ? trimmed : trimmed.replace(' ', 'T')
    const date = new Date(`${normalizedUtc.endsWith('Z') ? normalizedUtc : `${normalizedUtc}Z`}`)
    if (Number.isNaN(date.getTime())) return null

    return date
  }

  if (!hasExplicitTimezone) {
    const dhakaTime = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?)?$/)

    if (dhakaTime) {
      const [, year, month, day, hour = '0', minute = '0', second = '0'] = dhakaTime
      const date = new Date(Date.UTC(
        Number(year),
        Number(month) - 1,
        Number(day),
        Number(hour) - 6,
        Number(minute),
        Number(second),
      ))

      return Number.isNaN(date.getTime()) ? null : date
    }
  }

  const normalized = trimmed.includes('T') ? trimmed : trimmed.replace(' ', 'T')
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return null

  return date
}

function formatScheduleTime(value?: string | null, timezone?: string | null) {
  if (!value) return null

  const date = parseScheduleDate(value, timezone)
  if (!date) return null

  return date.toLocaleString([], {
    timeZone: scheduleDisplayTimeZone(timezone),
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

function scheduleDisplayTimeZone(_timezone?: string | null): string | undefined {
  return undefined
}
