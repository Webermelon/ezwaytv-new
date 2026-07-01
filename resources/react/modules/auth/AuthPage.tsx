import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react'
import { AlertCircle, ArrowLeft, ArrowRight, AtSign, CheckCircle2, KeyRound, Mail, Phone, RefreshCw, Shield, UserRound } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { BrandLogo } from '@/components/BrandLogo'

type AuthMode = 'login' | 'forgot' | 'register'
type OtpStep = 'email' | 'code'
type RegisterStep = 'account' | 'code'
type CheckState = 'idle' | 'checking' | 'available' | 'taken' | 'invalid'

type ApiEnvelope = {
  status?: boolean
  message?: unknown
  errors?: Record<string, string[] | string>
  data?: unknown
  available?: boolean
}

const authBackground = '/dummy-images/login_banner.jpg'

export function AuthPage() {
  const mode = inferAuthMode()
  const copy = authCopy(mode)

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader />
      <section className="relative min-h-[calc(100vh-64px)] overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center opacity-42"
          style={{ backgroundImage: `url(${authBackground})` }}
        />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(212,168,67,0.22),transparent_30%),linear-gradient(90deg,#050505_0%,rgba(5,5,5,0.95)_42%,rgba(5,5,5,0.7)_100%)]" />
        <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#050505] to-transparent" />

        <div className="relative z-10 mx-auto grid min-h-[calc(100vh-64px)] max-w-[1800px] items-center gap-10 px-4 py-10 sm:px-8 lg:grid-cols-[minmax(0,0.92fr)_minmax(360px,500px)] lg:px-12">
          <div className="max-w-2xl">
            <a href="/" className="mb-8 inline-flex w-fit items-center gap-2 text-sm font-bold text-white/62 transition hover:text-white">
              <ArrowLeft className="h-4 w-4" />
              Back to eZWay TV
            </a>
            <div className="mb-8">
              <BrandLogo imageClassName="max-h-16 max-w-[260px]" textClassName="text-4xl" placeholderClassName="h-14 w-[230px]" />
            </div>
            <span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#d4a843]/30 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.24em] text-[#f0c74b]">
              <Shield className="h-4 w-4" />
              Secure TV Access
            </span>
            <h1 className="mt-5 max-w-xl text-4xl font-black leading-[1.04] sm:text-6xl">{copy.title}</h1>
            <p className="mt-5 max-w-xl text-base leading-7 text-white/68">{copy.description}</p>
            <div className="mt-8 grid max-w-xl gap-3 text-sm font-semibold text-white/68 sm:grid-cols-3">
              {copy.signals.map((item) => (
                <div key={item} className="flex items-center gap-2 rounded-md border border-white/10 bg-white/[0.055] px-3 py-3">
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-[#d4a843]" />
                  {item}
                </div>
              ))}
            </div>
          </div>

          <AuthPanel mode={mode} />
        </div>
      </section>
    </main>
  )
}

