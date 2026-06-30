import { useCallback, useEffect, useRef, useState } from 'react'
import videojs from 'video.js'
import 'video.js/dist/video-js.css'
import 'videojs-contrib-ads'
import 'videojs-contrib-ads/dist/videojs-contrib-ads.css'
import 'videojs-ima'
import 'videojs-ima/dist/videojs.ima.css'

import type { VideoAd } from './videoDetailApi'

type VideoJsPlayerProps = {
  source: string
  poster?: string | null
  autoplay?: boolean
  muted?: boolean
  playTrigger?: number
  unmuteOnPlayTrigger?: boolean
  vastAds: VideoAd[]
  isLive?: boolean
  onPlay?: () => void
  onTimeUpdate?: (seconds: number) => void
  onPause?: (seconds: number) => void
  onEnded?: (seconds: number) => void
}

type VideoJsImaPlayer = videojs.Player & {
  ima?: ((options?: Record<string, unknown>) => void) & {
    initializeAdDisplayContainer?: () => void
    requestAds?: () => void
  }
}

type VastCreative = {
  mediaUrl: string
  mimeType: string
  title?: string | null
  advertiser?: string | null
  durationSeconds?: number | null
  skippable: boolean
  skipAfterSeconds?: number | null
  clickThrough?: string | null
}

type AdUiState = {
  visible: boolean
  title?: string | null
  advertiser?: string | null
  clickThrough?: string | null
  remainingSeconds?: number | null
  durationSeconds?: number | null
  elapsedSeconds?: number
  skippable: boolean
  skipAfterSeconds?: number | null
  canSkip: boolean
}

