import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Clock3, CreditCard, FileText, Loader2, RefreshCw, Repeat2, XCircle } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { AccountHero, AccountSidebar } from '@/modules/account/ProfileDetailsPage'
import { ApiError, api } from '@/lib/api'

type PaymentHistoryResponse = {
  data?: {
    summary?: Record<string, unknown>
    invoices?: PaymentInvoice[]
    subscriptions?: PaymentSubscription[]
  }
  message?: string
}

type PaymentInvoice = {
  id: number
  invoice_number?: string | null
  status?: string | null
  payment_status?: string | null
  currency?: string | null
  total_amount?: number | string | null
  paid_amount?: number | string | null
  balance_amount?: number | string | null
  invoice_date?: string | null
  due_date?: string | null
  paid_at?: string | null
  created_at?: string | null
}

type PaymentSubscription = {
  id: number
  subscription_uuid?: string | null
  subscribable_type?: string | null
  subscribable_id?: number | string | null
  status?: string | null
  billing_period?: string | null
  billing_period_count?: number | string | null
  amount?: number | string | null
  currency?: string | null
  provider?: string | null
  current_period_starts_at?: string | null
  current_period_ends_at?: string | null
  next_billing_at?: string | null
  started_at?: string | null
  cancelled_at?: string | null
  ended_at?: string | null
  created_at?: string | null
}

type TabKey = 'subscriptions' | 'invoices'

const tabs: Array<{ key: TabKey; label: string; icon: typeof CreditCard }> = [
  { key: 'subscriptions', label: 'Subscriptions', icon: Repeat2 },
  { key: 'invoices', label: 'Invoices', icon: FileText },
]

