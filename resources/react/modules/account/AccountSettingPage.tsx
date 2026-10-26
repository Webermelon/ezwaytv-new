import { ReactNode, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Check, Crown, Edit3, Loader2, LogOut, MonitorSmartphone, ShieldAlert, Smartphone, Trash2, UserCircle, X } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { isNativeIosApp } from '@/lib/native-platform'
import { deleteAccount, loadAccountSettings, logoutAllDevices, logoutDevice, type AccountDevice } from '@/modules/account/accountApi'
import { AccountSidebar } from '@/modules/account/ProfileDetailsPage'

type ConfirmAction = {
  title: string
  message: string
  actionLabel: string
  tone: 'warning' | 'danger'
  onConfirm: () => void
}

export function AccountSettingPage() {
  const queryClient = useQueryClient()
  const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)
  const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null)

  const accountQuery = useQuery({
    queryKey: ['account-settings'],
    queryFn: loadAccountSettings,
    enabled: isAuthenticated(),
  })

  const account = accountQuery.data
  const otherDevices = useMemo(() => account?.other_device ?? [], [account?.other_device])
  const hasOtherDevices = otherDevices.length > 0

  const logoutDeviceMutation = useMutation({
    mutationFn: logoutDevice,
    onSuccess: (message) => {
      setNotice({ tone: 'success', text: message || 'Device logged out.' })
      setConfirmAction(null)
      queryClient.invalidateQueries({ queryKey: ['account-settings'] })
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Device could not be logged out.') })
      setConfirmAction(null)
    },
  })

  const logoutAllMutation = useMutation({
    mutationFn: logoutAllDevices,
    onSuccess: (message) => {
      setNotice({ tone: 'success', text: message || 'All devices logged out.' })
      setConfirmAction(null)
      window.setTimeout(() => {
        window.location.href = window.ezwayAuth?.logout_url || '/logout'
      }, 700)
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Devices could not be logged out.') })
      setConfirmAction(null)
    },
  })

  const deleteAccountMutation = useMutation({
    mutationFn: deleteAccount,
    onSuccess: (message) => {
      setNotice({ tone: 'success', text: message || 'Account deleted.' })
      setConfirmAction(null)
      window.setTimeout(() => {
        window.location.href = '/'
      }, 800)
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Account could not be deleted.') })
      setConfirmAction(null)
    },
  })

  const actionPending = logoutDeviceMutation.isPending || logoutAllMutation.isPending || deleteAccountMutation.isPending

  if (!isAuthenticated()) {
    return (
      <main className="min-h-screen bg-[#050505] text-white">
        <AppHeader active="home" />
        <section className="px-4 py-16 sm:px-8 lg:px-12">
          <div className="mx-auto max-w-[760px] rounded-md border border-white/10 bg-white/[0.035] p-8 text-center">
            <UserCircle className="mx-auto h-10 w-10 text-[#edc342]" />
            <h1 className="mt-4 text-3xl font-black">Account Settings</h1>
            <p className="mt-3 text-sm leading-6 text-white/58">Sign in to manage your account, plan, and devices.</p>
            <Button asChild className="mt-6 bg-[#edc342] text-black hover:bg-[#f4ce4d]">
              <a href={`/login?redirect=${encodeURIComponent('/account-setting')}`}>Sign In</a>
            </Button>
          </div>
        </section>
      </main>
    )
  }

  function confirmDeviceLogout(device: AccountDevice) {
    setConfirmAction({
      title: 'Logout Device',
      message: `Logout ${device.device_name || 'this device'}?`,
      actionLabel: 'Logout',
      tone: 'warning',
      onConfirm: () => logoutDeviceMutation.mutate({ id: device.id, deviceId: device.device_id }),
    })
  }

  function confirmLogoutAll() {
    setConfirmAction({
      title: 'Logout All Devices',
      message: 'This will end every active session for this account.',
      actionLabel: 'Logout All',
      tone: 'warning',
      onConfirm: () => logoutAllMutation.mutate(),
    })
  }

  function confirmDeleteAccount() {
    setConfirmAction({
      title: 'Delete Account',
      message: 'This permanently deletes your account, profiles, watch activity, and saved data.',
      actionLabel: 'Delete Account',
      tone: 'danger',
      onConfirm: () => deleteAccountMutation.mutate(),
    })
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <section className="border-b border-white/8 bg-[radial-gradient(circle_at_82%_0%,rgba(212,168,67,0.18),transparent_28%),linear-gradient(180deg,#0b0b0b_0%,#050505_100%)] px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">
                <UserCircle className="h-4 w-4" />
                Account Settings
              </span>
              <h1 className="mt-5 text-4xl font-black leading-none sm:text-5xl">Manage Your Account</h1>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">
                Review your subscription, contact details, signed-in devices, and account controls.
              </p>
            </div>

            <Button asChild className="h-11 bg-[#edc342] px-4 font-black text-black hover:bg-[#f4ce4d]">
              <a href="https://ezwaynetwork.com">
                <Edit3 className="h-4 w-4" />
                Edit Profile
              </a>
            </Button>
          </div>
        </div>
      </section>

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1800px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/account-setting" />

          <div className="min-w-0">
            {notice ? <Notice tone={notice.tone} text={notice.text} onClose={() => setNotice(null)} /> : null}

            {accountQuery.isLoading ? (
              <div className="flex min-h-72 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
                <Loader2 className="h-6 w-6 animate-spin text-[#edc342]" />
              </div>
            ) : accountQuery.isError ? (
              <div className="rounded-md border border-red-400/20 bg-red-500/10 p-8 text-center text-red-100">
                {readApiError(accountQuery.error, 'Account settings could not be loaded.')}
              </div>
            ) : (
              <div className="grid gap-5">
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.85fr)]">
                  <SubscriptionPanel plan={account?.plan_details ?? null} />
                  <ContactPanel mobile={account?.register_mobile_number ?? null} />
                </div>

                <DevicePanel
                  currentDevice={account?.your_device ?? null}
                  otherDevices={otherDevices}
                  onLogoutDevice={confirmDeviceLogout}
                  onLogoutAll={hasOtherDevices ? confirmLogoutAll : undefined}
                />

                <DangerZone onDeleteAccount={confirmDeleteAccount} />
              </div>
            )}
          </div>
        </div>
      </section>

      {confirmAction ? (
        <ConfirmModal
          action={confirmAction}
          pending={actionPending}
          onClose={() => setConfirmAction(null)}
        />
      ) : null}
    </main>
  )
}

