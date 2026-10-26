import { api } from '@/lib/api'
import type { ApiEnvelope, MediaItem } from '@/modules/home/types'

export type EpisodeItem = MediaItem & {
  entertainment_id?: number | string
  season_id?: number | string
  episode_number?: number | string | null
  thumbnail_image?: string | null
  video_links?: Array<{ url?: string | null; type?: string | null; quality?: string | null }>
  tvshow_name?: string | null
  more_items?: MediaItem[]
}

export type SeasonItem = {
  season_id: number | string
  name: string
  short_desc?: string | null
  description?: string | null
  poster_image?: string | null
  poster_tv_image?: string | null
  total_episodes?: number
  episodes?: EpisodeItem[]
}

export type TvShowDetail = MediaItem & {
  thumbnail_image?: string | null
  release_year?: number | string | null
  content_rating?: string | null
  tvShowLinks?: SeasonItem[]
  more_items?: MediaItem[]
}

export async function loadTvShows(page = 1, perPage = 36, search = '') {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) })
  if (search) params.set('search', search)

  const response = await api.get<ApiEnvelope<MediaItem[]>>(`/api/v3/tvshow-list?${params.toString()}`)
  const items = response.data ?? []

  return { items, hasMore: items.length === perPage }
}

export async function loadTvShowDetail(id: string) {
  const params = new URLSearchParams({ tvshow_id: id })
  const response = await api.get<ApiEnvelope<TvShowDetail>>(`/api/tvshow-details?${params.toString()}`)
  return response.data
}

export async function loadEpisodeDetail(id: string) {
  const params = new URLSearchParams({ episode_id: id })
  const response = await api.get<ApiEnvelope<EpisodeItem & { tvShowLinks?: SeasonItem[]; genres?: Array<{ id?: number | string; name?: string | null }> }>>(`/api/episode-details?${params.toString()}`)
  return response.data
}