export function VideoJsPlayer({
  source,
  poster,
  autoplay = false,
  muted = false,
  playTrigger = 0,
  unmuteOnPlayTrigger = false,
  vastAds,
  isLive = false,
  onPlay,
  onTimeUpdate,
  onPause,
  onEnded,
}: VideoJsPlayerProps) {
  const videoNodeRef = useRef<HTMLVideoElement | null>(null)
  const playerRef = useRef<VideoJsImaPlayer | null>(null)
  const lastPlayTriggerRef = useRef(0)
  const adTagUrlRef = useRef<string | null>(null)
  const initializedAdTagRef = useRef<string | null>(null)
  const isAdPlayingRef = useRef(false)
  const pendingPlayRef = useRef(autoplay)
  const prerollStateRef = useRef<'idle' | 'loading' | 'ready' | 'playing' | 'done'>('idle')
  const prerollCreativeRef = useRef<VastCreative | null>(null)
  const finishPrerollRef = useRef<(() => void) | null>(null)
  const onPlayRef = useRef(onPlay)
  const onTimeUpdateRef = useRef(onTimeUpdate)
  const onPauseRef = useRef(onPause)
  const onEndedRef = useRef(onEnded)
  const [adUi, setAdUi] = useState<AdUiState>({ visible: false, skippable: false, canSkip: false })
  const hasVastAds = vastAds.length > 0

  const startPreroll = useCallback((player: VideoJsImaPlayer, creative: VastCreative) => {
    if (prerollStateRef.current === 'playing' || prerollStateRef.current === 'done') return

    prerollStateRef.current = 'playing'
    isAdPlayingRef.current = true
    const wasMuted = player.muted()
    if (autoplay) {
      player.muted(true)
    }
    player.controls(false)
    player.poster('')
    player.src({ src: creative.mediaUrl, type: creative.mimeType })
    let durationTimer: number | null = null

    setAdUi({
      visible: true,
      title: creative.title,
      advertiser: creative.advertiser,
      clickThrough: creative.clickThrough,
      remainingSeconds: creative.durationSeconds,
      durationSeconds: creative.durationSeconds,
      elapsedSeconds: 0,
      skippable: creative.skippable,
      skipAfterSeconds: creative.skipAfterSeconds,
      canSkip: creative.skippable && (creative.skipAfterSeconds ?? 0) <= 0,
    })

    let finished = false
    const finishPreroll = () => {
      if (finished) return
      finished = true
      if (durationTimer) {
        window.clearTimeout(durationTimer)
        durationTimer = null
      }
      finishPrerollRef.current = null
      player.off('ended', finishPreroll)
      player.off('timeupdate', updateAdUi)
      isAdPlayingRef.current = false
      prerollStateRef.current = 'done'
      setAdUi({ visible: false, skippable: false, canSkip: false })
      player.muted(autoplay ? true : wasMuted)
      player.controls(true)
      playMainSource(player, source, wasMuted)
    }

    finishPrerollRef.current = finishPreroll
    if (creative.durationSeconds && creative.durationSeconds > 0) {
      durationTimer = window.setTimeout(finishPreroll, creative.durationSeconds * 1000)
    }
    const updateAdUi = () => {
      const currentTime = player.currentTime() ?? 0
      const remainingSeconds = typeof creative.durationSeconds === 'number'
        ? Math.max(0, Math.ceil(creative.durationSeconds - currentTime))
        : null
      const canSkip = creative.skippable && currentTime >= (creative.skipAfterSeconds ?? 0)

      setAdUi((current) => ({
        ...current,
        remainingSeconds,
        elapsedSeconds: currentTime,
        canSkip,
      }))
    }

    player.one('ended', finishPreroll)
    player.on('timeupdate', updateAdUi)
    const playResult = player.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {
        finishPreroll()
      })
    }
  }, [autoplay, source])

  useEffect(() => {
    onPlayRef.current = onPlay
    onTimeUpdateRef.current = onTimeUpdate
    onPauseRef.current = onPause
    onEndedRef.current = onEnded
  }, [onEnded, onPause, onPlay, onTimeUpdate])

  useEffect(() => {
    if (!videoNodeRef.current) return

    const player = videojs(videoNodeRef.current, {
      autoplay: autoplay && !hasVastAds,
      controls: true,
      fill: true,
      fluid: false,
      liveui: false,
      muted,
      preload: 'auto',
      poster: poster ?? undefined,
      sources: [
        {
          src: source,
          type: guessMimeType(source),
        },
      ],
      controlBar: {
        pictureInPictureToggle: true,
      },
    }) as VideoJsImaPlayer

    if (isLive) {
      player.addClass('vjs-ezway-live')
      player.duration(Number.POSITIVE_INFINITY)
    }

    playerRef.current = player

    const handlePlay = () => {
      if (isAdPlayingRef.current) return

      if (adTagUrlRef.current && typeof player.ima?.initializeAdDisplayContainer === 'function') {
        try {
          player.ima.initializeAdDisplayContainer()
        } catch {
          // IMA may already have initialized the display container.
        }
      }

      onPlayRef.current?.()
    }

    const handleTimeUpdate = () => {
      if (isAdPlayingRef.current) return

      onTimeUpdateRef.current?.(player.currentTime() ?? 0)
    }

    const handlePause = () => {
      if (isAdPlayingRef.current) return

      onPauseRef.current?.(player.currentTime() ?? 0)
    }

    const handleEnded = () => {
      if (isAdPlayingRef.current) return

      onEndedRef.current?.(player.currentTime() ?? player.duration() ?? 0)
    }

    const resumeContent = () => {
      if (player.paused()) {
        const result = player.play()
        if (result && typeof result.catch === 'function') {
          result.catch(() => undefined)
        }
      }
    }

    player.on('play', handlePlay)
    player.on('timeupdate', handleTimeUpdate)
    player.on('pause', handlePause)
    player.on('ended', handleEnded)
    player.on('adserror', resumeContent)
    player.on('adtimeout', resumeContent)
    player.on('nopreroll', resumeContent)

    return () => {
      player.off('play', handlePlay)
      player.off('timeupdate', handleTimeUpdate)
      player.off('pause', handlePause)
      player.off('ended', handleEnded)
      player.off('adserror', resumeContent)
      player.off('adtimeout', resumeContent)
      player.off('nopreroll', resumeContent)
      player.dispose()
      playerRef.current = null
      adTagUrlRef.current = null
      initializedAdTagRef.current = null
      isAdPlayingRef.current = false
      pendingPlayRef.current = false
      prerollStateRef.current = 'idle'
      prerollCreativeRef.current = null
      finishPrerollRef.current = null
    }
  }, [autoplay, hasVastAds, isLive, muted, poster, source])

  useEffect(() => {
    const player = playerRef.current
    const selectedVastAd = resolveVastAd(vastAds)
    const adTagUrl = normalizeVastUrl(selectedVastAd?.url ?? selectedVastAd?.vast_url ?? selectedVastAd?.redirect_url ?? null)
    let cancelled = false

    adTagUrlRef.current = adTagUrl
    pendingPlayRef.current = autoplay

    if (!player || !adTagUrl || initializedAdTagRef.current === adTagUrl) {
      if (autoplay && !adTagUrl && player) {
        const playResult = player.play()
        if (playResult && typeof playResult.catch === 'function') {
          playResult.catch(() => undefined)
        }
      }
      return () => {
        cancelled = true
      }
    }

    initializedAdTagRef.current = adTagUrl
    prerollStateRef.current = 'loading'

    loadVastCreative(adTagUrl, selectedVastAd)
      .then((creative) => {
        if (cancelled || !playerRef.current) return

        if (!creative) {
          prerollStateRef.current = 'done'
          if (pendingPlayRef.current) {
            const playResult = playerRef.current.play()
            if (playResult && typeof playResult.catch === 'function') {
              playResult.catch(() => undefined)
            }
          }
          return
        }

        prerollCreativeRef.current = creative
        prerollStateRef.current = 'ready'

        if (pendingPlayRef.current) {
          startPreroll(playerRef.current, creative)
        }
      })
      .catch(() => {
        if (cancelled || !playerRef.current) return
        prerollStateRef.current = 'done'
        if (pendingPlayRef.current) {
          const playResult = playerRef.current.play()
          if (playResult && typeof playResult.catch === 'function') {
            playResult.catch(() => undefined)
          }
        }
      })

    return () => {
      cancelled = true
    }
  }, [autoplay, source, startPreroll, vastAds])

  useEffect(() => {
    const player = playerRef.current
    if (!player || hasVastAds) return

    if (autoplay) {
      const playResult = player.play()
      if (playResult && typeof playResult.catch === 'function') {
        playResult.catch(() => undefined)
      }
    }
  }, [autoplay, hasVastAds])

  useEffect(() => {
    if (playTrigger === lastPlayTriggerRef.current) return

    lastPlayTriggerRef.current = playTrigger
    const player = playerRef.current
    if (!player) return

    if (unmuteOnPlayTrigger) {
      player.muted(false)
    }

    pendingPlayRef.current = true

    if (prerollStateRef.current === 'ready' && prerollCreativeRef.current) {
      startPreroll(player, prerollCreativeRef.current)
      return
    }

    if (prerollStateRef.current === 'loading' || prerollStateRef.current === 'playing') {
      return
    }

    const playResult = player.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => undefined)
    }
  }, [playTrigger, startPreroll, unmuteOnPlayTrigger])

  return (
    <div className="ez-video-stage relative h-full min-h-0 w-full overflow-hidden bg-[#050505] sm:min-h-[320px]">
      {poster ? (
        <>
          <img src={poster} alt="" className="pointer-events-none absolute inset-0 h-full w-full scale-110 object-cover opacity-20 blur-2xl" />
          <div className="pointer-events-none absolute inset-0 bg-black/62" />
        </>
      ) : (
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.08),transparent_42%),#050505]" />
      )}
      <video id="react-video-player" ref={videoNodeRef} className="video-js vjs-big-play-centered vjs-theme-ezway relative z-10 h-full w-full" playsInline />
      {adUi.visible ? (
        <div className="pointer-events-none absolute inset-0 z-30 flex flex-col justify-between bg-gradient-to-b from-black/20 via-transparent to-black/58 p-4 sm:p-5">
          <div className="flex items-center justify-between gap-4 rounded-md border border-white/14 bg-black/38 px-3 py-2 shadow-lg backdrop-blur-md">
            <div className="min-w-0 text-sm font-semibold text-white/88">
              Your video will resume
              {typeof adUi.remainingSeconds === 'number' ? ` in ${Math.max(0, Math.ceil(adUi.remainingSeconds))} seconds` : ' soon'}
            </div>
            {adUi.clickThrough ? (
              <a
                href={adUi.clickThrough}
                target="_blank"
                rel="noreferrer"
                className="pointer-events-auto shrink-0 text-sm font-black text-white hover:text-primary"
              >
                Learn more &gt;&gt;
              </a>
            ) : null}
          </div>

          <div className="flex items-end justify-between gap-3">
            <span className="rounded-sm bg-black/60 px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white/76 backdrop-blur">
              Advertisement
            </span>
            {adUi.skippable ? (
              <button
                type="button"
                disabled={!adUi.canSkip}
                onClick={() => {
                  finishPrerollRef.current?.()
                }}
                className={[
                  'pointer-events-auto rounded-md border px-4 py-2 text-sm font-black shadow-lg transition',
                  adUi.canSkip
                    ? 'cursor-pointer border-white/20 bg-white text-black hover:bg-white/88'
                    : 'cursor-not-allowed border-white/12 bg-black/72 text-white/58',
                ].join(' ')}
              >
                {adUi.canSkip
                  ? 'Skip Ad'
                  : `Skip in ${Math.max(0, Math.ceil((adUi.skipAfterSeconds ?? 0) - (adUi.elapsedSeconds ?? 0)))}`}
              </button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  )
}

function resolveVastAd(vastAds: VideoAd[]) {
  return vastAds.find((ad) => ad.url || ad.vast_url || ad.redirect_url) ?? null
}

async function loadVastCreative(adTagUrl: string, ad?: VideoAd | null) {
  const response = await fetch(adTagUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/xml,text/xml,*/*' },
  })

  if (!response.ok) return null

  const xml = await response.text()
  const documentXml = new DOMParser().parseFromString(xml, 'application/xml')
  const mediaFile = Array.from(documentXml.getElementsByTagName('MediaFile'))
    .find((node) => node.textContent?.trim())

  const mediaUrl = mediaFile?.textContent?.trim()
  if (!mediaUrl) return null

  const adminSkipAfterSeconds = parseTimecode(ad?.skip_after)
  const adminSkipEnabled = isEnabled(ad?.enable_skip) || adminSkipAfterSeconds !== null
  const xmlSkipAfterSeconds = parseTimecode(documentXml.getElementsByTagName('Linear')[0]?.getAttribute('skipoffset'))
  const skippable = adminSkipEnabled || xmlSkipAfterSeconds !== null

  return {
    mediaUrl,
    mimeType: mediaFile?.getAttribute('type') ?? guessMimeType(mediaUrl),
    title: documentXml.getElementsByTagName('AdTitle')[0]?.textContent?.trim() ?? null,
    advertiser: documentXml.getElementsByTagName('Advertiser')[0]?.textContent?.trim() ?? null,
    durationSeconds: parseTimecode(documentXml.getElementsByTagName('Duration')[0]?.textContent?.trim()),
    skippable,
    skipAfterSeconds: adminSkipEnabled ? adminSkipAfterSeconds : xmlSkipAfterSeconds,
    clickThrough: documentXml.getElementsByTagName('ClickThrough')[0]?.textContent?.trim() ?? null,
  } satisfies VastCreative
}

function parseTimecode(value?: string | number | null) {
  if (!value) return null
  const normalized = String(value).trim()
  if (!normalized) return null
  if (/^\d+$/.test(normalized)) return Number(normalized)
  if (normalized.endsWith('%')) return null

  const parts = normalized.split(':').map(Number)
  if ((parts.length !== 2 && parts.length !== 3) || parts.some(Number.isNaN)) return null

  if (parts.length === 2) {
    return (parts[0] * 60) + parts[1]
  }

  return (parts[0] * 3600) + (parts[1] * 60) + parts[2]
}

function isEnabled(value: VideoAd['enable_skip']) {
  const normalized = String(value).trim().toLowerCase()

  return value === true || value === 1 || normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on'
}

function formatSeconds(value: number) {
  const seconds = Math.max(0, Math.ceil(value))
  const minutes = Math.floor(seconds / 60)
  const rest = String(seconds % 60).padStart(2, '0')

  return `${minutes}:${rest}`
}

function playMainSource(player: VideoJsImaPlayer, source: string, preferredMuted: boolean) {
  player.src({ src: source, type: guessMimeType(source) })
  player.load()

  const start = () => {
    const playResult = player.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {
        if (player.muted()) return

        player.muted(true)
        const mutedPlayResult = player.play()
        if (mutedPlayResult && typeof mutedPlayResult.catch === 'function') {
          mutedPlayResult.catch(() => undefined)
        }
      })
    }
  }

  player.one('playing', () => {
    if (preferredMuted) {
      player.muted(true)
    }
  })
  player.one('loadedmetadata', start)
  player.one('canplay', start)

  window.setTimeout(start, 250)
}

function normalizeVastUrl(url?: string | null) {
  if (!url) return null

  try {
    const parsed = new URL(url)
    const localHosts = ['127.0.0.1', 'localhost']
    const productionHosts = ['ezway.tv', 'www.ezway.tv']

    const isSameEzwayNetwork = window.location.hostname === 'react.ezway.tv' && productionHosts.includes(parsed.hostname)

    if ((localHosts.includes(window.location.hostname) || isSameEzwayNetwork) && productionHosts.includes(parsed.hostname)) {
      return `${window.location.origin}${parsed.pathname}${parsed.search}`
    }
  } catch {
    return url
  }

  return url
}

function guessMimeType(source: string) {
  const path = source.split('?')[0].toLowerCase()

  if (path.endsWith('.m3u8')) return 'application/x-mpegURL'
  if (path.endsWith('.webm')) return 'video/webm'
  if (path.endsWith('.mov')) return 'video/quicktime'

  return 'video/mp4'
}
