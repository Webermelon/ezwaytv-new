import { ReactNode, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Clock3, CreditCard, PackageCheck, Search, Truck, XCircle } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { AccountHero, AccountSidebar } from '@/modules/account/ProfileDetailsPage'
import { ApiError, api } from '@/lib/api'

type OrdersResponse = {
  data?: {
    service_orders?: ServiceOrder[]
  }
  message?: string
}

type CorePackageEnvelope = {
  data?: Array<{ id: number | string; slug?: string | null; name?: string | null }>
  message?: string
}

type ServiceOrder = {
  id: number
  uuid?: string | null
  order_number?: string | null
  package_id?: number | string | null
  package_name?: string | null
  status?: string | null
  payment_status?: string | null
  fulfillment_status?: string | null
  total_amount?: number | string | null
  currency?: string | null
  ordered_at?: string | null
  paid_at?: string | null
  fulfilled_at?: string | null
  cancelled_at?: string | null
  created_at?: string | null
  updated_at?: string | null
  provider_invoice_id?: string | null
  provider_payment_id?: string | null
  provider_subscription_id?: string | null
}

type OrderFilter = 'all' | 'paid' | 'pending' | 'active' | 'cancelled'

const filters: Array<{ key: OrderFilter; label: string }> = [
  { key: 'all', label: 'All orders' },
  { key: 'paid', label: 'Paid' },
  { key: 'active', label: 'Active' },
  { key: 'pending', label: 'Pending' },
  { key: 'cancelled', label: 'Cancelled' },
]

export function OrdersPage() {
  const [filter, setFilter] = useState<OrderFilter>('all')
  const [search, setSearch] = useState('')
  const ordersQuery = useQuery({
    queryKey: ['account-orders'],
    queryFn: loadOrders,
    enabled: window.isAuthenticated !== false,
    staleTime: 30_000,
  })
  const tvPackagesQuery = useQuery({
    queryKey: ['account-orders', 'tv-packges'],
    queryFn: loadTvPackageIds,
    enabled: window.isAuthenticated !== false,
    staleTime: 5 * 60_000,
  })

  const allOrders = ordersQuery.data?.data?.service_orders ?? []
  const tvPackageIds = tvPackagesQuery.data ?? new Set<number>()
  const orders = useMemo(() => allOrders.filter((order) => tvPackageIds.has(Number(order.package_id))), [allOrders, tvPackageIds])
  const visibleOrders = useMemo(() => filterOrders(orders, filter, search), [orders, filter, search])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <AccountHero title="Orders" description="Track TV subscriptions, channel services, and Core-powered purchases for this account." actionLabel="Pricing" actionHref="/subscription-plan" />

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1800px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/orders" />
          <div className="min-w-0">
            {window.isAuthenticated === false ? (
              <StatePanel title="Sign in required" message="Please sign in to view your orders." />
            ) : ordersQuery.isLoading || tvPackagesQuery.isLoading ? (
              <OrdersSkeleton />
            ) : ordersQuery.isError || tvPackagesQuery.isError ? (
              <StatePanel title="Orders unavailable" message={readApiError(ordersQuery.error || tvPackagesQuery.error, 'Orders could not be loaded right now.')} danger />
            ) : (
              <>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  <MetricCard label="Total orders" value={String(orders.length)} icon={PackageCheck} />
                  <MetricCard label="Paid" value={String(countByPayment(orders, 'paid'))} icon={CheckCircle2} />
                  <MetricCard label="In progress" value={String(countInProgress(orders))} icon={Truck} />
                  <MetricCard label="Total spend" value={money(sumOrders(orders), orders[0]?.currency)} icon={CreditCard} />
                </div>

                <div className="mt-7 rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                  <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex min-w-0 flex-wrap gap-2">
                      {filters.map((item) => {
                        const selected = filter === item.key
                        return (
                          <button
                            key={item.key}
                            type="button"
                            onClick={() => setFilter(item.key)}
                            className={['inline-flex h-10 items-center rounded-xl px-3 text-xs font-black uppercase tracking-[0.12em] transition', selected ? 'bg-[#d4a843] text-black' : 'bg-white/[0.055] text-white/56 hover:bg-white/[0.09] hover:text-white'].join(' ')}
                          >
                            {item.label}
                          </button>
                        )
                      })}
                    </div>
                    <label className="flex h-11 min-w-0 items-center gap-2 rounded-xl border border-white/10 bg-black/30 px-3 text-white/60 lg:w-80">
                      <Search className="h-4 w-4 shrink-0" />
                      <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search orders"
                        className="min-w-0 flex-1 bg-transparent text-sm font-semibold text-white outline-none placeholder:text-white/34"
                      />
                    </label>
                  </div>
                </div>

                <div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.035]">
                  {visibleOrders.length > 0 ? (
                    <div className="divide-y divide-white/8">
                      {visibleOrders.map((order) => <OrderRow key={order.id} order={order} />)}
                    </div>
                  ) : (
                    <StatePanel title="No orders found" message="Your service and subscription orders will appear here after checkout." />
                  )}
                </div>
              </>
            )}
          </div>
        </div>
      </section>
    </main>
  )
}

