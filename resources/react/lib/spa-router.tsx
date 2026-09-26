import * as React from 'react'
import { flushSync } from 'react-dom'

type SpaRouterContextValue = {
  path: string
  navigate: (to: string, options?: { replace?: boolean }) => void
}

const SpaRouterContext = React.createContext<SpaRouterContextValue | null>(null)

export function SpaRouter({ children }: { children: React.ReactNode }) {
  const [path, setPath] = React.useState(() => getCurrentPath())
  const pathRef = React.useRef(path)

  const commitPath = React.useCallback((nextPath: string) => {
    const currentPath = pathRef.current
    const changePage = () => {
      pathRef.current = nextPath
      flushSync(() => setPath(nextPath))
      window.scrollTo({ top: 0, behavior: 'instant' })
    }

    if (!shouldAnimateNavigation(currentPath, nextPath)) {
      changePage()
      return
    }

    if (document.startViewTransition) {
      document.startViewTransition(changePage)
      return
    }

    const root = document.getElementById('react-modernization-root')
    if (!root?.animate) {
      changePage()
      return
    }

    root.getAnimations().forEach((animation) => animation.cancel())
    const exit = root.animate(
      [
        { opacity: 1, transform: 'translateY(0)' },
        { opacity: 0, transform: 'translateY(-6px)' },
      ],
      { duration: 140, easing: 'ease-in', fill: 'forwards' },
    )

    void exit.finished.then(() => {
      changePage()
      root.getAnimations().forEach((animation) => animation.cancel())
      root.animate(
        [
          { opacity: 0, transform: 'translateY(8px)' },
          { opacity: 1, transform: 'translateY(0)' },
        ],
        { duration: 220, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
      )
    }).catch(() => undefined)
  }, [])

  const navigate = React.useCallback((to: string, options: { replace?: boolean } = {}) => {
    const nextUrl = new URL(to, window.location.origin)

    if (!isReactFrontendPath(nextUrl.pathname)) {
      window.location.href = nextUrl.toString()
      return
    }

    const nextPath = `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`
    const currentPath = getCurrentPath()

    if (nextPath === currentPath) {
      return
    }

    if (options.replace) {
      window.history.replaceState(null, '', nextPath)
    } else {
      window.history.pushState(null, '', nextPath)
    }

    commitPath(nextPath)
  }, [commitPath])

  React.useEffect(() => {
    const handlePopState = () => commitPath(getCurrentPath())

    const handleDocumentClick = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
        return
      }

      const target = event.target instanceof Element ? event.target.closest('a[href]') : null
      if (!(target instanceof HTMLAnchorElement)) {
        return
      }

      if (target.target && target.target !== '_self') {
        return
      }

      if (target.hasAttribute('download')) {
        return
      }

      const url = new URL(target.href)

      if (url.origin !== window.location.origin || !isReactFrontendPath(url.pathname)) {
        return
      }

      event.preventDefault()
      navigate(`${url.pathname}${url.search}${url.hash}`)
    }

    window.addEventListener('popstate', handlePopState)
    document.addEventListener('click', handleDocumentClick)

    return () => {
      window.removeEventListener('popstate', handlePopState)
      document.removeEventListener('click', handleDocumentClick)
    }
  }, [commitPath, navigate])

  const value = React.useMemo(() => ({ path, navigate }), [navigate, path])

  return <SpaRouterContext.Provider value={value}>{children}</SpaRouterContext.Provider>
}

export function useSpaPath() {
  return React.useContext(SpaRouterContext)?.path ?? getCurrentPath()
}

export function useSpaNavigate() {
  return React.useContext(SpaRouterContext)?.navigate ?? ((to: string) => {
    window.location.href = to
  })
}

function getCurrentPath() {
  return `${window.location.pathname}${window.location.search}${window.location.hash}`
}

function shouldAnimateNavigation(currentPath: string, nextPath: string) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return false
  }

  const currentPathname = currentPath.split(/[?#]/)[0]
  const nextPathname = nextPath.split(/[?#]/)[0]

  return currentPathname !== nextPathname
    && !currentPathname.startsWith('/video-embed')
    && !nextPathname.startsWith('/video-embed')
}

function isReactFrontendPath(pathname: string) {
  return !isServicePath(pathname)
}

function isServicePath(pathname: string) {
  return [
    '/api',
    '/app',
    '/admin',
    '/auth',
    '/sanctum',
    '/livewire',
    '/storage',
    '/build',
    '/vendor',
    '/install',
    '/_ignition',
    '/video/stream',
    '/video/1',
  ].some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`))
}
