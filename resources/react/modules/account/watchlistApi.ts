import { api } from '@/lib/api'
import type { MediaItem } from '@/modules/home/types'

export type WatchlistItem = MediaItem & {
  entertainment_id?: number | string
  entertainment_type?: string
  is_ondemand_video?: boolean | number
  ondemand_channel_id?: number | string | null
  ondemand_channel_name?: string | null
  ondemand_channel_username?: string | null
  thumbnail_image?: string | null
  movie_access?: string | null
  is_watch_list?: boolean | number
}

type WatchlistResponse = {
  status?: boolean
  data?: WatchlistItem[]
  meta?: {
    current_page?: number
    last_page?: number
    total?: number
    has_more?: boolean
  }
  message?: string
}

export async function loadWatchlist(type = 'all', page = 1, perPage = 24) {
  const params = new URLSearchParams({
    type,
    page: String(page),
    per_page: String(perPage),
  })
  const response = await api.get<WatchlistResponse>(`/account/watchlist-data?${params.toString()}`)

  return {
    items: response.data ?? [],
    meta: response.meta ?? {},
  }
}

export async function removeWatchlistItem(item: WatchlistItem) {
  const entertainmentId = item.entertainment_id ?? item.details?.id ?? item.id
  const type = item.entertainment_type ?? item.type ?? item.details?.type

  const response = await removeFromWatchlist(entertainmentId, type)

  return response
}

export async function addToWatchlist(entertainmentId?: string | number, type?: string | null) {
  const response = await api.post<{ status?: boolean; message?: string }>('/account/watchlist/save', {
    entertainment_id: entertainmentId,
    type,
  })

  return response.message
}

export async function removeFromWatchlist(entertainmentId?: string | number, type?: string | null) {
  const response = await api.post<{ status?: boolean; message?: string }>('/account/watchlist/delete', {
    entertainment_id: entertainmentId,
    type,
  })

  return response.message
}