function AuthPanel({ mode }: { mode: AuthMode }) {
  if (mode === 'register') return <RegisterPanel />

  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string; actionHref?: string; actionLabel?: string } | null>(null)
  const [email, setEmail] = useState('')
  const [otp, setOtp] = useState('')
  const [otpStep, setOtpStep] = useState<OtpStep>('email')

  const isForgot = mode === 'forgot'

  async function handleForgotSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const formData = new FormData(form)
    setMessage(null)
    setLoading(true)

    try {
      const response = await postAuth('/api/forgot-password?is_ajax=1', formData)
      ensureApiSuccess(response, 'Reset link sent. Please check your inbox.')
      setMessage({ tone: 'success', text: getApiMessage(response, 'Reset link sent. Please check your inbox.') })
      form.reset()
    } catch (error) {
      setMessage({ tone: 'error', text: error instanceof Error ? error.message : 'Something went wrong. Please try again.' })
    } finally {
      setLoading(false)
    }
  }

  async function handleOtpSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMessage(null)
    setLoading(true)

    try {
      const formData = new FormData()
      formData.set('email', email.trim())

      if (otpStep === 'email') {
        const response = await postAuth('/auth/spa-otp/send', formData)
        ensureApiSuccess(response, 'We sent a login code to your email.')
        setOtpStep('code')
        setMessage({ tone: 'success', text: getApiMessage(response, 'We sent a login code to your email.') })
        return
      }

      formData.set('otp', otp.replace(/\D/g, '').slice(0, 4))
      const response = await postAuth('/auth/spa-otp/verify', formData)
      ensureApiSuccess(response, 'You are signed in.')
      setMessage({ tone: 'success', text: getApiMessage(response, 'You are signed in.') })
      window.location.href = getRedirectUrl(response) || '/'
    } catch (error) {
      const text = error instanceof Error ? error.message : 'Something went wrong. Please try again.'
      setMessage({ tone: 'error', text })
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="rounded-md border border-white/10 bg-[#111]/92 p-5 shadow-2xl shadow-black/50 backdrop-blur-xl sm:p-7">
      <div className="mb-6">
        <p className="text-xs font-black uppercase tracking-[0.24em] text-[#d4a843]">{isForgot ? 'Password help' : 'OTP Login'}</p>
        <h2 className="mt-2 text-3xl font-black">{isForgot ? 'Reset password' : 'Sign in with a code'}</h2>
        <p className="mt-2 text-sm leading-6 text-white/58">
          {isForgot
            ? 'Enter your account email and we will send the reset link.'
            : otpStep === 'email'
              ? 'Enter the email connected to your eZWay TV account. We will send a one-time login code.'
              : `Enter the 4-digit code sent to ${email}.`}
        </p>
      </div>

      {message ? <StatusMessage tone={message.tone} text={message.text} /> : null}

      {isForgot ? (
        <form className="grid gap-4" onSubmit={handleForgotSubmit}>
          <Field icon={<Mail className="h-5 w-5" />} label="Account email" name="email" type="email" autoComplete="email" required />
          <PrimaryButton loading={loading} label="Send Reset Link" />
        </form>
      ) : (
        <form className="grid gap-4" onSubmit={handleOtpSubmit}>
          <Field icon={<Mail className="h-5 w-5" />} label="Account email" name="email" type="email" value={email} onChange={setEmail} autoComplete="email" required disabled={otpStep === 'code'} />

          {otpStep === 'code' ? (
            <Field icon={<KeyRound className="h-5 w-5" />} label="Login code" name="otp" value={otp} onChange={(value) => setOtp(value.replace(/\D/g, '').slice(0, 4))} inputMode="numeric" autoComplete="one-time-code" maxLength={4} required />
          ) : null}

          <PrimaryButton loading={loading} label={otpStep === 'email' ? 'Send Login Code' : 'Verify and Sign In'} disabled={!email.trim() || (otpStep === 'code' && otp.length !== 4)} />

          {otpStep === 'code' ? (
            <div className="grid gap-2 rounded-md border border-white/10 bg-black/22 px-4 py-4 text-sm font-semibold text-white/58">
              <button type="button" disabled={loading} onClick={() => { setOtp(''); setOtpStep('email'); setMessage(null) }} className="inline-flex items-center justify-center gap-2 text-[#f0c74b] transition hover:text-white disabled:opacity-60">
                <RefreshCw className="h-4 w-4" />
                Use a different email or resend code
              </button>
            </div>
          ) : null}
        </form>
      )}

      <div className="mt-6 rounded-md border border-white/10 bg-black/22 px-4 py-4 text-center text-sm font-semibold text-white/58">
        {isForgot ? (
          <>Remember your password? <a className="font-black text-[#f0c74b] hover:text-white" href="/login">Back to OTP login</a></>
        ) : (
          <>New here? <a className="font-black text-[#f0c74b] hover:text-white" href="/register">Create your eZWay TV account</a></>
        )}
      </div>
    </section>
  )
}