async function loadOrders() {
  return api.get<OrdersResponse>('/core/payment-history')
}

async function loadTvPackageIds() {
  const response = await api.get<CorePackageEnvelope>('/api/core/package-categories/tv-packges/packages?status=active&limit=100')
  return new Set((response.data ?? []).map((item) => Number(item.id)).filter((id) => Number.isFinite(id) && id > 0))
}

function OrderRow({ order }: { order: ServiceOrder }) {
  const paymentTone = statusTone(order.payment_status || order.status)
  const fulfillmentTone = fulfillmentStatusTone(order.fulfillment_status)
  const title = order.package_name || 'TV service order'

  return (
    <article className="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_220px] xl:items-center">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#d4a843]/14 text-[#edc342]">
            <PackageCheck className="h-5 w-5" />
          </span>
          <div className="min-w-0">
            <h2 className="truncate text-base font-black text-white">{title}</h2>
            <p className="mt-1 text-xs font-bold uppercase tracking-[0.14em] text-white/34">{order.order_number || `Order #${order.id}`}</p>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <StatusChip tone={paymentTone} label={order.payment_status || order.status || 'pending'} />
          <StatusChip tone={fulfillmentTone} label={order.fulfillment_status || 'unfulfilled'} />
          {order.provider_subscription_id ? <span className="rounded-full bg-white/[0.055] px-3 py-1 text-xs font-bold text-white/48">Subscription</span> : null}
        </div>

        <div className="mt-4 grid gap-3 text-sm text-white/52 sm:grid-cols-3">
          <Info label="Ordered" value={formatDate(order.ordered_at || order.created_at)} />
          <Info label="Paid" value={formatDate(order.paid_at)} />
          <Info label="Updated" value={formatDate(order.updated_at)} />
        </div>
      </div>

      <div className="rounded-2xl border border-white/10 bg-black/24 p-4 xl:text-right">
        <p className="text-xs font-black uppercase tracking-[0.16em] text-white/36">Total</p>
        <p className="mt-1 text-2xl font-black text-white">{money(order.total_amount, order.currency)}</p>
        <p className="mt-3 text-xs font-semibold text-white/36">Order ID: {order.id}</p>
      </div>
    </article>
  )
}

function MetricCard({ label, value, icon: Icon }: { label: string; value: string; icon: typeof CreditCard }) {
  return (
    <article className="rounded-2xl border border-white/10 bg-white/[0.045] p-5 shadow-2xl shadow-black/20">
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-white/42">{label}</p>
        <span className="grid h-10 w-10 place-items-center rounded-full bg-[#d4a843]/16 text-[#edc342]">
          <Icon className="h-5 w-5" />
        </span>
      </div>
      <p className="mt-4 text-3xl font-black text-white">{value}</p>
    </article>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-[11px] font-black uppercase tracking-[0.16em] text-white/30">{label}</p>
      <p className="mt-1 font-semibold text-white/62">{value}</p>
    </div>
  )
}

function StatusChip({ tone, label }: { tone: { className: string; icon: ReactNode }; label: string }) {
  return (
    <span className={['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black uppercase', tone.className].join(' ')}>
      {tone.icon}
      {label.replace(/_/g, ' ')}
    </span>
  )
}

