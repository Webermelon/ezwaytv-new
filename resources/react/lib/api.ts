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

    const response = await fetch(`${this.baseUrl}${path}`, {
      ...options,
      headers,
      credentials: 'same-origin',
    })

    const payload = await parseResponse(response)

    if (!response.ok) {
      throw new ApiError(response.statusText || 'API request failed', response.status, payload)
    }

    return payload as T
  }
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
