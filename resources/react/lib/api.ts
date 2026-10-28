type ApiClientOptions = {
  baseUrl?: string
  token?: string
}

type ApiRequestOptions = RequestInit & {
  token?: string
}

export class ApiError extends Error {
  status: number
  payload: unknown

  constructor(message: string, status: number, payload: unknown = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.payload = payload
  }
}

export class ApiClient {
  private baseUrl: string
  private token?: string
  private csrfRefresh: Promise<string | null> | null = null

  constructor(options: ApiClientOptions = {}) {
    this.baseUrl = options.baseUrl ?? ''
    this.token = options.token
  }

  async get<T>(path: string, options: ApiRequestOptions = {}) {
    return this.request<T>(path, { ...options, method: 'GET' })
  }

  async post<T>(path: string, body?: unknown, options: ApiRequestOptions = {}) {
    return this.request<T>(path, {
      ...options,
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    })
  }

  private async request<T>(path: string, options: ApiRequestOptions = {}) {
    const headers = new Headers(options.headers)

    if (!(options.body instanceof FormData)) {
      headers.set('Content-Type', 'application/json')
    }

    headers.set('Accept', 'application/json')

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    if (csrfToken && !headers.has('X-CSRF-TOKEN')) {
      headers.set('X-CSRF-TOKEN', csrfToken)
    }

    const token = options.token ?? this.token
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }

    const send = () => fetch(`${this.baseUrl}${path}`, {
      ...options,
      headers,
      credentials: 'same-origin',
    })
    let response = await send()
    if (response.status === 419 && !['GET', 'HEAD'].includes(options.method ?? 'GET')) {
      const freshToken = await this.refreshCsrfToken()
      if (freshToken) {
        headers.set('X-CSRF-TOKEN', freshToken)
        response = await send()
      }
    }

    const payload = await parseResponse(response)

    if (response.redirected && /\/(?:login|login-page|admin\/login)\/?$/.test(new URL(response.url).pathname)) {
      throw new ApiError('Please sign in again to save changes.', 401, null)
    }
    if (!response.ok) {
      throw new ApiError(errorMessage(payload, response.status), response.status, payload)
    }

    return payload as T
  }

  private async refreshCsrfToken(): Promise<string | null> {
    if (!this.csrfRefresh) {
      this.csrfRefresh = (async () => {
        try {
          const response = await fetch('/auth/csrf-token', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store',
          })
          const payload = await parseResponse(response)
          if (!response.ok || !payload || typeof payload !== 'object'
            || typeof payload.csrf_token !== 'string' || !payload.csrf_token) return null

          let meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
          if (!meta) {
            meta = document.createElement('meta')
            meta.name = 'csrf-token'
            document.head.appendChild(meta)
          }
          meta.content = payload.csrf_token
          return payload.csrf_token as string
        } catch {
          return null
        }
      })()
    }
    try {
      return await this.csrfRefresh
    } finally {
      this.csrfRefresh = null
    }
  }
}

function errorMessage(payload: unknown, status: number): string {
  if (payload && typeof payload === 'object') {
    const data = payload as { message?: unknown; error?: unknown; errors?: Record<string, unknown> }
    if (typeof data.message === 'string' && data.message.trim()) return data.message
    if (typeof data.error === 'string' && data.error.trim()) return data.error
    if (data.errors && typeof data.errors === 'object') {
      const first = Object.values(data.errors).flat().find((value) => typeof value === 'string' && value.trim())
      if (typeof first === 'string') return first
    }
  }
  if (status === 419) return 'Your session has expired. Please refresh the page and try again.'
  if (status === 401) return 'Please sign in again to save changes.'
  return 'The request could not be completed. Please try again.'
}

async function parseResponse(response: Response) {
  const text = await response.text()

  if (!text) {
    return null
  }

  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

export const api = new ApiClient()
