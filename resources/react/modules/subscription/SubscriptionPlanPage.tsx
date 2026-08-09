import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, AtSign, Check, CreditCard, Crown, KeyRound, Loader2, Mail, Phone, RefreshCw, ShieldCheck, ShoppingCart, UserRound, X } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { ApiError, api } from '@/lib/api'
import { loadAccountSettings } from '@/modules/account/accountApi'

type CheckoutAuthMode = 'email' | 'otp' | 'register'

type CheckoutAuthResponse = {
  status?: boolean
  message?: string
  errors?: Record<string, string[] | string>
  available?: boolean
  data?: { redirect_url?: string }
  retry_after_seconds?: unknown
}

type Plan = {
  source?: 'local' | 'core'
  slug?: string | null
  category_name?: string | null
  category_slug?: string | null
  plan_id: number
  name: string
  identifier?: string | null
  price?: number | string | null
  discount_percentage?: number | string | null
  total_price?: number | string | null
  level?: number | string | null
  duration?: string | null
  duration_value?: number | string | null
  description?: string | null
  plan_type?: PlanLimitation[]
}

type PlanLimitation = {
  id?: number | string
  limitation_title?: string | null
  limitation_value?: number | boolean | string | null
  slug?: string | null
  limit?: { value?: string | number | null } | string | number | null
  message?: string | null
}

type PlanListEnvelope = {
  status?: boolean
  data?: {
    data?: Plan[]
  } | Plan[]
  message?: string
}

type CorePackage = {
  id: number
  name: string
  slug?: string | null
  description?: string | null
  status?: string | null
  billing?: {
    type?: string | null
    period?: string | null
    period_count?: number | string | null
    price?: number | string | null
    currency?: string | null
    trial_enabled?: boolean
    trial_days?: number | string | null
  }
  features?: Array<string | { title?: string; name?: string; message?: string }>
  categories?: Array<{ name?: string | null; slug?: string | null }>
}

type CorePackageEnvelope = {
  data?: CorePackage[]
  category?: { name?: string | null; slug?: string | null }
  message?: string
}

type CheckoutResponse = {
  success?: boolean
  checkout_type?: string
  payment_status?: string
  redirect_url?: string
  message?: string
  data?: {
    checkout_type?: string
    payment_status?: string
    redirect_url?: string
  }
}

type PaymentMethod = {
  id: string
  brand?: string | null
  last4?: string | null
  exp_month?: string | number | null
  exp_year?: string | number | null
  label?: string | null
}

type PaymentMethodsResponse = {
  data?: PaymentMethod[]
  customer_id?: string | null
  is_billable?: boolean | null
  message?: string
}

