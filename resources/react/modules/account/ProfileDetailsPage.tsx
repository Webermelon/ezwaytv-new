import { FormEvent, useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bookmark, CreditCard, Edit3, ExternalLink, KeyRound, Loader2, PackageCheck, UserCircle, X } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { isNativeIosApp } from '@/lib/native-platform'
import { loadAccountSettings, type AccountProfile } from '@/modules/account/accountApi'

type ProfileForm = {
  first_name: string
  last_name: string
  email: string
  mobile: string
  country_code: string
  address: string
  gender: string
  date_of_birth: string
  file: File | null
  previewUrl: string
}

const emptyForm: ProfileForm = {
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  country_code: '',
  address: '',
  gender: 'male',
  date_of_birth: '',
  file: null,
  previewUrl: '',
}

export function ProfileDetailsPage() {
  const [form, setForm] = useState<ProfileForm>(emptyForm)

  const accountQuery = useQuery({
    queryKey: ['account-settings'],
    queryFn: loadAccountSettings,
    enabled: isAuthenticated(),
  })

  const profile = accountQuery.data?.profile
  const avatar = form.previewUrl || profile?.avatar || window.ezwayAuth?.avatar || '/dummy-images/avatars/icon1.png'

  useEffect(() => {
    if (!profile) return

    setForm({
      first_name: profile.first_name ?? '',
      last_name: profile.last_name ?? '',
      email: profile.email ?? '',
      mobile: profile.mobile ?? '',
      country_code: profile.country_code ?? '',
      address: profile.address ?? '',
      gender: profile.gender || 'male',
      date_of_birth: normalizeDate(profile.date_of_birth),
      file: null,
      previewUrl: '',
    })
  }, [profile])

  useEffect(() => {
    return () => {
      if (form.previewUrl) URL.revokeObjectURL(form.previewUrl)
    }
  }, [form.previewUrl])

  if (!isAuthenticated()) {
    return <AuthRequired title="Profile Details" />
  }

  function setFile(file: File | null) {
    if (form.previewUrl) URL.revokeObjectURL(form.previewUrl)
    setForm({ ...form, file, previewUrl: file ? URL.createObjectURL(file) : '' })
  }

  function submitForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <AccountHero title="Profile Details" description="Your TV profile is read-only here. Update your profile from eZWay Network to keep all connected apps in sync." actionLabel="Update on eZWay Network" actionHref="https://ezwaynetwork.com" />

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1800px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/update-profile" />
          <div className="min-w-0">
            {accountQuery.isLoading ? (
              <div className="flex min-h-72 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
                <Loader2 className="h-6 w-6 animate-spin text-[#edc342]" />
              </div>
            ) : (
              <form onSubmit={submitForm} className="rounded-md border border-white/10 bg-white/[0.035] p-5">
                <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-center">
                  <span className="flex h-28 w-28 shrink-0 overflow-hidden rounded-full border border-[#d4a843]/35 bg-[#d4a843]/12">
                    <img src={avatar} alt="" className="h-full w-full object-cover" />
                  </span>
                  <div>
                    <h2 className="text-2xl font-black">Profile Information</h2>
                    <p className="mt-2 max-w-xl text-sm leading-6 text-white/54">Profile changes are managed on eZWay Network so your identity stays consistent across all eZWay apps.</p>
                    <a href="https://ezwaynetwork.com" className="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-md border border-white/10 bg-white/[0.06] px-4 text-sm font-bold text-white transition hover:bg-white/[0.1]">
                      <ExternalLink className="h-4 w-4" />
                      Update on eZWay Network
                    </a>
                  </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <TextField label="First Name" value={form.first_name} onChange={(value) => setForm({ ...form, first_name: value })} required />
                  <TextField label="Last Name" value={form.last_name} onChange={(value) => setForm({ ...form, last_name: value })} required />
                  <TextField label="Email" type="email" value={form.email} onChange={(value) => setForm({ ...form, email: value })} required />
                  <TextField label="Mobile" value={form.mobile} onChange={(value) => setForm({ ...form, mobile: value })} required />
                  <TextField label="Country Code" value={form.country_code} onChange={(value) => setForm({ ...form, country_code: value })} placeholder="+1" />
                  <TextField label="Date of Birth" type="date" value={form.date_of_birth} onChange={(value) => setForm({ ...form, date_of_birth: value })} required />
                </div>

                <fieldset className="mt-4">
                  <legend className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-white/42">Gender</legend>
                  <div className="grid gap-2 sm:grid-cols-3">
                    {['male', 'female', 'other'].map((gender) => (
                      <label key={gender} className="flex h-11 items-center gap-2 rounded-md border border-white/10 bg-black/20 px-3 text-sm font-bold capitalize text-white/72">
                        <input disabled type="radio" name="gender" value={gender} checked={form.gender === gender} onChange={() => undefined} className="accent-[#edc342]" />
                        {gender}
                      </label>
                    ))}
                  </div>
                </fieldset>

                <label className="mt-4 grid gap-2">
                  <span className="text-xs font-black uppercase tracking-[0.18em] text-white/42">Address</span>
                  <textarea
                    disabled
                    value={form.address}
                    onChange={() => undefined}
                    rows={4}
                    className="cursor-not-allowed rounded-md border border-white/10 bg-black/30 px-4 py-3 text-sm font-semibold text-white/72 outline-none placeholder:text-white/28"
                    placeholder="Address"
                  />
                </label>

                <div className="mt-6 flex justify-end">
                  <Button asChild className="bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
                    <a href="https://ezwaynetwork.com">
                      <ExternalLink className="h-4 w-4" />
                      Update on eZWay Network
                    </a>
                  </Button>
                </div>
              </form>
            )}
          </div>
        </div>
      </section>
    </main>
  )
}

