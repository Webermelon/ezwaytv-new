import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'

import { api } from './api'

type AppConfiguration = {
  app_name?: string | null
  app_logo?: string | null
  app_light_logo?: string | null
  app_mini_logo?: string | null
  app_favicon?: string | null
  theme_color?: string | null
  root_colors?: string | null | Record<string, string>
}

type BrandingState = {
  appName: string
  logo: string | null
  miniLogo: string | null
  favicon: string | null
  themeColor: string | null
  loading: boolean
}

const fallbackBranding: BrandingState = {
  appName: 'eZWay TV',
  logo: null,
  miniLogo: null,
  favicon: null,
  themeColor: null,
  loading: true,
}

const presetPrimaryColors: Record<string, string> = {
  default: '#7093e5',
  'color-1': '#00c3f9',
  'color-2': '#fd8d00',
  'color-3': '#db5363',
  'color-4': '#ea6a12',
  'color-5': '#e586b3',
  gold: '#c9930a',
}

const BrandingContext = createContext<BrandingState>(fallbackBranding)

export function BrandingProvider({ children }: { children: ReactNode }) {
  const brandingQuery = useQuery({
    queryKey: ['app-configuration'],
    queryFn: () => api.get<AppConfiguration>('/api/v3/app-configuration'),
    staleTime: 10 * 60_000,
  })
  const settings = brandingQuery.data

  useEffect(() => {
    if (!settings) return

    const primaryColor = resolvePrimaryColor(settings)
    applyPrimaryColor(primaryColor)
    applyFavicon(settings.app_favicon)
    document.title = cleanString(settings.app_name) ?? fallbackBranding.appName
  }, [settings])

  const value = useMemo<BrandingState>(() => {
    if (!settings) {
      return { ...fallbackBranding, loading: brandingQuery.isLoading }
    }

    return {
      appName: cleanString(settings.app_name) ?? fallbackBranding.appName,
      logo: cleanString(settings.app_logo) ?? cleanString(settings.app_light_logo),
      miniLogo: cleanString(settings.app_mini_logo),
      favicon: cleanString(settings.app_favicon),
      themeColor: resolvePrimaryColor(settings),
      loading: brandingQuery.isLoading,
    }
  }, [brandingQuery.isLoading, settings])

  return <BrandingContext.Provider value={value}>{children}</BrandingContext.Provider>
}

export function useBranding() {
  return useContext(BrandingContext)
}

function resolvePrimaryColor(settings: AppConfiguration) {
  const customColors = parseRootColors(settings.root_colors)
  const customPrimary = cleanString(customColors['--bs-primary']) ?? cleanString(customColors['--primary'])
  if (customPrimary) return customPrimary

  const themeColor = cleanString(settings.theme_color)
  if (themeColor?.startsWith('#')) return themeColor
  if (themeColor && presetPrimaryColors[themeColor]) return presetPrimaryColors[themeColor]

  return null
}

function parseRootColors(rootColors: AppConfiguration['root_colors']) {
  if (!rootColors) return {}
  if (typeof rootColors === 'object') return rootColors

  try {
    const parsed = JSON.parse(rootColors)
    return parsed && typeof parsed === 'object' ? parsed as Record<string, string> : {}
  } catch {
    return {}
  }
}

function applyPrimaryColor(color: string | null) {
  const normalized = normalizeHex(color)
  if (!normalized) return

  const hsl = hexToHsl(normalized)
  const rgb = hexToRgb(normalized)
  if (!hsl || !rgb) return

  const root = document.documentElement
  root.style.setProperty('--primary', hsl)
  root.style.setProperty('--ring', hsl)
  root.style.setProperty('--bs-primary', normalized)
  root.style.setProperty('--bs-primary-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`)
}

function applyFavicon(favicon?: string | null) {
  const href = cleanString(favicon)
  if (!href) return

  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }

  link.href = href
}

function cleanString(value?: string | null) {
  const cleaned = typeof value === 'string' ? value.trim() : ''
  return cleaned.length > 0 ? cleaned : null
}

function normalizeHex(value?: string | null) {
  const color = cleanString(value)
  if (!color) return null

  const match = color.match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i)
  if (!match) return null

  const hex = match[1]
  if (hex.length === 3) {
    return `#${hex.split('').map((digit) => digit + digit).join('')}`
  }

  return `#${hex}`
}

function hexToRgb(hex: string) {
  const value = hex.replace('#', '')
  const numeric = Number.parseInt(value, 16)
  if (Number.isNaN(numeric)) return null

  return {
    r: (numeric >> 16) & 255,
    g: (numeric >> 8) & 255,
    b: numeric & 255,
  }
}

function hexToHsl(hex: string) {
  const rgb = hexToRgb(hex)
  if (!rgb) return null

  const r = rgb.r / 255
  const g = rgb.g / 255
  const b = rgb.b / 255
  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  let h = 0
  let s = 0
  const l = (max + min) / 2

  if (max !== min) {
    const delta = max - min
    s = l > 0.5 ? delta / (2 - max - min) : delta / (max + min)
    switch (max) {
      case r:
        h = (g - b) / delta + (g < b ? 6 : 0)
        break
      case g:
        h = (b - r) / delta + 2
        break
      default:
        h = (r - g) / delta + 4
    }
    h /= 6
  }

  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`
}
