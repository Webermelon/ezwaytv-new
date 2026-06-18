import { api } from '@/lib/api'

export type UserProfile = {
  id: number
  user_id?: number
  name: string
  avatar?: string | null
  is_active?: number | boolean
  is_child_profile?: number | boolean
  profile_pin?: string | null
  is_protected_profile?: number | boolean
}

type ProfileListResponse = {
  status?: boolean
  data?: {
    data?: UserProfile[]
  } | UserProfile[]
  message?: string
}

export async function loadUserProfiles() {
  const response = await api.get<ProfileListResponse>('/api/user-profile-list?per_page=20')

  return unwrapProfiles(response)
}

export async function saveUserProfile(input: {
  id?: number
  name: string
  avatar?: string
  file?: File | null
}) {
  const body = new FormData()
  body.set('name', input.name)
  body.set('is_child_profile', '0')

  if (input.id) {
    body.set('id', String(input.id))
  }

  if (input.avatar) {
    body.set('avatar', input.avatar)
  }

  if (input.file) {
    body.set('file_url', input.file)
  }

  const response = await api.post<ProfileListResponse>('/api/save-userprofile', body)

  return {
    profiles: unwrapProfiles(response),
    message: response.message,
  }
}

export async function deleteUserProfile(profileId: number) {
  const response = await api.post<ProfileListResponse>('/api/delete-userprofile', {
    profile_id: profileId,
  })

  return response.message
}

export async function selectUserProfile(profileId: number) {
  const response = await api.post<ProfileListResponse>(`/api/select-userprofile/${profileId}`, {})

  return {
    profiles: unwrapProfiles(response),
    message: response.message,
  }
}

function unwrapProfiles(response: ProfileListResponse) {
  if (Array.isArray(response.data)) {
    return response.data
  }

  if (Array.isArray(response.data?.data)) {
    return response.data.data
  }

  return []
}