function RegisterPanel() {
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)
  const [step, setStep] = useState<RegisterStep>('account')
  const [inviteCode, setInviteCode] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [otp, setOtp] = useState('')

  const usernameCheck = useAvailability('/auth/check-username', 'username', username)
  const emailCheck = useAvailability('/auth/check-email', 'email', email)
  const canCreate = firstName.trim() && lastName.trim() && usernameCheck.state === 'available' && emailCheck.state === 'available'

  async function handleRegisterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMessage(null)
    setLoading(true)

    try {
      const formData = new FormData()
      formData.set('invite_code', inviteCode.trim())
      formData.set('first_name', firstName.trim())
      formData.set('last_name', lastName.trim())
      formData.set('username', username.trim())
      formData.set('email', email.trim())
      formData.set('phone_number', phone.trim())

      const response = await postAuth('/auth/spa-register', formData)
      ensureApiSuccess(response, 'Your account is ready. We sent a login code to your email.')
      setStep('code')
      setMessage({ tone: 'success', text: getApiMessage(response, 'Your account is ready. We sent a login code to your email.') })
    } catch (error) {
      setMessage({ tone: 'error', text: error instanceof Error ? error.message : 'Something went wrong. Please try again.' })
    } finally {
      setLoading(false)
    }
  }

  async function handleVerifySubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMessage(null)
    setLoading(true)

    try {
      const formData = new FormData()
      formData.set('email', email.trim())
      formData.set('otp', otp.replace(/\D/g, '').slice(0, 4))
      const response = await postAuth('/auth/spa-otp/verify', formData)
      ensureApiSuccess(response, 'You are signed in.')
      window.location.href = '/subscription-plan'
    } catch (error) {
      const text = error instanceof Error ? error.message : 'Something went wrong. Please try again.'
      const shouldRegister = /could not find|not find|not registered|no active/i.test(text)
      setMessage({
        tone: 'error',
        text,
        actionHref: shouldRegister ? '/register' : undefined,
        actionLabel: shouldRegister ? 'Create account' : undefined,
      })
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="rounded-md border border-white/10 bg-[#111]/92 p-5 shadow-2xl shadow-black/50 backdrop-blur-xl sm:p-7">
      <div className="mb-6">
        <p className="text-xs font-black uppercase tracking-[0.24em] text-[#d4a843]">Create Account</p>
        <h2 className="mt-2 text-3xl font-black">Start watching with eZWay TV</h2>
        <p className="mt-2 text-sm leading-6 text-white/58">
          {step === 'account' ? 'Create your eZWay account first. We will verify it with an email code before checkout.' : `Enter the 4-digit code sent to ${email}.`}
        </p>
      </div>

      {message ? <StatusMessage tone={message.tone} text={message.text} /> : null}

      {step === 'account' ? (
        <form className="grid gap-4" onSubmit={handleRegisterSubmit}>
          <Field icon={<AtSign className="h-5 w-5" />} label="Invite code" name="invite_code" value={inviteCode} onChange={setInviteCode} autoComplete="off" />
          <div className="grid gap-4 sm:grid-cols-2">
            <Field icon={<UserRound className="h-5 w-5" />} label="First name" name="first_name" value={firstName} onChange={setFirstName} autoComplete="given-name" required />
            <Field icon={<UserRound className="h-5 w-5" />} label="Last name" name="last_name" value={lastName} onChange={setLastName} autoComplete="family-name" required />
          </div>
          <Field icon={<AtSign className="h-5 w-5" />} label="Username" name="username" value={username} onChange={(value) => setUsername(value.replace(/\s+/g, '').slice(0, 32))} autoComplete="username" required />
          <AvailabilityText check={usernameCheck} idleText="Use 3-32 letters, numbers, dot, dash, or underscore." />
          <Field icon={<Mail className="h-5 w-5" />} label="Email" name="email" type="email" value={email} onChange={setEmail} autoComplete="email" required />
          <AvailabilityText check={emailCheck} idleText="We will send your login code here." />
          <Field icon={<Phone className="h-5 w-5" />} label="Phone" name="phone_number" type="tel" value={phone} onChange={setPhone} autoComplete="tel" />
          <PrimaryButton loading={loading} label="Create Account" disabled={!canCreate} />
        </form>
      ) : (
        <form className="grid gap-4" onSubmit={handleVerifySubmit}>
          <Field icon={<KeyRound className="h-5 w-5" />} label="Login code" name="otp" value={otp} onChange={(value) => setOtp(value.replace(/\D/g, '').slice(0, 4))} inputMode="numeric" autoComplete="one-time-code" maxLength={4} required />
          <PrimaryButton loading={loading} label="Verify and Continue" disabled={otp.length !== 4} />
          <button type="button" disabled={loading} onClick={() => { setStep('account'); setOtp(''); setMessage(null) }} className="text-sm font-black text-[#f0c74b] transition hover:text-white disabled:opacity-60">
            Edit account details
          </button>
        </form>
      )}

      <div className="mt-6 rounded-md border border-white/10 bg-black/22 px-4 py-4 text-center text-sm font-semibold text-white/58">
        Already have an account? <a className="font-black text-[#f0c74b] hover:text-white" href="/login">Sign in with OTP</a>
      </div>
    </section>
  )
}

