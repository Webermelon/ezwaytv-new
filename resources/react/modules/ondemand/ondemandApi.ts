import { api } from '@/lib/api'
import type { ApiEnvelope, MediaItem, PaginatedData } from '@/modules/home/types'

export async function loadOnDemandChannels() {
  const response = await api.get<ApiEnvelope<PaginatedData<MediaItem>>>('/api/v3/ondemand?per_page=50')

  return response.data?.data ?? []
}

export async function loadOnDemandProfile(username: string) {
  const [profile, videos] = await Promise.all([
    api.get<ApiEnvelope<MediaItem>>(`/api/v3/ondemand/${encodeURIComponent(username)}`),
    api.get<ApiEnvelope<PaginatedData<MediaItem>>>(`/api/v3/ondemand/${encodeURIComponent(username)}/videos?per_page=50`),
  ])

  return {
    profile: profile.data ?? null,
    videos: videos.data?.data ?? [],
  }
}