function SubscriptionPanel({ plan }: { plan: Record<string, unknown> | null }) {
  const planName = stringValue(plan?.name) || 'No Active Subscription'
  const endDate = stringValue(plan?.end_date)
  const isActive = Boolean(plan)

  return (
    <section className="rounded-md border border-white/10 bg-white/[0.035] p-5">
      <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex min-w-0 gap-4">
          <IconBadge tone={isActive ? 'gold' : 'muted'}>
            <Crown className="h-5 w-5" />
          </IconBadge>
          <div className="min-w-0">
            <p className="text-xs font-black uppercase tracking-[0.18em] text-white/38">Subscription</p>
            <h2 className="mt-2 truncate text-2xl font-black">{planName}</h2>
            <p className="mt-2 text-sm leading-6 text-white/56">
              {isActive ? 'Premium streaming experience is active on this account.' : 'Choose a plan to unlock premium eZWay TV access.'}
            </p>
          </div>
        </div>
        {!isNativeIosApp() ? (
          <Button asChild className="bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
            <a href="/subscription-plan">{isActive ? 'Manage Plan' : 'Subscribe Now'}</a>
          </Button>
        ) : null}
      </div>

      {isActive ? (
        <div className="mt-5 grid gap-3 sm:grid-cols-3">
          <InfoTile label="Status" value={stringValue(plan?.status) || 'Active'} />
          <InfoTile label="Expires" value={endDate || '-'} />
          <InfoTile label="Price" value={priceLabel(plan)} />
        </div>
      ) : null}
    </section>
  )
}

