import { api } from '@/lib/api'

export type AccountDevice = {
  id: number
  user_id?: number
  device_id?: string | null
  device_name?: string | null
  active_profile?: number | null
  platform?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type AccountPlan = {
  id?: number
  name?: string | null
  identifier?: string | null
  start_date?: string | null
  end_date?: string | null
  status?: string | null
  amount?: number | string | null
  price?: number | string | null
  total_price?: number | string | null
  duration?: string | null
}

export type AccountSettings = {
  is_parental_lock_enable?: number | boolean | null
  profile?: AccountProfile | null
  plan_details?: AccountPlan | null
  register_mobile_number?: string | null
  your_device?: AccountDevice | null
  other_device?: AccountDevice[] | null
}

export type AccountProfile = {
  id: number
  first_name?: string | null
  last_name?: string | null
  name?: string | null
  email?: string | null
  mobile?: string | null
  country_code?: string | null
  address?: string | null
  gender?: 'male' | 'female' | 'other' | string | null
  date_of_birth?: string | null
  avatar?: string | null
  login?: string | null
  login_type?: string | null
}

type ApiEnvelope<T> = {
  status?: boolean
  data?: T
  message?: string
  errors?: Record<string, string[] | string>
}

export async function loadAccountSettings() {
  const response = await api.get<ApiEnvelope<AccountSettings>>('/account/settings-data')

  return response.data ?? {}
}

export async function updateAccountProfile(input: {
  first_name: string
  last_name: string
  email: string
  mobile: string
  country_code?: string
  address?: string
  gender?: string
  date_of_birth: string
  file?: File | null
}) {
  const body = new FormData()
  body.set('first_name', input.first_name)
  body.set('last_name', input.last_name)
  body.set('email', input.email)
  body.set('mobile', input.mobile)
  body.set('date_of_birth', input.date_of_birth)

  if (input.country_code) body.set('country_code', input.country_code)
  if (input.address) body.set('address', input.address)
  if (input.gender) body.set('gender', input.gender)
  if (input.file) body.set('file_url', input.file)

  const response = await api.post<ApiEnvelope<AccountProfile>>('/account/profile/update', body)

  return response
}

export async function updateAccountPassword(input: {
  old_password: string
  new_password: string
  new_password_confirmation: string
}) {
  return api.post<{ success?: boolean; message?: string; errors?: Record<string, string[] | string> }>('/account/password/update', input)
}

export async function logoutDevice(input: { id?: number; deviceId?: string | null }) {
  const params = new URLSearchParams()

  if (input.id) {
    params.set('id', String(input.id))
  }

  if (input.deviceId) {
    params.set('device_id', input.deviceId)
  }

  const response = await api.get<ApiEnvelope<null>>(`/api/device-logout${params.toString() ? `?${params.toString()}` : ''}`)

  return response.message
}

export async function logoutAllDevices() {
  const response = await api.get<ApiEnvelope<unknown>>('/api/logout-all')

  return response.message
}

export async function deleteAccount() {
  const response = await api.get<ApiEnvelope<null>>('/api/delete-account')

  return response.message
}
