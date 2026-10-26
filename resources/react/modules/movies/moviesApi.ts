import { api } from '@/lib/api'
import type { ApiEnvelope, MediaItem } from '@/modules/home/types'

export async function loadMoviesPage(page = 1, perPage = 36, genreId = '', language = '') {
  const params = new URLSearchParams({
    is_ajax: '1',
    page: String(page),
    per_page: String(perPage),
  })

  if (genreId) params.set('genre_id', genreId)
  if (language) params.set('language', language)

  const response = await api.get<ApiEnvelope<MediaItem[]>>(`/api/v3/movie-list?${params.toString()}`)

  return {
    items: response.data ?? [],
    hasMore: Boolean(response.hasMore && (response.data?.length ?? 0) > 0),
  }
}