function StatePanel({ title, message, danger = false }: { title: string; message: string; danger?: boolean }) {
  return (
    <div className={['rounded-2xl border p-8 text-center', danger ? 'border-red-400/20 bg-red-500/10 text-red-100' : 'border-white/10 bg-white/[0.035] text-white/56'].join(' ')}>
      <p className="text-lg font-black text-white">{title}</p>
      <p className="mt-2 text-sm leading-6">{message}</p>
    </div>
  )
}

function OrdersSkeleton() {
  return (
    <div className="grid gap-5">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => <div key={index} className="h-32 animate-pulse rounded-2xl bg-white/[0.06]" />)}
      </div>
      <div className="h-96 animate-pulse rounded-2xl bg-white/[0.06]" />
    </div>
  )
}

function filterOrders(orders: ServiceOrder[], filter: OrderFilter, search: string) {
  const term = search.trim().toLowerCase()
  return orders.filter((order) => {
    const payment = String(order.payment_status ?? '').toLowerCase()
    const status = String(order.status ?? '').toLowerCase()
    const fulfillment = String(order.fulfillment_status ?? '').toLowerCase()
    const matchesFilter =
      filter === 'all' ||
      (filter === 'paid' && payment === 'paid') ||
      (filter === 'active' && ['active', 'completed', 'paid'].includes(status)) ||
      (filter === 'pending' && [payment, status, fulfillment].some((value) => ['pending', 'unfulfilled', 'processing'].includes(value))) ||
      (filter === 'cancelled' && [payment, status].some((value) => ['cancelled', 'canceled', 'failed', 'payment_failed'].includes(value)))

    if (!matchesFilter) return false
    if (term === '') return true

    return [order.order_number, order.package_name, order.status, order.payment_status, order.fulfillment_status]
      .some((value) => String(value ?? '').toLowerCase().includes(term))
  })
}

function countByPayment(rows: ServiceOrder[], status: string) {
  return rows.filter((row) => String(row.payment_status ?? '').toLowerCase() === status).length
}

function countInProgress(rows: ServiceOrder[]) {
  return rows.filter((row) => !['fulfilled', 'completed', 'cancelled', 'canceled'].includes(String(row.fulfillment_status ?? row.status ?? '').toLowerCase())).length
}

function sumOrders(rows: ServiceOrder[]) {
  return rows.reduce((total, row) => total + Number(row.total_amount ?? 0), 0)
}

function money(value: number | string | null | undefined, currency = 'USD') {
  return `${currency || 'USD'} ${Number(value ?? 0).toFixed(2)}`
}

function formatDate(value?: string | null) {
  if (!value) return 'Not available'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date)
}

function statusTone(status?: string | null) {
  const value = String(status ?? '').toLowerCase()
  if (['active', 'paid', 'succeeded', 'completed'].includes(value)) {
    return { className: 'bg-emerald-400/14 text-emerald-200', icon: <CheckCircle2 className="h-3.5 w-3.5" /> }
  }
  if (['failed', 'cancelled', 'canceled', 'inactive', 'voided', 'refunded', 'payment_failed'].includes(value)) {
    return { className: 'bg-red-400/14 text-red-200', icon: <XCircle className="h-3.5 w-3.5" /> }
  }
  return { className: 'bg-[#d4a843]/14 text-[#edc342]', icon: <Clock3 className="h-3.5 w-3.5" /> }
}

function fulfillmentStatusTone(status?: string | null) {
  const value = String(status ?? '').toLowerCase()
  if (['fulfilled', 'completed'].includes(value)) {
    return { className: 'bg-emerald-400/14 text-emerald-200', icon: <Truck className="h-3.5 w-3.5" /> }
  }
  if (['cancelled', 'canceled'].includes(value)) {
    return { className: 'bg-red-400/14 text-red-200', icon: <XCircle className="h-3.5 w-3.5" /> }
  }
  return { className: 'bg-white/[0.07] text-white/58', icon: <Clock3 className="h-3.5 w-3.5" /> }
}

function readApiError(error: unknown, fallback: string) {
  if (error instanceof ApiError && error.payload && typeof error.payload === 'object') {
    const payload = error.payload as { message?: string }
    return payload.message || fallback
  }
  return error instanceof Error ? error.message : fallback
}
