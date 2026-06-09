import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { RadioTower } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { loadDistribution, type DistributionNetwork } from './distributionApi'

const signalUrl = 'https://www.rabbitears.info/contour.php?appid=25076f917915d2290179276a691b1937&site=1&dma=N&map=N&contour=Y&lppc=N&int=N&pop=Y&incpop=k21ac-d&excpop=&z1=N&nrqz=N&lprw=N&head=Y&asrn=&extras=&cir=&circen='

export function DistributionPage() {
  const distributionQuery = useQuery({
    queryKey: ['distribution'],
    queryFn: loadDistribution,
    staleTime: 5 * 60_000,
  })
  const networks = distributionQuery.data?.networks ?? []

  const platformNames = useMemo(() => networks.slice(0, 13).map((item) => item.name), [networks])

  return (
    <main className="min-h-screen bg-[#0d0d0d] text-[#e8e2d9]">
      <AppHeader active="distribution" />

      <section className="relative overflow-hidden px-4 py-20 text-center sm:px-8 lg:px-12">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(212,168,67,0.10)_0%,transparent_70%),repeating-linear-gradient(0deg,transparent,transparent_39px,rgba(212,168,67,0.04)_40px),repeating-linear-gradient(90deg,transparent,transparent_39px,rgba(212,168,67,0.04)_40px)]" />
        <div className="relative mx-auto max-w-5xl">
          <div className="mb-6 inline-flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.25em] text-[#d4a843] before:block before:h-px before:w-8 before:bg-[#d4a843]/60 after:block after:h-px after:w-8 after:bg-[#d4a843]/60">
            eZWay Network
          </div>
          <h1 className="text-6xl font-black uppercase leading-[0.92] tracking-[0.04em] text-white sm:text-7xl lg:text-8xl">
            eZWay <span className="text-[#d4a843]">TV</span>
            <br />
            Distribution
          </h1>
          <p className="mx-auto mt-7 max-w-3xl text-sm leading-7 text-[#9e9890] sm:text-base">
            eZWay.TV is an interactive streaming platform with worldwide reach, powered by a massive distribution network across AVOD, linear, mobile, smart TVs, and cable-connected devices.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <span className="rounded-sm border border-[#d4a843]/20 bg-[#d4a843]/10 px-4 py-2 text-xs font-bold tracking-normal text-[#f0c96a]">
              Potential reach: 100,000,000+
            </span>
            <span className="px-4 py-2 text-xs font-semibold tracking-normal text-[#e8e2d9]">
              Organic viewers: tens of thousands
            </span>
          </div>
        </div>
      </section>

      <div className="bg-[#d4a843] px-6 py-3 text-center text-xs font-black uppercase tracking-[0.06em] text-[#0d0d0d]">
        Potential reach: 100,000,000 - Organic viewers: tens of thousands
      </div>

      <section className="mx-auto max-w-[1240px] px-4 sm:px-8">
        <SectionHead title="Los Angeles Station" />

        <article className="mb-20 grid overflow-hidden rounded border border-[#d4a843]/20 border-l-4 border-l-[#d4a843] bg-[#161616] lg:grid-cols-2">
          <div className="p-8 sm:p-11">
            <span className="inline-block rounded-sm bg-[#d4a843] px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-[#0d0d0d]">
              OTA - Over-the-Air
            </span>
            <h2 className="mt-5 text-5xl font-black uppercase leading-none tracking-[0.04em] text-white">
              eZWay
              <br />
              Los Angeles
            </h2>
            <div className="mt-2 font-serif text-xl italic tracking-normal text-[#f0c96a]">Channel 27.3</div>
            <p className="mt-6 max-w-md text-sm leading-7 text-[#7a7468]">
              eZWay Network is now broadcasting over-the-air in the Los Angeles metro area on <strong className="text-[#e8e2d9]">Channel 27.3</strong>. The station reaches millions of households across Greater LA, delivering purpose-driven content, live events, interviews, and original series.
            </p>
            <p className="mt-4 max-w-md text-sm leading-7 text-[#7a7468]">
              View the full FCC contour map for signal coverage, propagation details, and licensing information for K21AC-D.
            </p>
            <a
              href={signalUrl}
              target="_blank"
              rel="noreferrer"
              className="mt-7 inline-flex items-center gap-2 rounded-sm border border-[#d4a843] px-5 py-3 text-xs font-bold uppercase tracking-[0.1em] text-[#f0c96a] transition hover:bg-[#d4a843] hover:text-[#0d0d0d]"
            >
              <RadioTower className="h-4 w-4" />
              View Signal Coverage Map
            </a>
          </div>
          <div className="relative min-h-[360px] bg-[#1e1e1e]">
            <iframe
              src={signalUrl}
              title="eZWay LA Station Signal Coverage - Channel 27.3 K21AC-D"
              loading="lazy"
              className="h-full min-h-[360px] w-full border-0 opacity-85 saturate-[0.6] contrast-110"
            />
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(22,22,22,0.55)_0%,transparent_30%)]" />
          </div>
        </article>
      </section>

      <section className="mx-auto max-w-[1240px] px-4 sm:px-8">
        <SectionHead title="Network Partners" />

        {distributionQuery.isLoading ? (
          <NetworkSkeleton />
        ) : networks.length > 0 ? (
          <div className="mb-20 grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))] sm:[grid-template-columns:repeat(auto-fill,minmax(340px,1fr))]">
            {networks.map((network) => (
              <NetworkCard key={network.slug ?? network.name} network={network} />
            ))}
          </div>
        ) : (
          <div className="mb-20 rounded border border-[#272727] bg-[#161616] p-8 text-[#7a7468]">No distribution data available.</div>
        )}
      </section>

      <section className="border-y border-[#d4a843]/20 bg-[#161616] px-4 py-10 text-center sm:px-8">
        <div className="mb-5 text-[11px] font-bold uppercase tracking-[0.22em] text-[#7a7468]">Available On All Major Platforms</div>
        <div className="mx-auto flex max-w-5xl flex-wrap justify-center gap-x-4 gap-y-3">
          {(platformNames.length ? platformNames : ['EZWAY.TV', 'XOTV', 'BVC TV', 'NATIONAL BIZ TV']).map((platform) => (
            <span key={platform} className="rounded-sm border border-white/10 bg-[#1e1e1e] px-5 py-2 text-xs font-semibold tracking-normal text-[#9e9890]">
              {platform}
            </span>
          ))}
        </div>
      </section>
    </main>
  )
}

