import { api } from '@/lib/api'

export type CastCrewItem = {
  id: number | string
  name: string
  type?: string | null
  bio?: string | null
  designation?: string | null
  profile_image?: string | null
}

export type CastCrewDetail = {
  name: string
  birth_date?: string | null
  birth_place?: string | null
  total_movies?: number | null
  total_tv_show?: number | null
  rating?: number | null
  role?: string | null
  top_genres?: string | null
  profile_image?: string | null
  bio?: string | null
}

type ApiEnvelope<T> = {
  status: boolean
  data?: T
  message?: string
}

export async function loadCastCrewList(options: { search?: string; type?: string; perPage?: number } = {}) {
  const params = new URLSearchParams({
    per_page: String(options.perPage ?? 60),
  })

  if (options.search?.trim()) {
    params.set('search', options.search.trim())
  }

  if (options.type && options.type !== 'all') {
    params.set('type', options.type)
  }

  const response = await api.get<ApiEnvelope<CastCrewItem[]>>(`/api/castcrew-list?${params.toString()}`)

  return response.data ?? []
}

export async function loadCastCrewDetail(id: string | number, type?: string | null) {
  const types = type && type !== 'all' ? [type] : ['actor', 'director']

  for (const nextType of types) {
    try {
      const params = new URLSearchParams({ id: String(id), type: nextType })
      const response = await api.get<ApiEnvelope<CastCrewDetail>>(`/api/v3/cast-details?${params.toString()}`)

      if (response.data) return response.data
    } catch {
      // Try the next known cast/crew type.
    }
  }

  return null
}