export function SubscriptionPlanPage() {
  const [checkoutPlanId, setCheckoutPlanId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [checkoutError, setCheckoutError] = useState('')
  const [previewPlan, setPreviewPlan] = useState<Plan | null>(null)
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([])
  const [paymentMethodsMessage, setPaymentMethodsMessage] = useState('')
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState('')
  const [paymentMethodsLoading, setPaymentMethodsLoading] = useState(false)
  const [checkoutSignedIn, setCheckoutSignedIn] = useState(window.isAuthenticated !== false)
  const checkoutStatus = new URLSearchParams(window.location.search).get('checkout_status')
  const checkoutSucceeded = checkoutStatus === 'success'

  const plansQuery = useQuery({
    queryKey: ['subscription-plans'],
    queryFn: loadPlans,
    staleTime: 5 * 60_000,
  })

  const channelServicesQuery = useQuery({
    queryKey: ['tv-channel-service-packages'],
    queryFn: loadChannelServicePlans,
    staleTime: 5 * 60_000,
  })

  const accountQuery = useQuery({
    queryKey: ['account-settings', 'subscription-plan'],
    queryFn: loadAccountSettings,
    enabled: window.isAuthenticated !== false,
    staleTime: 30_000,
  })

  const activeSubscription = accountQuery.data?.plan_details ?? null
  const hasActiveSubscription = isActiveSubscription(activeSubscription)
  const plans = plansQuery.data ?? []
  const channelServicePlans = channelServicesQuery.data ?? []
  const filteredPlans = useMemo(() => plans.filter(isPremiumContentPlan), [plans])

  useEffect(() => {
    if (checkoutSucceeded && window.isAuthenticated !== false) {
      void accountQuery.refetch()
    }
  }, [checkoutSucceeded])

  async function handleChoose(plan: Plan) {
    setError('')
    setCheckoutError('')

    if (plan.source === 'core') {
      setPreviewPlan(plan)
      setSelectedPaymentMethodId('')
      setPaymentMethods([])

      if (checkoutSignedIn || window.isAuthenticated !== false) {
        await loadCheckoutPaymentMethods()
      } else {
        setPaymentMethodsLoading(false)
      }
      return
    }

    await startCheckout(plan, '')
  }

  async function loadCheckoutPaymentMethods() {
    setPaymentMethodsLoading(true)
    try {
      const response = await api.get<PaymentMethodsResponse>('/core/payment-methods')
      const methods = Array.isArray(response.data) ? response.data : []
      setPaymentMethods(methods)
      setPaymentMethodsMessage(response.message ?? '')
      setSelectedPaymentMethodId(methods[0]?.id ?? '')
    } catch (paymentMethodError) {
      setPaymentMethods([])
      setPaymentMethodsMessage('')
      setSelectedPaymentMethodId('')
      setCheckoutError(checkoutErrorMessage(paymentMethodError))
    } finally {
      setPaymentMethodsLoading(false)
    }
  }

  async function handleCheckoutSignedIn() {
    window.isAuthenticated = true
    setCheckoutSignedIn(true)
    await accountQuery.refetch()
    await loadCheckoutPaymentMethods()
  }

  async function startCheckout(plan: Plan, paymentMethodId: string) {
    setError('')
    setCheckoutError('')
    setCheckoutPlanId(plan.plan_id)

    try {
      if (plan.source === 'core') {
        const response = await api.post<CheckoutResponse>('/core/checkouts', {
          package_slug: plan.slug,
          payment_method_id: paymentMethodId || null,
        })
        const redirectUrl = response.data?.redirect_url || response.redirect_url

        if (!redirectUrl) {
          throw new Error(response.message || 'Core checkout is not available.')
        }

        window.location.href = redirectUrl
        return
      }

      if (!purchaseUrlForPlan(plan)) {
        throw new Error('Checkout is not configured for this plan yet.')
      }

      const formData = new FormData()
      formData.set('plan_id', String(plan.plan_id))
      formData.set('plan_name', plan.name)

      const response = await api.post<CheckoutResponse>('/select-plan', formData)

      if (!response.success || !response.redirect_url) {
        throw new Error(response.message || 'External checkout is not available.')
      }

      window.location.href = response.redirect_url
    } catch (checkoutError) {
      const message = checkoutErrorMessage(checkoutError)
      if (plan.source === 'core') {
        setCheckoutError(message)
      } else {
        setError(message)
      }
      setCheckoutPlanId(null)
    }
  }

  function closeCheckoutPreview() {
    if (checkoutPlanId) return
    setPreviewPlan(null)
    setCheckoutError('')
    setPaymentMethods([])
    setSelectedPaymentMethodId('')
  }
  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <section className="border-b border-white/8 bg-[radial-gradient(circle_at_78%_0%,rgba(212,168,67,0.20),transparent_28%),linear-gradient(180deg,#0b0b0b_0%,#050505_100%)] px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">
                <Crown className="h-4 w-4" />
                Subscription Plans
              </span>
              <h1 className="mt-5 text-4xl font-black leading-none sm:text-5xl">Choose Your Plan</h1>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">
                Choose the active TV subscription package from eZWay Core. Payment is completed securely through the connected checkout flow.
              </p>
            </div>

            <div className="rounded-md border border-[#d4a843]/24 bg-[#d4a843]/10 px-4 py-3 text-sm font-black text-[#edc342]">
              TV Subscription
            </div>
          </div>
        </div>
      </section>

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          {checkoutSucceeded ? (
            <div className="mb-6 flex gap-3 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-5 py-4 text-sm font-semibold leading-6 text-emerald-100">
              <Check className="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" />
              <span>Payment successful. Your TV subscription is active, and this plan is now marked as your current plan.</span>
            </div>
          ) : null}

          {error ? (
            <div className="mb-6 rounded-md border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100">
              {error}
            </div>
          ) : null}

          {plansQuery.isLoading ? (
            <PricingPlanSkeletonGrid />
          ) : filteredPlans.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {filteredPlans.map((plan, index) => (
                <PlanCard
                  key={plan.plan_id}
                  plan={plan}
                  featured={index === 0}
                  loading={checkoutPlanId === plan.plan_id}
                  current={(hasActiveSubscription && isCurrentPlan(plan, activeSubscription)) || (checkoutSucceeded && plan.source === 'core' && isPremiumContentPlan(plan))}
                  onChoose={() => handleChoose(plan)}
                />
              ))}
            </div>
          ) : (
            <div className="rounded-md border border-white/10 bg-white/[0.035] p-8 text-center text-white/56">
              No active TV subscription packages found.
            </div>
          )}
        </div>
      </section>

      {previewPlan ? (
        <CheckoutPreviewModal
          plan={previewPlan}
          methods={paymentMethods}
          message={paymentMethodsMessage}
          selectedMethodId={selectedPaymentMethodId}
          onSelectMethod={setSelectedPaymentMethodId}
          loadingMethods={paymentMethodsLoading}
          checkoutBusy={checkoutPlanId === previewPlan.plan_id}
          error={checkoutError}
          isSignedIn={checkoutSignedIn || window.isAuthenticated !== false}
          onSignedIn={handleCheckoutSignedIn}
          onClose={closeCheckoutPreview}
          onCheckout={() => startCheckout(previewPlan, selectedPaymentMethodId)}
        />
      ) : null}
      <section className="border-t border-white/8 px-4 pb-14 pt-2 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1800px]">
          <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-xs font-black uppercase tracking-[0.22em] text-[#d4a843]">Additional Channel Services</p>
              <h2 className="mt-2 text-2xl font-black sm:text-3xl">eZWay TV Special Paid Services</h2>
            </div>
            <p className="max-w-2xl text-sm leading-6 text-white/52">
              These are separate service purchases and are not part of the viewer subscription plans above.
            </p>
          </div>

          {channelServicesQuery.isLoading ? (
            <PricingPlanSkeletonGrid compact />
          ) : channelServicePlans.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-3">
              {channelServicePlans.map((plan, index) => (
                <ChannelServiceCard
                  key={plan.plan_id}
                  plan={plan}
                  featured={index === 0}
                  loading={checkoutPlanId === plan.plan_id}
                  onChoose={() => handleChoose(plan)}
                />
              ))}
            </div>
          ) : (
            <div className="rounded-md border border-white/10 bg-white/[0.035] p-8 text-center text-white/56">
              No active channel service packages found.
            </div>
          )}
        </div>
      </section>
    </main>
  )
}

