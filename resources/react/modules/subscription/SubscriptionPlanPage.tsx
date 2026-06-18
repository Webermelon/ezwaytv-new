import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Check, CreditCard, Crown, ExternalLink, Loader2, Music2, Radio, ShieldCheck, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { ApiError, api } from '@/lib/api'

type Plan = {
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

type CheckoutResponse = {
  success?: boolean
  redirect_url?: string
  message?: string
}

const externalChannelOffers = [
  {
    title: 'Get Your Own VOD Channel',
    price: '199.99',
    label: 'On Demand Channel',
    description: 'Launch a branded VOD channel for your videos and audience.',
    href: 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38376',
    icon: Tv,
    featured: true,
    features: ['Branded VOD channel presence', 'Channel page for your content', 'External monthly service purchase'],
  },
  {
    title: 'Music Channel',
    price: '24.99',
    label: 'Music Promotion',
    description: 'Get your music video in rotation on the EZWAY Music Channel.',
    href: 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38358',
    icon: Music2,
    featured: false,
    features: ['Music video rotation', 'EZWAY Music Channel exposure', 'External monthly service purchase'],
  },
  {
    title: 'Live Channel Time Slot',
    price: '249.99',
    label: 'Live TV Placement',
    description: 'Reserve a time slot on one of our live channels.',
    href: 'mailto:info@ezwaynetwork.com?subject=eZWay%20TV%20Live%20Channel%20Time%20Slot',
    icon: Radio,
    featured: false,
    features: ['Live channel scheduling request', 'Placement on an eZWay live channel', 'Team confirmation required'],
  },
]

export function SubscriptionPlanPage() {
  const [checkoutPlanId, setCheckoutPlanId] = useState<number | null>(null)
  const [error, setError] = useState('')

  const plansQuery = useQuery({
    queryKey: ['subscription-plans'],
    queryFn: loadPlans,
    staleTime: 5 * 60_000,
  })

  const plans = plansQuery.data ?? []
  const filteredPlans = useMemo(() => plans.filter(isPremiumContentPlan), [plans])

  async function handleChoose(plan: Plan) {
    setError('')
    setCheckoutPlanId(plan.plan_id)

    if (window.isAuthenticated === false) {
      window.location.href = `/login?redirect=${encodeURIComponent('/subscription-plan')}`
      return
    }

    if (!purchaseUrlForPlan(plan)) {
      setError('No GetPaid purchase link is configured for this plan price.')
      setCheckoutPlanId(null)
      return
    }

    try {
      const formData = new FormData()
      formData.set('plan_id', String(plan.plan_id))
      formData.set('plan_name', plan.name)

      const response = await api.post<CheckoutResponse>('/select-plan', formData)

      if (!response.success || !response.redirect_url) {
        throw new Error(response.message || 'External checkout is not available.')
      }

      window.location.href = response.redirect_url
    } catch (checkoutError) {
      setError(checkoutErrorMessage(checkoutError))
      setCheckoutPlanId(null)
    }
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <section className="border-b border-white/8 bg-[radial-gradient(circle_at_78%_0%,rgba(212,168,67,0.20),transparent_28%),linear-gradient(180deg,#0b0b0b_0%,#050505_100%)] px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1500px]">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">
                <Crown className="h-4 w-4" />
                Subscription Plans
              </span>
              <h1 className="mt-5 text-4xl font-black leading-none sm:text-5xl">Choose Your Plan</h1>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">
                Plans are managed here. Recurring payment is completed securely on eZWay Network GetPaid.
              </p>
            </div>

            <div className="rounded-md border border-[#d4a843]/24 bg-[#d4a843]/10 px-4 py-3 text-sm font-black text-[#edc342]">
              Premium Content Access
            </div>
          </div>
        </div>
      </section>

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1500px]">
          {error ? (
            <div className="mb-6 rounded-md border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100">
              {error}
            </div>
          ) : null}

          {plansQuery.isLoading ? (
            <div className="flex min-h-64 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
              <Loader2 className="h-6 w-6 animate-spin text-[#d4a843]" />
            </div>
          ) : filteredPlans.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {filteredPlans.map((plan, index) => (
                <PlanCard
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
              No active plans found for this filter.
            </div>
          )}
        </div>
      </section>

      <section className="border-t border-white/8 px-4 pb-14 pt-2 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1500px]">
          <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-xs font-black uppercase tracking-[0.22em] text-[#d4a843]">Additional Channel Services</p>
              <h2 className="mt-2 text-2xl font-black sm:text-3xl">eZWay TV Special Paid Services</h2>
            </div>
            <p className="max-w-2xl text-sm leading-6 text-white/52">
              These are separate service purchases and are not part of the viewer subscription plans above.
            </p>
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            {externalChannelOffers.map((offer) => (
              <ExternalOfferCard key={offer.title} offer={offer} />
            ))}
          </div>
        </div>
      </section>
    </main>
  )
}

function PlanCard({
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

      <ul className="mt-6 grid gap-3">
        {(limitations.length > 0 ? limitations : fallbackFeatures()).map((item, index) => (
          <li key={item.id ?? item.slug ?? index} className="flex gap-3 text-sm leading-5 text-white/68">
            <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
            <span>{item.message || item.limitation_title}</span>
          </li>
        ))}
      </ul>

      <button
        type="button"
        onClick={onChoose}
        disabled={loading || !purchaseUrl}
        className={['mt-7 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-70', featured ? 'bg-[#d4a843] text-black hover:bg-[#efc955]' : 'bg-white text-black hover:bg-white/84'].join(' ')}
      >
        {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <CreditCard className="h-5 w-5" />}
        {loading ? 'Opening Checkout...' : 'Subscribe with GetPaid'}
      </button>

      <p className="mt-4 flex items-center justify-center gap-2 text-xs font-semibold text-white/42">
        <ShieldCheck className="h-4 w-4 text-[#d4a843]" />
        External recurring payment
      </p>
    </article>
  )
}

function ExternalOfferCard({ offer }: { offer: (typeof externalChannelOffers)[number] }) {
  const Icon = offer.icon
  const isMailto = offer.href.startsWith('mailto:')

  return (
    <article className={['rounded-md border p-5 shadow-2xl shadow-black/20', offer.featured ? 'border-[#d4a843]/60 bg-[#d4a843]/10' : 'border-white/10 bg-white/[0.045]'].join(' ')}>
      <div className="flex items-start justify-between gap-3">
        <span className="rounded-sm bg-[#d4a843] px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-black">
          {offer.label}
        </span>
        <Icon className="h-7 w-7 shrink-0 text-[#d4a843]" />
      </div>

      <h3 className="mt-5 min-h-14 text-2xl font-black leading-tight">{offer.title}</h3>
      <p className="mt-3 min-h-12 text-sm leading-6 text-white/58">{offer.description}</p>

      <div className="mt-6 flex items-end gap-2">
        <span className="text-4xl font-black">${offer.price}</span>
        <span className="pb-1 text-sm font-bold text-white/48">/ month</span>
      </div>

      <ul className="mt-6 grid gap-3">
        {offer.features.map((feature) => (
          <li key={feature} className="flex gap-3 text-sm leading-5 text-white/68">
            <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
            <span>{feature}</span>
          </li>
        ))}
      </ul>

      <a
        href={offer.href}
        target={isMailto ? undefined : '_blank'}
        rel={isMailto ? undefined : 'noreferrer'}
        className={['mt-7 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-black transition', offer.featured ? 'bg-[#d4a843] text-black hover:bg-[#efc955]' : 'bg-white text-black hover:bg-white/84'].join(' ')}
      >
        {isMailto ? 'Contact to Reserve' : 'Open External Checkout'}
        <ExternalLink className="h-4 w-4" />
      </a>
    </article>
  )
}

async function loadPlans() {
  const response = await api.get<PlanListEnvelope>('/api/plan-list?per_page=50')
  const data = response.data

  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data

  return []
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

function purchaseUrlForPlan(plan: Plan) {
  const price = Number(plan.total_price ?? plan.price ?? 0).toFixed(2)

  if (price === '1.99') return 'https://ezwaynetwork.com/ezway-tv-checkout/?item=38475'

  return ''
}

function isPremiumContentPlan(plan: Plan) {
  const price = Number(plan.total_price ?? plan.price ?? 0).toFixed(2)
  return price === '1.99' && Number(plan.level ?? 0) === 4
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

function fallbackFeatures(): PlanLimitation[] {
  return [
    { id: 'streaming', message: 'Access eligible eZWay TV content.' },
    { id: 'external', message: 'Recurring billing through eZWay Network.' },
    { id: 'account', message: 'Plan record is saved to your local account.' },
  ]
}
