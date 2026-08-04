type CapacitorBridge = {
  getPlatform?: () => string
  isNativePlatform?: () => boolean
}

declare global {
  interface Window {
    Capacitor?: CapacitorBridge
    ezwayIosRestrictionsEnabled?: boolean
  }
}

/** True only inside the native Capacitor iOS shell, never ordinary iOS Safari. */
export function isNativeIosApp() {
  if (typeof window === 'undefined') return false
  if (window.ezwayIosRestrictionsEnabled === false) return false

  const capacitor = window.Capacitor
  if (!capacitor) return false

  const platform = capacitor.getPlatform?.().toLowerCase()
  const isNative = capacitor.isNativePlatform?.()

  // Older Capacitor bridges may not expose isNativePlatform, but do expose ios.
  return platform === 'ios' && isNative !== false
}

export function isIosRestrictedPath(pathname: string) {
  const restrictedPrefixes = [
    '/login',
    '/register',
    '/forget-password',
    '/subscription-plan',
    '/payment-history',
    '/orders',
    '/music',
    '/upload-your-videoes',
    '/select-plan',
    '/pay-per-view',
  ]

  return restrictedPrefixes.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`))
}