function StatusMessage({ tone, text, actionHref, actionLabel }: { tone: 'success' | 'error'; text: string; actionHref?: string; actionLabel?: string }) {
  return (
    <div className={['mb-5 flex gap-3 rounded-md border px-4 py-3 text-sm font-semibold leading-6', tone === 'success' ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-100' : 'border-red-400/25 bg-red-500/10 text-red-100'].join(' ')}>
      {tone === 'success' ? <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" /> : <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />}
      <span className="min-w-0 flex-1">{text}</span>
      {actionHref && actionLabel ? (
        <a href={actionHref} className="shrink-0 rounded-md bg-white/12 px-3 py-1 text-xs font-black text-white transition hover:bg-white/20">
          {actionLabel}
        </a>
      ) : null}
    </div>
  )
}

function AvailabilityText({ check, idleText }: { check: { state: CheckState; message: string }; idleText: string }) {
  const className = check.state === 'available' ? 'text-emerald-300' : check.state === 'taken' || check.state === 'invalid' ? 'text-red-200' : 'text-white/42'
  const text = check.state === 'idle' ? idleText : check.message
  return <p className={`-mt-2 text-xs font-semibold ${className}`}>{text}</p>
}

function PrimaryButton({ loading, label, disabled }: { loading: boolean; label: string; disabled?: boolean }) {
  return (
    <button type="submit" disabled={loading || disabled} className="mt-1 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-[#d4a843] px-4 text-sm font-black text-black transition hover:bg-[#efc955] disabled:cursor-not-allowed disabled:opacity-65">
      {loading ? 'Please wait...' : label}
      {!loading ? <ArrowRight className="h-5 w-5" /> : null}
    </button>
  )
}

function Field({
  icon,
  label,
  name,
  type = 'text',
  value,
  onChange,
  defaultValue,
  autoComplete,
  inputMode,
  required,
  disabled,
  maxLength,
}: {
  icon: ReactNode
  label: string
  name: string
  type?: string
  value?: string
  onChange?: (value: string) => void
  defaultValue?: string
  autoComplete?: string
  inputMode?: 'none' | 'text' | 'tel' | 'url' | 'email' | 'numeric' | 'decimal' | 'search'
  required?: boolean
  disabled?: boolean
  maxLength?: number
}) {
  return (
    <label className="grid gap-2">
      <span className="text-xs font-black uppercase tracking-[0.16em] text-white/46">{label}</span>
      <span className="flex h-12 items-center gap-3 rounded-md border border-white/10 bg-black/32 px-3 text-white transition focus-within:border-[#d4a843]/70 focus-within:bg-black/44">
        <span className="text-[#d4a843]">{icon}</span>
        <input name={name} type={type} value={value} onChange={onChange ? (event) => onChange(event.target.value) : undefined} defaultValue={defaultValue} autoComplete={autoComplete} inputMode={inputMode} required={required} disabled={disabled} maxLength={maxLength} className="h-full min-w-0 flex-1 bg-transparent text-sm font-semibold outline-none placeholder:text-white/32 disabled:cursor-not-allowed disabled:text-white/50" />
      </span>
    </label>
  )
}

function useAvailability(path: string, key: string, value: string) {
  const [state, setState] = useState<CheckState>('idle')
  const [message, setMessage] = useState('')
  const normalized = useMemo(() => value.trim(), [value])

  useEffect(() => {
    if (!normalized) {
      setState('idle')
      setMessage('')
      return
    }

    if (key === 'username' && !/^[A-Za-z0-9_.-]{3,32}$/.test(normalized)) {
      setState('invalid')
      setMessage('Use 3-32 letters, numbers, dot, dash, or underscore.')
      return
    }

    if (key === 'email' && !/^\S+@\S+\.\S+$/.test(normalized)) {
      setState('idle')
      setMessage('')
      return
    }

    const controller = new AbortController()
    const timeout = window.setTimeout(async () => {
      setState('checking')
      setMessage('Checking...')

      try {
        const payload = await getAuth(`${path}?${new URLSearchParams({ [key]: normalized })}`, controller.signal)
        const available = payload.available === true
        setState(available ? 'available' : 'taken')
        setMessage(available ? `${key === 'email' ? 'Email' : 'Username'} is valid` : getApiMessage(payload, 'Already taken.'))
      } catch (error) {
        if (controller.signal.aborted) return
        setState('invalid')
        setMessage(error instanceof Error ? error.message : 'Could not check this right now.')
      }
    }, 450)

    return () => {
      controller.abort()
      window.clearTimeout(timeout)
    }
  }, [key, normalized, path])

  return { state, message }
}

async function postAuth(path: string, body: FormData): Promise<ApiEnvelope> {
  body.set('is_ajax', '1')

  let response = await fetch(path, { method: 'POST', headers: authHeaders(), body, credentials: 'same-origin' })

  if (response.status === 419) {
    await refreshCsrfToken()
    response = await fetch(path, { method: 'POST', headers: authHeaders(), body, credentials: 'same-origin' })
  }

  const payload = await parseJson(response)

  if (!response.ok) throw new Error(getApiMessage(payload, response.statusText || 'Request failed.'))

  return payload
}

async function getAuth(path: string, signal?: AbortSignal): Promise<ApiEnvelope> {
  const response = await fetch(path, { headers: authHeaders(), credentials: 'same-origin', signal })
  const payload = await parseJson(response)
  if (!response.ok) throw new Error(getApiMessage(payload, response.statusText || 'Request failed.'))
  return payload
}

function authHeaders() {
  const headers = new Headers({ Accept: 'application/json' })
  const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken)
  return headers
}

async function refreshCsrfToken() {
  const response = await fetch('/api/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  const payload = await parseJson(response)
  const token = getCsrfFromPayload(payload)
  if (!token) return
  let meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
  if (!meta) {
    meta = document.createElement('meta')
    meta.name = 'csrf-token'
    document.head.appendChild(meta)
  }
  meta.content = token
}

async function parseJson(response: Response): Promise<ApiEnvelope> {
  const text = await response.text()
  if (!text) return {}
  try { return JSON.parse(text) } catch { return { message: text } }
}

function ensureApiSuccess(payload: ApiEnvelope, fallback: string) {
  if (payload.status === false) throw new Error(getApiMessage(payload, fallback))
}

function getApiMessage(payload: ApiEnvelope, fallback: string) {
  const message = payload.message
  if (typeof message === 'string' && message.trim()) return message
  if (payload.errors) {
    const first = Object.values(payload.errors).flat().find(Boolean)
    if (first) return String(first)
  }
  return fallback
}

function getCsrfFromPayload(payload: ApiEnvelope) {
  if (payload && typeof payload === 'object') {
    const record = payload as Record<string, unknown>
    if (typeof record.csrf_token === 'string') return record.csrf_token
    if (typeof record.token === 'string') return record.token
    if (record.data && typeof record.data === 'object') {
      const data = record.data as Record<string, unknown>
      if (typeof data.csrf_token === 'string') return data.csrf_token
      if (typeof data.token === 'string') return data.token
    }
  }
  return ''
}

function getRedirectUrl(payload: ApiEnvelope) {
  if (!payload.data || typeof payload.data !== 'object') return ''
  const data = payload.data as Record<string, unknown>
  return typeof data.redirect_url === 'string' ? data.redirect_url : ''
}

function inferAuthMode(): AuthMode {
  const pathname = window.location.pathname
  if (pathname.startsWith('/register')) return 'register'
  if (pathname.startsWith('/forget-password')) return 'forgot'
  return 'login'
}

function authCopy(mode: AuthMode) {
  if (mode === 'forgot') {
    return {
      title: 'Get back into your account',
      description: 'Use your account email and we will send the reset link for your eZWay TV access.',
      signals: ['Email recovery', 'Secure reset', 'Private account'],
    }
  }

  if (mode === 'register') {
    return {
      title: 'Create your eZWay TV account',
      description: 'Reserve your username, verify your email, and continue to the TV subscription checkout when you are ready.',
      signals: ['Live username check', 'Email code verify', 'Checkout after signup'],
    }
  }

  return {
    title: 'Sign in to eZWay TV',
    description: 'Use a secure one-time code to access your subscriptions, watchlist, live channels, and on-demand videos.',
    signals: ['Email code login', 'No password needed', 'Private watch session'],
  }
}