function PricingPlanSkeletonGrid({ compact = false }: { compact?: boolean }) {
  return (
    <div className={compact ? 'grid gap-4 md:grid-cols-3' : 'grid gap-4 md:grid-cols-2 xl:grid-cols-3'}>
      {Array.from({ length: compact ? 3 : 3 }).map((_, index) => (
        <PricingPlanSkeletonCard key={index} compact={compact} featured={index === 0} />
      ))}
    </div>
  )
}

function PricingPlanSkeletonCard({ compact, featured }: { compact: boolean; featured: boolean }) {
  return (
    <article className={['relative overflow-hidden rounded-md border p-5 shadow-2xl shadow-black/20', featured ? 'border-[#d4a843]/28 bg-[#d4a843]/8' : 'border-white/10 bg-white/[0.045]'].join(' ')}>
      <div className="pointer-events-none absolute inset-0 -translate-x-full animate-[pricing-shimmer_1.55s_infinite] bg-[linear-gradient(110deg,transparent_0%,rgba(255,255,255,0.075)_45%,transparent_62%)]" />
      <div className="relative flex min-h-12 items-start justify-between gap-3">
        <div className="w-full max-w-[70%]">
          <div className="h-3 w-24 rounded-full bg-[#d4a843]/20" />
          <div className="mt-4 h-7 w-full rounded-md bg-white/10" />
        </div>
        <div className="h-7 w-20 rounded-full bg-white/10" />
      </div>
      <div className="relative mt-7 flex items-end gap-2">
        <div className="h-10 w-32 rounded-md bg-white/12" />
        <div className="mb-1 h-4 w-16 rounded bg-white/8" />
      </div>
      <div className="relative mt-7 grid gap-3">
        {Array.from({ length: compact ? 3 : 5 }).map((_, itemIndex) => (
          <div key={itemIndex} className="flex items-center gap-3">
            <div className="h-4 w-4 rounded-full bg-[#d4a843]/20" />
            <div className={['h-4 rounded bg-white/9', itemIndex % 3 === 0 ? 'w-10/12' : itemIndex % 3 === 1 ? 'w-8/12' : 'w-9/12'].join(' ')} />
          </div>
        ))}
      </div>
      <div className="relative mt-8 h-12 rounded-md bg-white/12" />
    </article>
  )
}