function ContactPanel({ mobile }: { mobile: string | null }) {
  return (
    <section className="rounded-md border border-white/10 bg-white/[0.035] p-5">
      <div className="flex gap-4">
        <IconBadge tone="blue">
          <Smartphone className="h-5 w-5" />
        </IconBadge>
        <div className="min-w-0">
          <p className="text-xs font-black uppercase tracking-[0.18em] text-white/38">Contact Info</p>
          <h2 className="mt-2 text-2xl font-black">Registered Details</h2>
          <p className="mt-2 text-sm leading-6 text-white/56">Your contact number is used for account access and notifications.</p>
        </div>
      </div>
      <div className="mt-5 rounded-md border border-white/10 bg-black/20 px-4 py-3">
        <p className="text-xs font-black uppercase tracking-[0.16em] text-white/36">Mobile</p>
        <p className="mt-2 text-lg font-black">{mobile ? maskMobile(mobile) : 'Not added'}</p>
      </div>
      <Button asChild className="mt-5 w-full bg-white/[0.08] font-black text-white hover:bg-white/[0.12]">
        <a href="https://ezwaynetwork.com">
          <Edit3 className="h-4 w-4" />
          Update Details
        </a>
      </Button>
    </section>
  )
}

function DevicePanel({
  currentDevice,
  otherDevices,
  onLogoutDevice,
  onLogoutAll,
}: {
  currentDevice: AccountDevice | null
  otherDevices: AccountDevice[]
  onLogoutDevice: (device: AccountDevice) => void
  onLogoutAll?: () => void
}) {
  return (
    <section className="rounded-md border border-white/10 bg-white/[0.035] p-5">
      <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex gap-4">
          <IconBadge tone="blue">
            <MonitorSmartphone className="h-5 w-5" />
          </IconBadge>
          <div>
            <p className="text-xs font-black uppercase tracking-[0.18em] text-white/38">Active Devices</p>
            <h2 className="mt-2 text-2xl font-black">Signed-In Devices</h2>
            <p className="mt-2 text-sm leading-6 text-white/56">Manage devices currently connected to this account.</p>
          </div>
        </div>
        {onLogoutAll ? (
          <Button type="button" onClick={onLogoutAll} className="bg-white/[0.08] font-black text-white hover:bg-white/[0.12]">
            <LogOut className="h-4 w-4" />
            Logout All
          </Button>
        ) : null}
      </div>

      <div className="grid gap-5">
        <div>
          <h3 className="mb-3 text-sm font-black uppercase tracking-[0.18em] text-white/38">Current Device</h3>
          {currentDevice ? (
            <DeviceCard device={currentDevice} current onLogout={() => onLogoutDevice(currentDevice)} />
          ) : (
            <EmptyLine text="No current device was detected." />
          )}
        </div>

        <div>
          <h3 className="mb-3 text-sm font-black uppercase tracking-[0.18em] text-white/38">Other Devices</h3>
          {otherDevices.length > 0 ? (
            <div className="grid gap-3 lg:grid-cols-2">
              {otherDevices.map((device) => (
                <DeviceCard key={`${device.id}-${device.device_id}`} device={device} onLogout={() => onLogoutDevice(device)} />
              ))}
            </div>
          ) : (
            <EmptyLine text="No other devices are signed in." />
          )}
        </div>
      </div>
    </section>
  )
}

