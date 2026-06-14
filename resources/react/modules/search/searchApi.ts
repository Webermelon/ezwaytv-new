import { api } from '@/lib/api'
import type { MediaItem } from '@/modules/home/types'

export type SearchKind = 'video' | 'livetv' | 'ondemand'

export type SearchResult = MediaItem & {
  searchKind: SearchKind
  href: string
}

type SearchResponse = {
  status?: boolean
  html?: string
  ondemandList?: Array<{
    id?: number | string
    name?: string
    username?: string
    avatar_url?: string | null
    profile_url?: string | null
  }>
}

export async function loadSearchResults(query: string, types: string[] = []) {
  const params = new URLSearchParams({
    search: query,
    is_ajax: '1',
    per_page: '48',
  })

  if (types.length > 0) {
    params.set('type', types.join(','))
  }

  const response = await api.get<SearchResponse>(`/api/v3/get-search-data?${params.toString()}`)

  return normalizeSearchResponse(response)
}

function normalizeSearchResponse(response: SearchResponse) {
  const htmlResults = parseHtmlResults(response.html ?? '')
  const ondemandResults = (response.ondemandList ?? []).map((item): SearchResult => ({
    id: item.id ?? item.username ?? item.name ?? Math.random(),
    name: item.name ?? 'On Demand Channel',
    username: item.username,
    poster_image: item.avatar_url ?? undefined,
    avatar_image_url: item.avatar_url ?? undefined,
    profile_url: item.profile_url ?? undefined,
    type: 'ondemand',
    searchKind: 'ondemand',
    href: item.username ? `/on-demand/${item.username}` : '/on-demand',
  }))

  return uniqueResults([...htmlResults, ...ondemandResults])
}

function parseHtmlResults(html: string) {
  if (!html.trim()) return []

  const document = new DOMParser().parseFromString(html, 'text/html')
  const results: SearchResult[] = []

  document.querySelectorAll<HTMLElement>('[data-movie-data]').forEach((element) => {
    const raw = element.getAttribute('data-movie-data')
    if (!raw) return

    try {
      const item = JSON.parse(decodeHtml(raw)) as MediaItem
      if (!item?.id) return

      results.push({
        ...item,
        searchKind: 'video',
        href: item.slug ? `/video-details/${item.slug}?autoplay=1&is_search=1` : '/videos',
      })
    } catch {
      // Ignore malformed legacy card data.
    }
  })

  document.querySelectorAll<HTMLAnchorElement>('a[href*="/livetv-details/"], a[href*="/livetv/"]').forEach((anchor) => {
    const title = anchor.querySelector('img')?.getAttribute('alt')
      ?? anchor.closest('.col')?.querySelector('h6')?.textContent?.trim()
      ?? anchor.textContent?.trim()
      ?? 'Live TV'
    const href = anchor.getAttribute('href') ?? '/livetv'
    const slug = href.split('/').filter(Boolean).pop() ?? ''
    const image = anchor.querySelector('img')?.getAttribute('src') ?? undefined

    if (!slug) return

    results.push({
      id: slug,
      name: title,
      slug,
      poster_image: image,
      poster_tv_image: image,
      type: 'livetv',
      searchKind: 'livetv',
      href: `/livetv/${slug}`,
    })
  })

  return uniqueResults(results)
}

function decodeHtml(value: string) {
  const textarea = document.createElement('textarea')
  textarea.innerHTML = value
  return textarea.value
}

function uniqueResults(items: SearchResult[]) {
  const seen = new Set<string>()

  return items.filter((item) => {
    const key = `${item.searchKind}-${item.id}-${item.slug ?? item.username ?? item.name}`
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}
