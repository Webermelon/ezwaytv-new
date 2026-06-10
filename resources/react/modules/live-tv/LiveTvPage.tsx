import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CalendarClock, ChevronDown, MessageCircle, Play, Radio, Search, Send } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { AdBannerSlider } from '@/components/AdBannerSlider'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { useSpaPath } from '@/lib/spa-router'
import type { LiveTvDashboard, MediaItem, ProgramInfo } from '@/modules/home/types'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'
import type { VideoAd } from '@/modules/video-detail/videoDetailApi'
import {
  loadLiveTvAds,
  loadLiveTvChat,
  loadLiveTvDashboard,
  loadLiveTvDetail,
  loadLiveTvSchedule,
  sendLiveTvChatMessage,
  trackLiveTvPlay,
  trackLiveTvView,
  updateLiveTvWatchTime,
  type LiveTvScheduleItem,
  type LiveTvChatState,
} from './liveTvApi'

export function LiveTvPage() {
  const path = useSpaPath()
  const channelKey = decodeURIComponent(path.split('?')[0].replace(/^\/(?:spa\/live-tv|livetv)\/?/, '')).replace(/^\/+|\/+$/g, '')
  const [activeCategory, setActiveCategory] = useState('all')
  const [query, setQuery] = useState('')
  const dashboardQuery = useQuery({
    queryKey: ['livetv-dashboard'],
    queryFn: loadLiveTvDashboard,
    staleTime: 60_000,
  })
  const dashboard = dashboardQuery.data ?? {}

  const categories = dashboard.category_data ?? []
  const allChannels = useMemo(() => categories.flatMap((category) => category.channel_data ?? []), [categories])
  const matchedChannel = useMemo(
    () => (channelKey ? findChannel(allChannels, dashboard.slider ?? [], channelKey) : undefined),
    [allChannels, channelKey, dashboard.slider],
  )

  const detailLookupId = matchedChannel?.id ?? channelKey
  const detailQuery = useQuery({
    queryKey: ['livetv-detail', detailLookupId],
    queryFn: () => loadLiveTvDetail(detailLookupId),
    enabled: Boolean(channelKey),
    staleTime: 30_000,
  })
  const detail = detailQuery.data ?? null

  const channels = useMemo(() => {
    const term = query.trim().toLowerCase()
    const source = activeCategory === 'all'
      ? allChannels
      : categories.find((category) => String(category.id) === activeCategory)?.channel_data ?? []

    if (!term) return source

    return source.filter((channel) => {
      const name = channel.details?.name ?? channel.name
      const category = channel.details?.category

      return [name, category].filter(Boolean).some((value) => String(value).toLowerCase().includes(term))
    })
  }, [activeCategory, allChannels, categories, query])

  if (channelKey) {
    return (
      <LiveTvDetailPage
        channel={detail ?? matchedChannel}
        fallbackKey={channelKey}
        loading={dashboardQuery.isLoading || detailQuery.isLoading}
        suggestions={detail?.suggested_content ?? relatedChannels(allChannels, detail ?? matchedChannel)}
      />
    )
  }

  const featured = dashboard.slider?.[0] ?? channels[0]

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="livetv" />

      <section className="relative min-h-[66vh] overflow-hidden">
        {featured?.poster_tv_image || featured?.poster_image ? (
          <img src={featured.poster_tv_image ?? featured.poster_image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-62" />
        ) : null}
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
            Browse live channels in the React SPA. Channel detail pages now stay inside React, while playback still uses the existing Laravel player.
          </p>
          <div className="mt-7 flex flex-wrap gap-3">
            <Button asChild size="lg" className="bg-white text-black hover:bg-white/85">
              <a href={liveTvSpaHref(featured)}>
                <Play className="h-5 w-5 fill-current" />
                View Channel
              </a>
            </Button>
            <Button asChild size="lg" variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
              <a href="/livetv">All Live TV</a>
            </Button>
          </div>
        </div>
      </section>

      <section className="px-4 pb-16 sm:px-8 lg:px-12">
        <div className="mb-6 grid gap-3 rounded-md border border-white/10 bg-white/[0.045] p-3 lg:grid-cols-[1fr_auto]">
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
              <LiveTvCard key={`${channel.id}-${channel.details?.name}`} channel={channel} />
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
  fallbackKey,
  loading,
  suggestions,
}: {
  channel?: MediaItem
  fallbackKey: string
  loading: boolean
  suggestions: MediaItem[]
}) {
  const title = channel?.details?.name ?? channel?.name ?? 'Live channel'
  const image = channel?.poster_tv_image ?? channel?.poster_image ?? channel?.details?.thumbnail_image
  const description = channel?.details?.description ?? channel?.description ?? 'Live channel details are loading from the existing Laravel APIs.'
  const category = channel?.details?.category
  const stream = resolveLiveTvStream(channel)
  const [playId, setPlayId] = useState<number | null>(null)
  const [playerStarted, setPlayerStarted] = useState(false)
  const [playTrigger, setPlayTrigger] = useState(0)
  const playIdRef = useRef<number | null>(null)
  const lastWatchUpdateRef = useRef(0)
  const trackedViewKeyRef = useRef<string | number | null>(null)
  const channelId = channel?.id
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
  const scheduleQuery = useQuery({
    queryKey: ['livetv-schedule', channelId, channel?.schedules_url],
    queryFn: () => loadLiveTvSchedule(channelId as string | number, channel?.schedules_url),
    enabled: Boolean(channelId) && detailSchedule.length === 0,
    staleTime: 60_000,
  })
  const ads = adsQuery.data ?? { vast: [], custom: [] }
  const chat = chatQuery.data ?? null
  const schedule = scheduleQuery.data ?? []
  const displayedSchedule = detailSchedule.length > 0 ? detailSchedule : schedule
  const hasScheduleUi = Boolean(channel?.now_playing?.title || channel?.next_playing?.title || displayedSchedule.length > 0 || scheduleQuery.isLoading)
  const trackViewMutation = useMutation({
    mutationFn: (nextChannel: MediaItem) => trackLiveTvView(nextChannel),
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
      <section className="relative overflow-hidden">
        {image ? <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover opacity-58" /> : null}
        <div className="absolute inset-0 bg-[linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.9)_38%,rgba(5,5,5,0.42)_76%,#050505_100%)]" />
        <div className="absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-[#050505] to-transparent" />

        <div className="relative z-10 grid min-h-[76vh] items-start gap-6 px-4 py-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-8 lg:px-12 lg:py-8">
          <section className="order-2 min-w-0 pb-8 pt-0 lg:order-1 lg:py-10">
            <a href="/livetv" className="mb-6 inline-flex w-fit items-center gap-2 text-sm font-semibold text-white/62 hover:text-white">
              <ArrowLeft className="h-4 w-4" />
              Live TV
            </a>
            <div className="flex flex-wrap gap-2">
              <Badge className="w-fit rounded-sm bg-red-600 text-white">
                <Radio className="mr-1 h-3.5 w-3.5" />
                Live
              </Badge>
              {category ? <Badge className="rounded-sm bg-white/14 text-white">{category}</Badge> : null}
              {channel?.details?.access ? <Badge className="rounded-sm bg-white/14 text-white">{channel.details.access}</Badge> : null}
            </div>
            <h1 className="mt-4 max-w-3xl text-2xl font-black leading-tight sm:text-4xl lg:text-5xl">{title}</h1>
            <p className="mt-5 max-w-2xl text-sm leading-6 text-white/68 sm:text-base">{description}</p>

            <div className="mt-7 flex flex-wrap gap-3">
              <Button
                type="button"
                size="lg"
                className="bg-white text-black hover:bg-white/85"
                disabled={!stream && !loading}
                onClick={() => {
                  setPlayerStarted(true)
                  setPlayTrigger((value) => value + 1)
                  document.querySelector<HTMLElement>('.livetv-player')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                }}
              >
                <Play className="h-5 w-5 fill-current" />
                Watch Live
              </Button>
              <Button asChild size="lg" variant="secondary" className="bg-white/14 text-white hover:bg-white/24">
                <a href="/livetv">All Channels</a>
              </Button>
            </div>
          </section>

          <div className="livetv-player order-1 min-w-0 self-center overflow-hidden rounded-md border border-white/10 bg-black shadow-2xl lg:order-2">
            {stream && playerStarted ? (
              <VideoJsPlayer
                source={stream.url}
                poster={image}
                autoplay={false}
                muted={false}
                playTrigger={playTrigger}
                vastAds={ads.vast}
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
              />
            ) : stream ? (
              <LiveTvStartScreen image={image} title={title} onStart={() => {
                setPlayerStarted(true)
                setPlayTrigger((value) => value + 1)
              }} />
            ) : loading ? (
              <LiveTvPlayerPreparing image={image} title={title} />
            ) : (
              <div className="flex aspect-video items-center justify-center bg-black p-8 text-center text-white/56">
                No playable Live TV stream was returned for this channel.
              </div>
            )}
          </div>
        </div>
      </section>

      <AdStrip ads={ads.custom} />

      {hasScheduleUi || (channel?.id && chat?.enabled) ? (
        <section className="px-4 pb-16 sm:px-8 lg:px-12">
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

      <section className="px-4 pb-16 sm:px-8 lg:px-12">
        <div className="mb-3 flex items-center justify-between gap-4">
          <h2 className="text-2xl font-bold">More Live Channels</h2>
          <a href="/livetv" className="text-sm font-semibold text-white/58 hover:text-white">View all</a>
        </div>
        {suggestions.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-6">
            {suggestions.slice(0, 12).map((item) => (
              <LiveTvCard key={`suggestion-${item.id}`} channel={item} />
            ))}
          </div>
        ) : (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-8 text-sm text-white/56">
            More channels will appear here when the API returns related Live TV content.
          </div>
        )}
      </section>
      <AdBannerSlider placement="livetv" />
    </main>
  )
}

function LiveTvPlayerPreparing({ image, title }: { image?: string; title: string }) {
  return (
    <div className="relative flex aspect-video items-center justify-center overflow-hidden bg-black">
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
                      {[formatScheduleTime(item.start), formatScheduleTime(item.end)].filter(Boolean).join(' - ')}
                    </div>
                    <h3 className="min-w-0 text-sm font-bold leading-5">{item.title ?? 'Untitled program'}</h3>
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
          <h3 className="line-clamp-2 text-lg font-bold leading-snug text-white">{program.title}</h3>
          <p className="mt-2 text-sm text-white/56">
            {[formatTime(program.start_time), formatTime(program.end_time)].filter(Boolean).join(' - ')}
          </p>
          {progress > 0 ? (
            <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/12">
              <div className="h-full rounded-full bg-red-600" style={{ width: `${progress}%` }} />
            </div>
          ) : null}
        </>
      ) : (
        <p className="text-sm leading-6 text-white/54">
          {loading ? 'Loading schedule from the existing Live TV API.' : 'No program data is available for this slot.'}
        </p>
      )}
    </article>
  )
}

function LiveTvStartScreen({ image, title, onStart }: { image?: string | null; title: string; onStart: () => void }) {
  return (
    <button
      type="button"
      onClick={onStart}
      className="group relative block aspect-video w-full overflow-hidden bg-black text-left"
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

function LiveTvCard({ channel }: { channel: MediaItem }) {
  const name = channel.details?.name ?? channel.name

  return (
    <a href={liveTvSpaHref(channel)} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.025] group-hover:border-primary/60">
        <MediaThumbnail src={channel.poster_tv_image ?? channel.poster_image ?? channel.details?.thumbnail_image} alt={name} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/34 via-transparent to-transparent" />
        <Badge className="absolute left-3 top-3 rounded-sm bg-red-600 text-white">Live</Badge>
      </div>
      <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{name}</h3>
      {channel.details?.category ? <p className="mt-1 text-xs text-white/58">{channel.details.category}</p> : null}
    </a>
  )
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

function LiveTvChat({
  channelId,
  chat,
}: {
  channelId: string | number
  chat: LiveTvChatState | null
}) {
  const [message, setMessage] = useState('')
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
        {chat?.guest_name ? <span className="text-xs text-white/48">{chat.guest_name}</span> : null}
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
    </section>
  )
}

function normalizeScheduleItems(items: LiveTvScheduleItem[], now?: ProgramInfo | null) {
  const threshold = parseScheduleDate(now?.start_time)?.getTime() ?? Date.now()

  return items
    .map((item) => ({
      ...item,
      start: item.start_at ?? item.start_time ?? null,
      end: item.end_at ?? item.end_time ?? null,
    }))
    .filter((item) => item.title || item.start)
    .filter((item) => {
      const endTime = parseScheduleDate(item.end)?.getTime()
      const startTime = parseScheduleDate(item.start)?.getTime()

      if (endTime) return endTime >= threshold
      if (startTime) return startTime >= threshold

      return true
    })
    .sort((a, b) => (parseScheduleDate(a.start)?.getTime() ?? 0) - (parseScheduleDate(b.start)?.getTime() ?? 0))
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

function relatedChannels(channels: MediaItem[], current?: MediaItem) {
  return channels.filter((channel) => String(channel.id) !== String(current?.id)).slice(0, 12)
}

function formatTime(value?: string | null) {
  if (!value) return null

  const date = parseScheduleDate(value)
  if (!date) return null

  return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}

function parseScheduleDate(value?: string | null) {
  if (!value) return null

  const normalized = value.includes('T') ? value : value.replace(' ', 'T')
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return null

  return date
}

function formatScheduleTime(value?: string | null) {
  if (!value) return null

  const date = parseScheduleDate(value)
  if (!date) return null

  return date.toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}