function SectionHead({ title }: { title: string }) {
  return (
    <div className="flex items-baseline gap-5 pb-10 pt-18">
      <h2 className="text-4xl font-black uppercase tracking-[0.05em] text-white sm:text-5xl">{title}</h2>
      <div className="h-px flex-1 bg-[linear-gradient(to_right,#8a6b28,transparent)]" />
    </div>
  )
}

function NetworkCard({ network }: { network: DistributionNetwork }) {
  return (
    <article className="group relative flex min-h-[300px] flex-col gap-4 overflow-hidden rounded-md border border-[#d4a843]/14 bg-[#161616] p-7 shadow-[0_18px_45px_rgba(0,0,0,0.24)] transition duration-300 hover:-translate-y-1 hover:border-[#d4a843]/50 hover:bg-[#1b1b1b]">
      <div className="absolute inset-x-0 top-0 h-[3px] origin-left scale-x-0 bg-[#d4a843] transition duration-300 group-hover:scale-x-100" />
      <div className="flex items-center gap-4">
        <div className="flex h-[58px] w-[88px] shrink-0 items-center justify-center overflow-hidden rounded border border-white/8 bg-[#242424]">
          {network.image ? <img src={network.image} alt={network.name} className="max-h-10 max-w-16 object-contain" loading="lazy" /> : null}
        </div>
        <h3 className="text-2xl font-black uppercase leading-none tracking-[0.05em] text-white">{network.name}</h3>
      </div>
      {network.description ? <p className="flex-1 text-sm leading-7 text-[#bdb5a8]">{network.description}</p> : null}
      {network.tag ? (
        <span className="w-fit rounded-sm border border-[#d4a843]/24 bg-[#d4a843]/8 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#f0c96a]">
          {network.tag}
        </span>
      ) : null}
    </article>
  )
}

function NetworkSkeleton() {
  return (
    <div className="mb-20 grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))] sm:[grid-template-columns:repeat(auto-fill,minmax(340px,1fr))]">
      {Array.from({ length: 12 }).map((_, index) => (
        <div key={index} className="h-[300px] animate-pulse rounded-md border border-[#d4a843]/14 bg-[#161616] p-8" />
      ))}
    </div>
  )
}