function DeviceCard({ device, current, onLogout }: { device: AccountDevice; current?: boolean; onLogout: () => void }) {
  return (
    <div className="rounded-md border border-white/10 bg-black/20 p-4">
      <div className="flex items-center justify-between gap-4">
        <div className="min-w-0">
          {current ? (
            <span className="mb-2 inline-flex items-center gap-1.5 rounded-md border border-emerald-400/25 bg-emerald-500/10 px-2 py-1 text-xs font-bold text-emerald-200">
              <Check className="h-3.5 w-3.5" />
              Current Device
            </span>
          ) : null}
          <h4 className="truncate text-lg font-black">{device.device_name || device.platform || 'Unknown Device'}</h4>
          <p className="mt-1 truncate text-sm text-white/48">{device.platform || device.device_id || 'Device'}</p>
          <p className="mt-2 text-xs font-semibold text-white/38">{device.updated_at || device.created_at || 'Last activity unavailable'}</p>
        </div>
        <button
          type="button"
          onClick={onLogout}
          aria-label={`Logout ${device.device_name || 'device'}`}
          title="Logout device"
          className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-white/10 bg-white/[0.055] text-white transition hover:bg-white/[0.1]"
        >
          <LogOut className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}

function DangerZone({ onDeleteAccount }: { onDeleteAccount: () => void }) {
  return (
    <section className="rounded-md border border-red-400/20 bg-red-500/10 p-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex gap-4">
          <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-red-300/25 bg-red-400/12 text-red-100">
            <ShieldAlert className="h-5 w-5" />
          </span>
          <div>
            <h2 className="text-2xl font-black text-red-50">Danger Zone</h2>
            <p className="mt-2 text-sm leading-6 text-red-50/72">Deleting your account permanently removes your local eZWay TV data.</p>
          </div>
        </div>
        <Button type="button" onClick={onDeleteAccount} className="bg-red-500 font-black text-white hover:bg-red-400">
          <Trash2 className="h-4 w-4" />
          Delete Account
        </Button>
      </div>
    </section>
  )
}

function ConfirmModal({ action, pending, onClose }: { action: ConfirmAction; pending: boolean; onClose: () => void }) {
  const danger = action.tone === 'danger'

  return (
    <div className="fixed inset-0 z-[10000] flex items-center justify-center overflow-y-auto bg-black/72 px-4 py-6 backdrop-blur-sm">
      <div className="w-full max-w-[520px] rounded-md border border-white/10 bg-[#101010] shadow-2xl shadow-black">
        <div className="flex items-center justify-between gap-4 border-b border-white/8 px-5 py-4">
          <h2 className="text-xl font-black">{action.title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/[0.05] text-white transition hover:bg-white/[0.1]"
            aria-label="Close"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="grid gap-5 p-5">
          <div className={['flex items-center gap-4 rounded-md border p-4', danger ? 'border-red-400/20 bg-red-500/10 text-red-50' : 'border-[#d4a843]/24 bg-[#d4a843]/10 text-[#f3dc86]'].join(' ')}>
            <AlertTriangle className="h-6 w-6 shrink-0" />
            <p className="text-sm leading-6">{action.message}</p>
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            <Button type="button" variant="outline" onClick={onClose} disabled={pending} className="border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08]">
              Cancel
            </Button>
            <Button type="button" onClick={action.onConfirm} disabled={pending} className={danger ? 'bg-red-500 font-black text-white hover:bg-red-400' : 'bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]'}>
              {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {action.actionLabel}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

function IconBadge({ tone, children }: { tone: 'gold' | 'blue' | 'muted'; children: ReactNode }) {
  const classes = {
    gold: 'border-[#d4a843]/35 bg-[#d4a843]/14 text-[#edc342]',
    blue: 'border-sky-300/25 bg-sky-400/10 text-sky-200',
    muted: 'border-white/10 bg-white/[0.055] text-white/58',
  }[tone]

  return <span className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-md border ${classes}`}>{children}</span>
}

function InfoTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-white/10 bg-black/20 px-4 py-3">
      <p className="text-xs font-black uppercase tracking-[0.16em] text-white/36">{label}</p>
      <p className="mt-2 truncate text-sm font-black text-white">{value}</p>
    </div>
  )
}

function EmptyLine({ text }: { text: string }) {
  return <div className="rounded-md border border-white/10 bg-black/20 px-4 py-5 text-sm font-semibold text-white/44">{text}</div>
}

function Notice({ tone, text, onClose }: { tone: 'success' | 'error'; text: string; onClose: () => void }) {
  const classes = tone === 'success'
    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
    : 'border-red-400/20 bg-red-500/10 text-red-100'

  return (
    <div className={`mb-6 flex items-start justify-between gap-3 rounded-md border px-4 py-3 text-sm font-semibold ${classes}`}>
      <span>{text}</span>
      <button type="button" onClick={onClose} className="shrink-0 text-current opacity-70 hover:opacity-100" aria-label="Dismiss message">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function maskMobile(value: string) {
  const trimmed = value.trim()
  if (trimmed.length <= 3) return trimmed

  return `${'*'.repeat(Math.max(0, trimmed.length - 3))}${trimmed.slice(-3)}`
}

function priceLabel(plan: Record<string, unknown> | null) {
  const raw = plan?.total_price ?? plan?.price ?? plan?.amount

  if (raw === null || raw === undefined || raw === '') {
    return '-'
  }

  const amount = Number(raw)
  return Number.isFinite(amount) ? `$${amount.toFixed(2)}` : String(raw)
}

function stringValue(value: unknown) {
  return typeof value === 'string' && value.trim() ? value.trim() : ''
}

function readApiError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    const payload = error.payload as { message?: string; errors?: Record<string, string[] | string> } | null
    const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null

    return firstError || payload?.message || fallback
  }

  return error instanceof Error ? error.message : fallback
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}
