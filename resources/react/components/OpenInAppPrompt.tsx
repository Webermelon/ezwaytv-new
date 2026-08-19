import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ExternalLink, MonitorPlay, X } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { isNativeCapacitorApp } from '@/lib/native-platform'

const DISMISS_KEY = 'ezway_tv_continue_in_browser'
const OPEN_ATTEMPT_MS = 1400
const APP_SCHEME = 'ezwaytv'
const ANDROID_PACKAGE = 'tv.ezway.apps'
const DEFAULT_ANDROID_STORE = 'https://play.google.com/store/apps/details?id=tv.ezway.apps'
const DEFAULT_IOS_STORE = 'https://apps.apple.com/us/app/ezway-tv/id6446066875'
const DEFAULT_ICON = 'https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/logo_icon_6a216e0c6cca2.png'

type AppPromptConfiguration = {
  app_name?: string | null
  app_mini_logo?: string | null
  app_logo?: string | null
  mobile_app?: {
    playstore_url?: string | null
    appstore_url?: string | null
  } | null
}

export function OpenInAppPrompt() {
  const [visible, setVisible] = useState(() => shouldShowPrompt())
  const [showStoreAction, setShowStoreAction] = useState(false)
  const configQuery = useQuery({
    queryKey: ['open-in-app-config'],
    queryFn: () => api.get<AppPromptConfiguration>('/api/v3/app-configuration'),
    staleTime: 5 * 60_000,
    enabled: visible,
  })

  const currentUrl = useMemo(() => {
    if (typeof window === 'undefined') return ''

    const url = new URL(window.location.href)
    url.searchParams.set('open_app', '1')

    return url.toString()
  }, [])

  if (!visible) return null

  const isIos = isIosDevice()
  const appName = configQuery.data?.app_name || 'eZWay TV'
  const icon = configQuery.data?.app_mini_logo || configQuery.data?.app_logo || DEFAULT_ICON
  const storeUrl = isIos
    ? configQuery.data?.mobile_app?.appstore_url || DEFAULT_IOS_STORE
    : configQuery.data?.mobile_app?.playstore_url || DEFAULT_ANDROID_STORE

  function openApp() {
    if (typeof window === 'undefined') return

    setShowStoreAction(false)
    const fallbackTimer = window.setTimeout(() => {
      if (document.visibilityState === 'visible') {
        setShowStoreAction(true)
      }
    }, OPEN_ATTEMPT_MS)

    const clearFallback = () => window.clearTimeout(fallbackTimer)
    window.addEventListener('pagehide', clearFallback, { once: true })
    document.addEventListener('visibilitychange', clearFallback, { once: true })

    window.location.href = isIos ? buildIosDeepLink(currentUrl) : buildAndroidIntent(currentUrl)
  }

  function continueInBrowser() {
    try {
      window.sessionStorage.setItem(DISMISS_KEY, '1')
    } catch {
      // Ignore blocked storage; the close still applies for the current render.
    }
    setVisible(false)
  }

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/78 px-4 backdrop-blur-[3px] sm:hidden" role="dialog" aria-modal="true" aria-labelledby="open-tv-app-title">
      <div className="relative w-full max-w-[340px] rounded-[28px] border border-white/70 bg-white p-5 text-center text-[#191919] shadow-[0_24px_80px_rgba(0,0,0,0.32)]">
        <button
          type="button"
          onClick={continueInBrowser}
          className="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-full border border-black/10 bg-white text-black/62 shadow-sm"
          aria-label="Close open app prompt"
        >
          <X className="h-5 w-5" />
        </button>

        <div className="mx-auto mt-2 flex h-[74px] w-[74px] items-center justify-center overflow-hidden rounded-[22px] bg-[#111] p-2.5 shadow-[0_12px_28px_rgba(0,0,0,0.32)]">
          <img src={icon} alt="" className="h-full w-full object-contain" />
        </div>

        <h2 id="open-tv-app-title" className="mx-auto mt-6 max-w-[260px] text-[24px] font-black leading-tight tracking-normal text-[#202020]">
          Open {appName} in the app
        </h2>
        <p className="mx-auto mt-4 max-w-[265px] text-sm font-medium leading-6 text-black/55">
          Get the best experience for live TV, on-demand videos, channels, and subscriptions.
        </p>

        <div className="mt-7 space-y-3">
          <Button type="button" onClick={openApp} className="h-12 w-full rounded-full bg-[#191919] text-white hover:bg-black">
            <MonitorPlay className="h-5 w-5" />
            Open App
          </Button>
          <Button type="button" onClick={continueInBrowser} variant="outline" className="h-12 w-full rounded-full border-[#d4a843]/55 bg-white text-[#262626] hover:bg-[#fff8df]">
            Continue in browser
          </Button>
          {showStoreAction ? (
            <Button asChild type="button" variant="ghost" className="h-11 w-full rounded-full text-[#262626] hover:bg-black/5">
              <a href={storeUrl}>
                Get the App
                <ExternalLink className="h-4 w-4" />
              </a>
            </Button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

function shouldShowPrompt() {
  if (typeof window === 'undefined') return false
  if (isNativeCapacitorApp()) return false
  if (!isMobileBrowser()) return false
  if (isInstalledPwa()) return false

  try {
    return window.sessionStorage.getItem(DISMISS_KEY) !== '1'
  } catch {
    return true
  }
}

function isMobileBrowser() {
  if (typeof window === 'undefined') return false

  const userAgent = window.navigator.userAgent || ''
  const mobileUserAgent = /Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(userAgent)
  const coarsePointer = window.matchMedia?.('(pointer: coarse)').matches ?? false
  const narrowScreen = window.matchMedia?.('(max-width: 767px)').matches ?? false

  return mobileUserAgent || (coarsePointer && narrowScreen)
}

function isInstalledPwa() {
  if (typeof window === 'undefined') return false

  const standaloneDisplay = window.matchMedia?.('(display-mode: standalone)').matches ?? false
  const iosStandalone = 'standalone' in window.navigator && Boolean((window.navigator as Navigator & { standalone?: boolean }).standalone)

  return standaloneDisplay || iosStandalone
}

function isIosDevice() {
  if (typeof window === 'undefined') return false

  return /iPhone|iPad|iPod/i.test(window.navigator.userAgent || '')
}

function buildIosDeepLink(currentUrl: string) {
  return `${APP_SCHEME}://open?url=${encodeURIComponent(currentUrl)}`
}

function buildAndroidIntent(currentUrl: string) {
  const encodedUrl = encodeURIComponent(currentUrl)
  const fallback = encodeURIComponent(DEFAULT_ANDROID_STORE)

  return `intent://open?url=${encodedUrl}#Intent;scheme=${APP_SCHEME};package=${ANDROID_PACKAGE};S.browser_fallback_url=${fallback};end`
}