function TextField({ label, value, onChange, type = 'text', required, placeholder }: { label: string; value: string; onChange: (value: string) => void; type?: string; required?: boolean; placeholder?: string }) {
  return (
    <label className="grid gap-2">
      <span className="text-xs font-black uppercase tracking-[0.18em] text-white/42">{label}</span>
      <input
        disabled
        type={type}
        value={value}
        required={required}
        placeholder={placeholder ?? label}
        onChange={(event) => onChange(event.target.value)}
        className="h-12 cursor-not-allowed rounded-md border border-white/10 bg-black/30 px-4 text-sm font-semibold text-white/72 outline-none placeholder:text-white/28"
      />
    </label>
  )
}

export function AccountSidebar({ activeHref }: { activeHref: string }) {
  const items = [
    { label: 'Account Settings', href: '/account-setting', icon: UserCircle },
    { label: 'My Watchlist', href: '/watch-list', icon: Bookmark },
    ...(!isNativeIosApp() ? [
      { label: 'Payment History', href: '/payment-history', icon: CreditCard },
      { label: 'Orders', href: '/orders', icon: PackageCheck },
    ] : []),
    { label: 'Profile Details', href: '/update-profile', icon: UserCircle },
    { label: 'Change Password', href: '/change-password', icon: KeyRound },
  ]

  return (
    <aside className="rounded-md border border-white/10 bg-white/[0.035] p-2 lg:sticky lg:top-24 lg:self-start">
      <nav className="grid gap-1">
        {items.map(({ label, href, icon: Icon }) => {
          const active = href === activeHref
          return (
            <a
              key={label}
              href={href}
              className={[
                'flex min-h-12 items-center gap-3 rounded-md border px-3 text-sm font-black transition',
                active ? 'border-[#d4a843]/38 bg-[#d4a843]/12 text-[#edc342]' : 'border-transparent text-white/66 hover:border-white/10 hover:bg-white/[0.055] hover:text-white',
              ].join(' ')}
            >
              <Icon className="h-4 w-4 shrink-0" />
              <span className="truncate">{label}</span>
            </a>
          )
        })}
      </nav>
    </aside>
  )
}

export function AccountHero({ title, description, actionLabel, actionHref }: { title: string; description: string; actionLabel?: string; actionHref?: string }) {
  return (
    <section className="border-b border-white/8 bg-[radial-gradient(circle_at_82%_0%,rgba(212,168,67,0.18),transparent_28%),linear-gradient(180deg,#0b0b0b_0%,#050505_100%)] px-4 py-10 sm:px-8 lg:px-12">
      <div className="mx-auto flex max-w-[1800px] flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <span className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">
            <UserCircle className="h-4 w-4" />
            Account Settings
          </span>
          <h1 className="mt-5 text-4xl font-black leading-none sm:text-5xl">{title}</h1>
          <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">{description}</p>
        </div>
        {actionLabel && actionHref ? (
          <Button asChild className="h-11 bg-[#edc342] px-4 font-black text-black hover:bg-[#f4ce4d]">
            <a href={actionHref}>{actionLabel}</a>
          </Button>
        ) : null}
      </div>
    </section>
  )
}

export function Notice({ tone, text, onClose }: { tone: 'success' | 'error'; text: string; onClose: () => void }) {
  const classes = tone === 'success' ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-red-400/20 bg-red-500/10 text-red-100'

  return (
    <div className={`mb-6 flex items-start justify-between gap-3 rounded-md border px-4 py-3 text-sm font-semibold ${classes}`}>
      <span>{text}</span>
      <button type="button" onClick={onClose} className="shrink-0 text-current opacity-70 hover:opacity-100" aria-label="Dismiss message">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

export function AuthRequired({ title }: { title: string }) {
  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <section className="px-4 py-16 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[760px] rounded-md border border-white/10 bg-white/[0.035] p-8 text-center">
          <UserCircle className="mx-auto h-10 w-10 text-[#edc342]" />
          <h1 className="mt-4 text-3xl font-black">{title}</h1>
          <p className="mt-3 text-sm leading-6 text-white/58">Sign in to manage this account area.</p>
          <Button asChild className="mt-6 bg-[#edc342] text-black hover:bg-[#f4ce4d]">
            <a href={`/login?redirect=${encodeURIComponent(window.location.pathname)}`}>Sign In</a>
          </Button>
        </div>
      </section>
    </main>
  )
}

export function readApiError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    const payload = error.payload as { message?: string; errors?: Record<string, string[] | string> } | null
    const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null
    return firstError || payload?.message || fallback
  }

  return error instanceof Error ? error.message : fallback
}

function normalizeDate(value: AccountProfile['date_of_birth']) {
  if (!value) return ''
  return String(value).slice(0, 10)
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}