function PlanCard({
  plan,
  featured,
  loading,
  current,
  onChoose,
}: {
  plan: Plan
  featured: boolean
  loading: boolean
  current: boolean
  onChoose: () => void
}) {
  const price = Number(plan.total_price ?? plan.price ?? 0)
  const rawPrice = Number(plan.price ?? price)
  const discount = Number(plan.discount_percentage ?? 0)
  const limitations = (plan.plan_type ?? []).filter((item) => item.message || item.limitation_title).slice(0, 6)
  const purchaseUrl = purchaseUrlForPlan(plan)

  return (
    <article className={['rounded-md border p-5 shadow-2xl shadow-black/20', featured ? 'border-[#d4a843]/60 bg-[#d4a843]/10' : 'border-white/10 bg-white/[0.045]'].join(' ')}>
      <div className="flex min-h-12 items-start justify-between gap-3">
        <div>
          <p className="text-xs font-black uppercase tracking-[0.18em] text-[#d4a843]">Level {plan.level ?? '-'}</p>
          <h2 className="mt-2 text-2xl font-black">{plan.name}</h2>
        </div>
        {featured ? (
          <span className="rounded-full bg-[#d4a843] px-3 py-1 text-[11px] font-black uppercase text-black">Featured</span>
        ) : null}
      </div>

      <div className="mt-6">
        <div className="flex items-end gap-2">
          <span className="text-4xl font-black">${formatMoney(price)}</span>
          <span className="pb-1 text-sm font-bold text-white/48">/ {durationLabel(plan)}</span>
        </div>
        {discount > 0 ? (
          <p className="mt-2 text-sm font-semibold text-white/48">
            <span className="line-through">${formatMoney(rawPrice)}</span> Save {discount}%
          </p>
        ) : null}
      </div>

      {plan.description ? (
        <p className="mt-5 text-sm leading-6 text-white/58">{plan.description}</p>
      ) : null}

      {limitations.length > 0 ? (
        <ul className="mt-6 grid gap-3">
          {limitations.map((item, index) => (
            <li key={item.id ?? item.slug ?? index} className="flex gap-3 text-sm leading-5 text-white/68">
              <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
              <span>{item.message || item.limitation_title}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <button
        type="button"
        onClick={onChoose}
        disabled={current || loading || (!purchaseUrl && plan.source !== 'core')}
        className={['mt-7 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-70', featured ? 'bg-[#d4a843] text-black hover:bg-[#efc955]' : 'bg-white text-black hover:bg-white/84'].join(' ')}
      >
        {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : current ? <Check className="h-5 w-5" /> : <CreditCard className="h-5 w-5" />}
        {loading ? 'Opening Checkout...' : current ? 'Current Plan' : 'Choose Plan'}
      </button>

      <p className="mt-4 flex items-center justify-center gap-2 text-xs font-semibold text-white/42">
        <ShieldCheck className="h-4 w-4 text-[#d4a843]" />
        Core TV package
      </p>
    </article>
  )
}

function CheckoutPreviewModal({
  plan,
  methods,
  message,
  selectedMethodId,
  onSelectMethod,
  loadingMethods,
  checkoutBusy,
  error,
  isSignedIn,
  onSignedIn,
  onClose,
  onCheckout,
}: {
  plan: Plan
  methods: PaymentMethod[]
  message?: string
  selectedMethodId: string
  onSelectMethod: (id: string) => void
  loadingMethods: boolean
  checkoutBusy: boolean
  error: string
  isSignedIn: boolean
  onSignedIn: () => Promise<void>
  onClose: () => void
  onCheckout: () => void
}) {
  const price = Number(plan.total_price ?? plan.price ?? 0)

  return (
    <div className="fixed inset-0 z-[80] flex items-end justify-center bg-black/72 px-3 py-4 backdrop-blur-sm sm:items-center sm:px-5" role="dialog" aria-modal="true">
      <div className="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-white/12 bg-[#101010] shadow-2xl shadow-black/60">
        <div className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-white/10 bg-[#101010]/95 px-5 py-4 backdrop-blur">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.18em] text-[#d4a843]">Checkout preview</p>
            <h2 className="mt-1 text-xl font-black text-white">Complete your TV subscription</h2>
          </div>
          <button type="button" onClick={onClose} disabled={checkoutBusy} className="grid h-10 w-10 place-items-center rounded-full bg-white/8 text-white transition hover:bg-white/14 disabled:opacity-50" aria-label="Close checkout preview">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
          <section className="rounded-xl border border-white/10 bg-white/[0.045] p-5">
            <div className="flex items-start gap-4">
              <span className="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-[#d4a843] text-black">
                <Crown className="h-7 w-7" />
              </span>
              <div className="min-w-0">
                <p className="text-lg font-black text-white">{plan.name}</p>
                <p className="mt-1 line-clamp-3 text-sm leading-6 text-white/56">{plan.description || 'Premium eZWay TV content access handled securely by Core.'}</p>
              </div>
            </div>

            <div className="mt-5 rounded-xl bg-black/22 p-4 ring-1 ring-white/8">
              <div className="flex items-center justify-between gap-4 text-sm">
                <span className="text-white/56">Subscription</span>
                <span className="font-black text-white">${formatMoney(price)} / {durationLabel(plan)}</span>
              </div>
              <div className="mt-3 flex items-center justify-between border-t border-white/10 pt-3">
                <span className="text-sm font-bold text-white/70">Total today</span>
                <span className="text-2xl font-black text-white">${formatMoney(price)}</span>
              </div>
            </div>
          </section>

          <aside className="rounded-xl border border-white/10 bg-white/[0.045] p-5">
            {isSignedIn ? (
              <>
                <div className="flex items-center gap-3">
                  <span className="grid h-11 w-11 place-items-center rounded-full bg-[#d4a843] text-black">
                    <CreditCard className="h-5 w-5" />
                  </span>
                  <div>
                    <p className="text-sm font-black text-white">Payment method</p>
                    <p className="text-xs text-white/48">Saved card or add a new one</p>
                  </div>
                </div>

                <PaymentMethodSelector
                  methods={methods}
                  message={message}
                  selectedMethodId={selectedMethodId}
                  onSelect={onSelectMethod}
                  loading={loadingMethods}
                />

                {error ? (
                  <div className="mt-5 flex gap-3 rounded-xl border border-red-400/25 bg-red-500/10 p-4 text-sm font-semibold leading-6 text-red-100">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-300" />
                    <span>{error}</span>
                  </div>
                ) : null}

                <button
                  type="button"
                  onClick={onCheckout}
                  disabled={checkoutBusy || loadingMethods}
                  className="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#d4a843] px-4 text-sm font-black text-black transition hover:bg-[#efc955] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {checkoutBusy ? <Loader2 className="h-4 w-4 animate-spin" /> : <CreditCard className="h-4 w-4" />}
                  {checkoutBusy ? 'Processing...' : selectedMethodId ? 'Pay with selected card' : 'Add new card / Checkout'}
                </button>

                <p className="mt-3 flex gap-2 text-xs leading-5 text-white/44">
                  <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
                  {selectedMethodId ? 'Core will try this saved card first. If it fails, you will be sent to hosted checkout.' : 'Hosted checkout lets you add a new card securely.'}
                </p>
              </>
            ) : (
              <CheckoutAuthPanel onSignedIn={onSignedIn} />
            )}
          </aside>
        </div>
      </div>
    </div>
  )
}

function CheckoutAuthPanel({ onSignedIn }: { onSignedIn: () => Promise<void> }) {
  const [mode, setMode] = useState<CheckoutAuthMode>('email')
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)
  const [email, setEmail] = useState('')
  const [otp, setOtp] = useState('')
  const [inviteCode, setInviteCode] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [username, setUsername] = useState('')
  const [phone, setPhone] = useState('')
  const [blockedUntil, setBlockedUntil] = useState<number | null>(null)

  const cleanEmail = email.trim().toLowerCase()
  const cleanOtp = otp.replace(/\D/g, '').slice(0, 4)
  const canCreate = firstName.trim() && lastName.trim() && username.trim().length >= 3 && cleanEmail
  const blockedSeconds = useCountdown(blockedUntil)

  async function sendOtp() {
    setBusy(true)
    setMessage(null)
    try {
      const formData = new FormData()
      formData.set('email', cleanEmail)
      const response = await api.post<CheckoutAuthResponse>('/auth/spa-otp/send', formData)
      ensureCheckoutAuthSuccess(response, 'We sent a login code to your email.')
      setMode('otp')
      setMessage({ tone: 'success', text: checkoutAuthMessage(response, 'We sent a login code to your email.') })
    } catch (error) {
      const text = checkoutErrorMessage(error)
      const retryAfter = checkoutRetryAfterSeconds(error)
      if (retryAfter > 0) setBlockedUntil(Date.now() + retryAfter * 1000)
      if (error instanceof ApiError && error.status === 404) {
        setMode('register')
        setMessage({ tone: 'error', text: 'No TV account found for this email. Create your account here to continue checkout.' })
      } else {
        setMessage({ tone: 'error', text })
      }
    } finally {
      setBusy(false)
    }
  }

  async function verifyOtp() {
    setBusy(true)
    setMessage(null)
    try {
      const formData = new FormData()
      formData.set('email', cleanEmail)
      formData.set('otp', cleanOtp)
      const response = await api.post<CheckoutAuthResponse>('/auth/spa-otp/verify', formData)
      ensureCheckoutAuthSuccess(response, 'You are signed in.')
      setMessage({ tone: 'success', text: checkoutAuthMessage(response, 'You are signed in. Loading checkout...') })
      await onSignedIn()
    } catch (error) {
      const retryAfter = checkoutRetryAfterSeconds(error)
      if (retryAfter > 0) setBlockedUntil(Date.now() + retryAfter * 1000)
      setMessage({ tone: 'error', text: checkoutErrorMessage(error) })
    } finally {
      setBusy(false)
    }
  }

  async function createAccount() {
    setBusy(true)
    setMessage(null)
    try {
      const formData = new FormData()
      formData.set('invite_code', inviteCode.trim())
      formData.set('first_name', firstName.trim())
      formData.set('last_name', lastName.trim())
      formData.set('username', username.trim())
      formData.set('email', cleanEmail)
      formData.set('phone_number', phone.trim())
      const response = await api.post<CheckoutAuthResponse>('/auth/spa-register', formData)
      ensureCheckoutAuthSuccess(response, 'Your account is ready. We sent a login code to your email.')
      setMode('otp')
      setMessage({ tone: 'success', text: checkoutAuthMessage(response, 'Your account is ready. We sent a login code to your email.') })
    } catch (error) {
      setMessage({ tone: 'error', text: checkoutErrorMessage(error) })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <div className="flex items-center gap-3">
        <span className="grid h-11 w-11 place-items-center rounded-full bg-[#d4a843] text-black">
          {mode === 'register' ? <UserRound className="h-5 w-5" /> : mode === 'otp' ? <KeyRound className="h-5 w-5" /> : <Mail className="h-5 w-5" />}
        </span>
        <div>
          <p className="text-sm font-black text-white">Checkout sign in</p>
          <p className="text-xs text-white/48">Create or verify your account here</p>
        </div>
      </div>

      {message ? (
        <div className={["mt-5 rounded-xl border p-4 text-sm font-semibold leading-6", message.tone === 'success' ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-100' : 'border-red-400/25 bg-red-500/10 text-red-100'].join(' ')}>
          {message.text}
        </div>
      ) : null}
      {blockedSeconds > 0 ? (
        <div className="mt-5 rounded-xl border border-red-400/25 bg-red-500/10 p-4 text-sm font-semibold leading-6 text-red-100">
          You are temporarily blocked. Try again in <span className="font-black text-white">{formatCountdown(blockedSeconds)}</span>.
        </div>
      ) : null}

      {mode === 'email' ? (
        <form className="mt-5 grid gap-4" onSubmit={(event) => { event.preventDefault(); void sendOtp() }}>
          <CheckoutInput icon={<Mail className="h-4 w-4" />} label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" required />
          <button type="submit" disabled={busy || blockedSeconds > 0 || !cleanEmail} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#d4a843] px-4 text-sm font-black text-black transition hover:bg-[#efc955] disabled:cursor-not-allowed disabled:opacity-60">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}
            {busy ? 'Checking...' : 'Continue with Email'}
          </button>
        </form>
      ) : null}

      {mode === 'otp' ? (
        <form className="mt-5 grid gap-4" onSubmit={(event) => { event.preventDefault(); void verifyOtp() }}>
          <CheckoutInput icon={<Mail className="h-4 w-4" />} label="Email" type="email" value={email} onChange={setEmail} disabled />
          <CheckoutInput icon={<KeyRound className="h-4 w-4" />} label="Login code" value={otp} onChange={(value) => setOtp(value.replace(/\D/g, '').slice(0, 4))} inputMode="numeric" autoComplete="one-time-code" maxLength={4} required />
          <button type="submit" disabled={busy || blockedSeconds > 0 || cleanOtp.length !== 4} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#d4a843] px-4 text-sm font-black text-black transition hover:bg-[#efc955] disabled:cursor-not-allowed disabled:opacity-60">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <KeyRound className="h-4 w-4" />}
            {busy ? 'Verifying...' : 'Verify and Continue'}
          </button>
          <button type="button" disabled={busy} onClick={() => { setOtp(''); setMode('email'); setMessage(null) }} className="inline-flex items-center justify-center gap-2 text-sm font-black text-[#f0c74b] transition hover:text-white disabled:opacity-60">
            <RefreshCw className="h-4 w-4" /> Use a different email
          </button>
        </form>
      ) : null}

      {mode === 'register' ? (
        <form className="mt-5 grid gap-4" onSubmit={(event) => { event.preventDefault(); void createAccount() }}>
          <CheckoutInput icon={<Mail className="h-4 w-4" />} label="Email" type="email" value={email} onChange={setEmail} autoComplete="email" required />
          <CheckoutInput icon={<AtSign className="h-4 w-4" />} label="Invite code" value={inviteCode} onChange={setInviteCode} autoComplete="off" />
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
            <CheckoutInput icon={<UserRound className="h-4 w-4" />} label="First name" value={firstName} onChange={setFirstName} autoComplete="given-name" required />
            <CheckoutInput icon={<UserRound className="h-4 w-4" />} label="Last name" value={lastName} onChange={setLastName} autoComplete="family-name" required />
          </div>
          <CheckoutInput icon={<AtSign className="h-4 w-4" />} label="Username" value={username} onChange={(value) => setUsername(value.replace(/\s+/g, '').slice(0, 32))} autoComplete="username" required />
          <CheckoutInput icon={<Phone className="h-4 w-4" />} label="Phone" type="tel" value={phone} onChange={setPhone} autoComplete="tel" />
          <button type="submit" disabled={busy || !canCreate} className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#d4a843] px-4 text-sm font-black text-black transition hover:bg-[#efc955] disabled:cursor-not-allowed disabled:opacity-60">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserRound className="h-4 w-4" />}
            {busy ? 'Creating...' : 'Create Account and Continue'}
          </button>
          <button type="button" disabled={busy} onClick={() => { setMode('email'); setMessage(null) }} className="text-sm font-black text-[#f0c74b] transition hover:text-white disabled:opacity-60">
            Already have an account? Send login code
          </button>
        </form>
      ) : null}
    </div>
  )
}

function CheckoutInput({
  icon,
  label,
  value,
  onChange,
  type = 'text',
  autoComplete,
  inputMode,
  required,
  disabled,
  maxLength,
}: {
  icon: React.ReactNode
  label: string
  value: string
  onChange: (value: string) => void
  type?: string
  autoComplete?: string
  inputMode?: 'none' | 'text' | 'tel' | 'url' | 'email' | 'numeric' | 'decimal' | 'search'
  required?: boolean
  disabled?: boolean
  maxLength?: number
}) {
  return (
    <label className="grid gap-2">
      <span className="text-xs font-black uppercase tracking-[0.16em] text-white/46">{label}</span>
      <span className="flex h-12 items-center gap-3 rounded-xl border border-white/10 bg-black/28 px-3 text-white transition focus-within:border-[#d4a843]/70 focus-within:bg-black/40">
        <span className="text-[#d4a843]">{icon}</span>
        <input value={value} onChange={(event) => onChange(event.target.value)} type={type} autoComplete={autoComplete} inputMode={inputMode} required={required} disabled={disabled} maxLength={maxLength} className="h-full min-w-0 flex-1 bg-transparent text-sm font-semibold outline-none placeholder:text-white/32 disabled:cursor-not-allowed disabled:text-white/50" />
      </span>
    </label>
  )
}

function PaymentMethodSelector({
  methods,
  message,
  selectedMethodId,
  onSelect,
  loading,
}: {
  methods: PaymentMethod[]
  message?: string
  selectedMethodId: string
  onSelect: (id: string) => void
  loading: boolean
}) {
  if (loading) {
    return (
      <div className="mt-4 flex items-center gap-2 rounded-xl bg-black/20 p-4 text-sm font-semibold text-white/52 ring-1 ring-white/8">
        <Loader2 className="h-4 w-4 animate-spin" /> Loading saved cards
      </div>
    )
  }

  if (!methods.length) {
    return (
      <div className="mt-4 rounded-xl bg-black/20 p-4 text-sm leading-6 text-white/52 ring-1 ring-white/8">
        {message || 'No saved cards were found. Continue to checkout to add a card securely.'}
      </div>
    )
  }

  return (
    <div className="mt-4 grid gap-2">
      {methods.map((method) => (
        <label key={method.id} className={['flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-3 text-sm transition', selectedMethodId === method.id ? 'border-[#d4a843]/70 bg-[#d4a843]/12 text-white' : 'border-white/10 bg-black/18 text-white/68 hover:bg-white/8'].join(' ')}>
          <input type="radio" name="tv-payment-method" value={method.id} checked={selectedMethodId === method.id} onChange={() => onSelect(method.id)} className="accent-[#d4a843]" />
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/8">
            <CreditCard className="h-4 w-4" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block truncate font-black">{paymentMethodLabel(method)}</span>
            {method.exp_month && method.exp_year ? <span className="block text-xs text-white/42">Expires {method.exp_month}/{method.exp_year}</span> : null}
          </span>
        </label>
      ))}
      <button type="button" onClick={() => onSelect('')} className="mt-1 text-left text-sm font-bold text-white/50 transition hover:text-white">
        Use another card / add new card
      </button>
    </div>
  )
}
function ChannelServiceCard({
  plan,
  featured,
  loading,
  onChoose,
}: {
  plan: Plan
  featured: boolean
  loading: boolean
  onChoose: () => void
}) {
  const price = Number(plan.total_price ?? plan.price ?? 0)
  const limitations = (plan.plan_type ?? []).filter((item) => item.message || item.limitation_title).slice(0, 5)

  return (
    <article className={['rounded-md border p-5 shadow-2xl shadow-black/20', featured ? 'border-[#d4a843]/60 bg-[#d4a843]/10' : 'border-white/10 bg-white/[0.045]'].join(' ')}>
      <div className="flex items-start justify-between gap-3">
        <span className="rounded-sm bg-[#d4a843] px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-black">
          {plan.category_name || 'TV Package'}
        </span>
        <ShoppingCart className="h-7 w-7 shrink-0 text-[#d4a843]" />
      </div>

      <h3 className="mt-5 min-h-14 text-2xl font-black leading-tight">{plan.name}</h3>
      {plan.description ? <p className="mt-3 min-h-12 text-sm leading-6 text-white/58">{plan.description}</p> : null}

      <div className="mt-6 flex items-end gap-2">
        <span className="text-4xl font-black">${formatMoney(price)}</span>
        <span className="pb-1 text-sm font-bold text-white/48">/ {durationLabel(plan)}</span>
      </div>

      {limitations.length > 0 ? (
        <ul className="mt-6 grid gap-3">
          {limitations.map((item, index) => (
            <li key={String(item.id ?? item.slug ?? item.message ?? index)} className="flex gap-3 text-sm leading-5 text-white/68">
              <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
              <span>{item.message || item.limitation_title}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <button
        type="button"
        onClick={onChoose}
        disabled={loading}
        className={['mt-7 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition disabled:cursor-wait disabled:opacity-70', featured ? 'bg-[#d4a843] text-black hover:bg-[#efc955]' : 'bg-white text-black hover:bg-white/84'].join(' ')}
      >
        {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShoppingCart className="h-4 w-4" />}
        {loading ? 'Preparing...' : 'Choose Service'}
      </button>
    </article>
  )
}

async function loadPlans() {
  return loadCoreCategoryPlans('tv-subscription')
}

async function loadChannelServicePlans() {
  return loadCoreCategoryPlans('tv-packges')
}

async function loadCoreCategoryPlans(categorySlug: string) {
  const response = await api.get<CorePackageEnvelope>(`/api/core/package-categories/${categorySlug}/packages?status=active&limit=50`)
  const packages = Array.isArray(response.data) ? response.data : []

  return packages.map((item) => corePackageToPlan(item, response.category))
}

function corePackageToPlan(item: CorePackage, category?: CorePackageEnvelope['category']): Plan {
  const firstCategory = item.categories?.[0]
  const features = (item.features ?? []).map((feature, index) => {
    const message = typeof feature === 'string'
      ? feature
      : feature.message || feature.title || feature.name || ''

    return {
      id: `${item.id}-feature-${index}`,
      message,
    }
  }).filter((feature) => feature.message)

  return {
    source: 'core',
    plan_id: item.id,
    name: item.name,
    slug: item.slug,
    identifier: item.slug,
    price: item.billing?.price ?? 0,
    total_price: item.billing?.price ?? 0,
    duration: item.billing?.period || (item.billing?.type === 'one_time' ? 'one time' : 'month'),
    duration_value: item.billing?.period_count ?? 1,
    description: item.description,
    category_name: category?.name || firstCategory?.name || 'TV Subscription',
    category_slug: category?.slug || firstCategory?.slug || 'tv-subscription',
    plan_type: features,
  }
}


function isActiveSubscription(plan: unknown) {
  if (!plan || typeof plan !== 'object') return false
  const status = String((plan as { status?: unknown }).status ?? '').toLowerCase()
  return !status || ['active', 'trialing'].includes(status)
}

function isCurrentPlan(plan: Plan, activeSubscription: unknown) {
  if (!activeSubscription || typeof activeSubscription !== 'object') return false
  const subscription = activeSubscription as { plan_id?: unknown; package_id?: unknown; identifier?: unknown; slug?: unknown; name?: unknown }
  const localPlanId = Number(subscription.plan_id ?? subscription.package_id ?? 0)
  const subscriptionIdentifier = String(subscription.identifier ?? subscription.slug ?? '')

  if (plan.source === 'core') {
    if (subscriptionIdentifier && String(plan.identifier ?? plan.slug ?? '') === subscriptionIdentifier) return true
    return plan.category_slug === 'tv-subscription' && localPlanId > 0
  }

  return Number(plan.plan_id) === localPlanId
}
function durationLabel(plan: Plan) {
  const value = Number(plan.duration_value ?? 1)
  const unit = String(plan.duration ?? 'month').toLowerCase()
  const label = unit === 'year' ? 'year' : unit === 'week' ? 'week' : unit === 'day' ? 'day' : 'month'

  return value > 1 ? `${value} ${label}s` : label
}

function formatMoney(value: number) {
  return value.toFixed(2)
}

function paymentMethodLabel(method: PaymentMethod) {
  const brand = method.brand || 'Card'
  return method.last4 ? `${brand} ending in ${method.last4}` : (method.label || brand)
}

function purchaseUrlForPlan(plan: Plan) {
  const price = Number(plan.total_price ?? plan.price ?? 0).toFixed(2)

  if (price === '1.99') return 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38475'

  return ''
}

function isPremiumContentPlan(plan: Plan) {
  return plan.source === 'core' || plan.category_slug === 'tv-subscription'
}

function ensureCheckoutAuthSuccess(payload: CheckoutAuthResponse | null, fallback: string) {
  if (payload?.status === false) {
    throw new Error(checkoutAuthMessage(payload, fallback))
  }
}

function checkoutAuthMessage(payload: CheckoutAuthResponse | null, fallback: string) {
  if (typeof payload?.message === 'string' && payload.message.trim()) return payload.message
  if (payload?.errors) {
    const first = Object.values(payload.errors).flat().find(Boolean)
    if (first) return String(first)
  }
  return fallback
}

function checkoutErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const payload = error.payload as { message?: string; errors?: Record<string, string[] | string> } | null
    if (payload?.message) return payload.message

    const firstError = payload?.errors ? Object.values(payload.errors)[0] : null
    if (Array.isArray(firstError)) return firstError[0] ?? 'Unable to start checkout.'
    if (typeof firstError === 'string') return firstError
  }

  return error instanceof Error ? error.message : 'Unable to start checkout.'
}

function checkoutRetryAfterSeconds(error: unknown) {
  if (!(error instanceof ApiError)) return 0
  const payload = error.payload as { retry_after_seconds?: unknown } | null
  const seconds = Number(payload?.retry_after_seconds)
  return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : 0
}

function useCountdown(until: number | null) {
  const [seconds, setSeconds] = useState(0)

  useEffect(() => {
    if (!until) {
      setSeconds(0)
      return
    }

    const tick = () => setSeconds(Math.max(0, Math.ceil((until - Date.now()) / 1000)))
    tick()
    const interval = window.setInterval(tick, 1000)

    return () => window.clearInterval(interval)
  }, [until])

  return seconds
}

function formatCountdown(totalSeconds: number) {
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}