export function PaymentHistoryPage() {
  const [activeTab, setActiveTab] = useState<TabKey>('subscriptions')
  const historyQuery = useQuery({
    queryKey: ['payment-history'],
    queryFn: loadPaymentHistory,
    enabled: window.isAuthenticated !== false,
    staleTime: 30_000,
  })

  const data = historyQuery.data?.data ?? {}
  const subscriptions = data.subscriptions ?? []
  const invoices = data.invoices ?? []
  const visibleInvoices = invoices.length > 0 ? invoices : subscriptions.map(subscriptionInvoice)
  const activeRows = useMemo(() => {
    if (activeTab === 'subscriptions') return subscriptions
    return visibleInvoices
  }, [activeTab, subscriptions, visibleInvoices])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <AccountHero title="Payment History" description="Review TV subscriptions and invoices connected to your eZWay account." actionLabel="Account Settings" actionHref="/account-setting" />

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1800px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/payment-history" />
          <div className="min-w-0">
          {window.isAuthenticated === false ? (
            <StatePanel title="Sign in required" message="Please sign in to view your payment history." />
          ) : historyQuery.isLoading ? (
            <PaymentHistorySkeleton />
          ) : historyQuery.isError ? (
            <StatePanel title="Payment history unavailable" message={readApiError(historyQuery.error, 'Payment history could not be loaded right now.')} danger />
          ) : (
            <>
              <div className="grid gap-3 sm:grid-cols-2">
                <MetricCard label="Active subscriptions" value={String(countActive(subscriptions))} icon={Repeat2} />
                <MetricCard label="Open invoices" value={String(countOpenInvoices(visibleInvoices))} icon={FileText} />
              </div>

              <div className="mt-7 overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.035] p-2">
                <div className="flex min-w-max gap-2">
                  {tabs.map((tab) => {
                    const Icon = tab.icon
                    const selected = activeTab === tab.key
                    return (
                      <button
                        key={tab.key}
                        type="button"
                        onClick={() => setActiveTab(tab.key)}
                        className={['inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-black transition', selected ? 'bg-[#d4a843] text-black' : 'text-white/62 hover:bg-white/[0.07] hover:text-white'].join(' ')}
                      >
                        <Icon className="h-4 w-4" />
                        {tab.label}
                        <span className={selected ? 'text-black/58' : 'text-white/38'}>{countForTab(tab.key, subscriptions, visibleInvoices)}</span>
                      </button>
                    )
                  })}
                </div>
              </div>

              <div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.035]">
                {activeRows.length > 0 ? (
                  <div className="divide-y divide-white/8">
                    {activeTab === 'subscriptions' ? subscriptions.map((item) => <SubscriptionRow key={item.id} item={item} />) : null}
                    {activeTab === 'invoices' ? visibleInvoices.map((item) => <InvoiceRow key={item.id} item={item} />) : null}
                  </div>
                ) : (
                  <StatePanel title={`No ${tabs.find((tab) => tab.key === activeTab)?.label.toLowerCase()} yet`} message="Once payment activity is available, it will show here." />
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

async function loadPaymentHistory() {
  return api.get<PaymentHistoryResponse>('/core/payment-history')
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

function SubscriptionRow({ item }: { item: PaymentSubscription }) {
  return (
    <HistoryRow
      icon={Repeat2}
      title="TV Subscription"
      subtitle={`${item.billing_period_count ?? 1} ${item.billing_period ?? 'month'} billing`}
      amount={money(item.amount, item.currency)}
      status={item.status}
      dateLabel="Current period"
      dateValue={dateRange(item.current_period_starts_at, item.current_period_ends_at)}
    />
  )
}

function InvoiceRow({ item }: { item: PaymentInvoice }) {
  return (
    <HistoryRow
      icon={FileText}
      title={item.invoice_number || `Invoice #${item.id}`}
      subtitle={`Paid ${money(item.paid_amount, item.currency)} · Balance ${money(item.balance_amount, item.currency)}`}
      amount={money(item.total_amount, item.currency)}
      status={item.payment_status || item.status}
      dateLabel="Invoice date"
      dateValue={formatDate(item.invoice_date || item.created_at)}
    />
  )
}

function HistoryRow({ icon: Icon, title, subtitle, amount, status, dateLabel, dateValue }: { icon: typeof CreditCard; title: string; subtitle: string; amount: string; status?: string | null; dateLabel: string; dateValue: string }) {
  const tone = statusTone(status)
  return (
    <article className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 gap-4">
        <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/[0.07] text-[#edc342]">
          <Icon className="h-5 w-5" />
        </span>
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="truncate text-base font-black text-white">{title}</h2>
            <span className={['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-black uppercase', tone.className].join(' ')}>
              {tone.icon}
              {status || 'pending'}
            </span>
          </div>
          <p className="mt-1 text-sm text-white/48">{subtitle}</p>
        </div>
      </div>

      <div className="grid gap-1 text-left sm:text-right">
        <p className="text-lg font-black text-white">{amount}</p>
        <p className="text-xs font-bold uppercase tracking-[0.14em] text-white/34">{dateLabel}</p>
        <p className="text-sm font-semibold text-white/58">{dateValue}</p>
      </div>
    </article>
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

function PaymentHistorySkeleton() {
  return (
    <div className="grid gap-5">
      <div className="grid gap-3 sm:grid-cols-2">
        {Array.from({ length: 2 }).map((_, index) => <div key={index} className="h-32 animate-pulse rounded-2xl bg-white/[0.06]" />)}
      </div>
      <div className="h-80 animate-pulse rounded-2xl bg-white/[0.06]" />
    </div>
  )
}

function subscriptionInvoice(subscription: PaymentSubscription): PaymentInvoice {
  const amount = subscription.amount ?? 0
  const status = String(subscription.status ?? '').toLowerCase()
  const paid = ['active', 'trialing', 'canceled', 'cancelled', 'ended', 'inactive'].includes(status)

  return {
    id: subscription.id,
    invoice_number: subscription.subscription_uuid || 'Subscription #' + subscription.id,
    status: subscription.status,
    payment_status: paid ? 'paid' : subscription.status,
    currency: subscription.currency,
    total_amount: amount,
    paid_amount: paid ? amount : 0,
    balance_amount: paid ? 0 : amount,
    invoice_date: subscription.started_at || subscription.created_at,
    paid_at: subscription.started_at || subscription.created_at,
    created_at: subscription.created_at,
  }
}

function countForTab(tab: TabKey, subscriptions: unknown[], invoices: unknown[]) {
  return tab === 'subscriptions' ? subscriptions.length : invoices.length
}

function countActive(rows: PaymentSubscription[]) {
  return rows.filter((row) => ['active', 'trialing'].includes(String(row.status ?? '').toLowerCase())).length
}
function countOpenInvoices(rows: PaymentInvoice[]) {
  return rows.filter((row) => !['paid', 'voided', 'cancelled', 'canceled'].includes(String(row.payment_status ?? row.status ?? '').toLowerCase())).length
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

function dateRange(start?: string | null, end?: string | null) {
  if (!start && !end) return 'Not available'
  return `${formatDate(start)} - ${formatDate(end)}`
}

function statusTone(status?: string | null) {
  const value = String(status ?? '').toLowerCase()
  if (['active', 'paid', 'succeeded', 'completed'].includes(value)) {
    return { className: 'bg-emerald-400/14 text-emerald-200', icon: <CheckCircle2 className="h-3.5 w-3.5" /> }
  }
  if (['failed', 'cancelled', 'canceled', 'inactive', 'voided', 'refunded'].includes(value)) {
    return { className: 'bg-red-400/14 text-red-200', icon: <XCircle className="h-3.5 w-3.5" /> }
  }
  return { className: 'bg-[#d4a843]/14 text-[#edc342]', icon: <Clock3 className="h-3.5 w-3.5" /> }
}

function readApiError(error: unknown, fallback: string) {
  if (error instanceof ApiError && error.payload && typeof error.payload === 'object') {
    const payload = error.payload as { message?: string }
    return payload.message || fallback
  }
  return error instanceof Error ? error.message : fallback
}
