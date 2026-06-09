import { api } from '@/lib/api'
import type { ApiEnvelope, MediaItem } from '@/modules/home/types'

export async function loadVideosPage(categorySlug = '', page = 1, perPage = 48) {
  const params = new URLSearchParams({
    is_ajax: '1',
    page: String(page),
    per_page: String(perPage),
  })

  if (categorySlug) {
    params.set('category_slug', categorySlug)
  }

  const response = await api.get<ApiEnvelope<MediaItem[]>>(`/api/v3/video-list?${params.toString()}`)

  return {
    items: response.data ?? [],
    hasMore: Boolean(response.hasMore && (response.data?.length ?? 0) > 0),
  }
}

export async function loadVideos(categorySlug = '') {
  const videos: MediaItem[] = []
  let page = 1
  let hasMore = true

  while (hasMore && page <= 50) {
    const response = await loadVideosPage(categorySlug, page, 48)
    videos.push(...response.items)
    hasMore = response.hasMore
    page += 1
  }

  return uniqueById(videos)
}

function uniqueById(items: MediaItem[]) {
  const seen = new Set<string | number>()

  return items.filter((item) => {
    if (seen.has(item.id)) return false
    seen.add(item.id)
    return true
  })
}
