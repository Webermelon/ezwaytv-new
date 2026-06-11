import type { ReactNode } from 'react'
import {
  Award,
  BadgeCheck,
  BarChart3,
  Building2,
  CheckCircle2,
  CloudUpload,
  Eye,
  Film,
  Flame,
  Headphones,
  Mic2,
  Music2,
  Radio,
  Rocket,
  ShieldCheck,
  Sparkles,
  Star,
  Tv,
  UsersRound,
} from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'

const streamUrl = 'https://stream.ezway.tv/hls/2db69da329834ed18f1bc376a2c3a27c.m3u8'
const uploadUrl = '/upload-your-videoes/ezway-music'
const paymentUrl = 'https://ezwaynetwork.com/ezway-tv-music-submission-purchase/'

const genres = [
  { name: 'Hip Hop', icon: Mic2, copy: 'Trap, drill, underground and old-school. We amplify every voice.', views: '480K views', videos: '640 videos' },
  { name: 'Pop', icon: Music2, copy: 'Chart-ready anthems, dance hits and feel-good music with mass appeal.', views: '610K views', videos: '820 videos' },
  { name: 'R&B', icon: Headphones, copy: 'Silky soul and contemporary rhythm that connects deeply with fans.', views: '390K views', videos: '510 videos' },
  { name: 'Rock', icon: Flame, copy: 'Classic rock, indie, metal and alternative for a worldwide audience.', views: '520K views', videos: '700 videos' },
  { name: 'Gospel', icon: Sparkles, copy: 'Inspiring worship, praise and contemporary gospel for every believer.', views: '400K views', videos: '530 videos' },
]

const stats = [
  { label: 'Total Streams', value: '2.4M+', sub: 'Across all platforms', icon: Eye },
  { label: 'Active Members', value: '18K+', sub: 'Performers and fans', icon: UsersRound },
  { label: 'Music Videos', value: '3.2K+', sub: 'Live on the channel', icon: Film },
  { label: 'Artists Promoted', value: '850+', sub: 'And growing daily', icon: BadgeCheck },
]

const platforms = [
  { name: 'Roku', pct: 72 },
  { name: 'Apple TV', pct: 58 },
  { name: 'Amazon Fire', pct: 45 },
  { name: 'eZWay TV Live', pct: 33 },
]

const testimonials = [
  ['Jaylen Marcus', 'Hip Hop Artist', "eZWay Music gave my video the exposure I could not get anywhere else. Within 2 weeks I had over 50,000 new streams."],
  ['Sister Evelyn', 'Gospel Artist', 'I submitted my gospel single and the response from the community was incredible. This platform truly cares about performers.'],
  ['The Broken Strings', 'Rock Band', 'As an indie rock band, it is hard to get on TV platforms. eZWay Music got us on Roku and Apple TV in days.'],
]

export function MusicPage() {
  return (
    <main className="min-h-screen bg-[#060606] text-white">
      <AppHeader />
      <MusicHero />
      <GenresSection />
      <ChannelSection />
      <AnalyticsSection />
      <HowItWorksSection />
      <CommitteeSection />
      <PricingSection />
      <TestimonialsSection />
      <FinalCta />
    </main>
  )
}

function MusicHero() {
  return (
    <section className="relative overflow-hidden bg-[#070707]">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_15%,rgba(212,168,67,0.22),transparent_30%),radial-gradient(circle_at_82%_20%,rgba(31,111,235,0.18),transparent_32%),linear-gradient(135deg,#050505_0%,#101010_54%,#050505_100%)]" />
      <div className="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-[#060606] to-transparent" />

      <div className="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.02fr_0.98fr] lg:items-center lg:px-12">
        <div className="min-w-0">
          <div className="inline-flex max-w-full items-center gap-2 rounded-sm border border-[#d4a843]/28 bg-[#d4a843]/10 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-[#f1c95c]">
            <Radio className="h-4 w-4 shrink-0" />
            <span className="min-w-0 truncate">PerformersResource.com Featured Channel</span>
          </div>
          <h1 className="mt-6 max-w-4xl break-words text-5xl font-black leading-[0.96] tracking-normal text-white sm:text-7xl lg:text-8xl">
            Stream Your Music
            <span className="block text-[#d4a843]">The eZWay</span>
          </h1>
          <p className="mt-6 max-w-2xl text-base leading-8 text-white/72 sm:text-lg">
            Get your music video in rotation on the EZWAY Music Channel and promote it across Roku, Apple TV, Amazon Fire TV, eZWay TV Live and more.
          </p>
          <div className="mt-6 flex flex-wrap gap-2">
            {['Hip Hop', 'Pop', 'R&B', 'Rock', 'Gospel'].map((genre) => (
              <span key={genre} className="rounded-sm border border-white/10 bg-white/[0.07] px-3 py-1.5 text-xs font-bold text-white/76">
                {genre}
              </span>
            ))}
          </div>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button asChild size="lg" className="bg-[#d4a843] text-black hover:bg-[#eac45b]">
              <a href={paymentUrl} target="_blank" rel="noreferrer">
                <CloudUpload className="h-5 w-5" />
                Make Payment
              </a>
            </Button>
            <Button asChild size="lg" variant="outline" className="border-white/14 bg-transparent text-white hover:bg-white/10 hover:text-white">
              <a href={uploadUrl}>
                Upload After Payment
              </a>
            </Button>
          </div>
          <div className="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3 text-sm font-bold text-white/60">
            <span className="text-[#d4a843]">Watch On:</span>
            {['Roku', 'Apple TV', 'Amazon Fire TV', 'eZWay TV Live'].map((item) => (
              <span key={item} className="inline-flex items-center gap-2">
                <Tv className="h-4 w-4" />
                {item}
              </span>
            ))}
          </div>
        </div>

        <div className="relative min-h-[360px]">
          <div className="absolute left-4 top-8 h-28 w-28 rounded-full border border-[#d4a843]/22" />
          <div className="absolute bottom-10 right-0 h-36 w-36 rounded-full border border-[#1f6feb]/22" />
          <div className="relative mx-auto max-w-[560px] overflow-hidden rounded-md border border-white/12 bg-[#141414] shadow-2xl shadow-black/60">
            <img src="/music-landing/assets/images/hero.jpeg" alt="eZWay Music artists" className="aspect-[4/3] w-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/78 via-black/10 to-transparent" />
            <div className="absolute left-4 top-4 inline-flex items-center gap-2 rounded-sm bg-red-600 px-3 py-1.5 text-xs font-black">
              <span className="h-2 w-2 rounded-full bg-white" />
              LIVE NOW
            </div>
            <div className="absolute bottom-4 left-4 right-4 flex flex-wrap items-center justify-between gap-3">
              <div className="inline-flex items-center gap-2 rounded-sm bg-black/72 px-3 py-2 text-sm font-black backdrop-blur">
                <Music2 className="h-4 w-4 text-[#d4a843]" />
                eZWay Music Channel
              </div>
              <div className="rounded-sm bg-white/12 px-3 py-2 text-sm font-bold backdrop-blur">2.4M+ Streams</div>
            </div>
          </div>
        </div>
      </div>

      <div className="relative overflow-hidden border-y border-[#d4a843]/20 bg-[#d4a843] px-4 py-3 text-center text-xs font-black uppercase tracking-[0.08em] text-black">
        Hip Hop - Pop - R&B - Rock - Gospel - Music Videos - Concerts - Live Performances
      </div>
    </section>
  )
}

function AnalyticsSection() {
  return (
    <Section id="analytics" label="Platform Analytics" icon={BarChart3} title="Real Results for Real Artists" intro="Live stats from the eZWay Music platform, built for artists who want more than a simple upload link.">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => <StatCard key={stat.label} stat={stat} />)}
      </div>
      <div className="mt-10 rounded-md border border-white/10 bg-white/[0.045] p-5 sm:p-7">
        <h3 className="text-center text-xl font-black">Viewer Breakdown by Platform</h3>
        <div className="mt-6 grid gap-4">
          {platforms.map((platform) => (
            <div key={platform.name} className="grid items-center gap-3 text-sm font-bold text-white/74 sm:grid-cols-[150px_1fr_48px]">
              <span>{platform.name}</span>
              <div className="h-3 rounded-sm bg-white/10">
                <div className="h-full rounded-sm bg-[#d4a843]" style={{ width: `${platform.pct}%` }} />
              </div>
              <span className="text-[#f1c95c]">{platform.pct}%</span>
            </div>
          ))}
        </div>
      </div>
    </Section>
  )
}

function GenresSection() {
  return (
    <Section id="genres" label="Browse by Genre" icon={Music2} title="Every Genre, One Platform" intro="Submit your video to the genre that fits your sound.">
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        {genres.map((genre) => {
          const Icon = genre.icon
          return (
            <a key={genre.name} href={paymentUrl} target="_blank" rel="noreferrer" className="group rounded-md border border-white/10 bg-[#141414] p-5 transition hover:-translate-y-1 hover:border-[#d4a843]/60 hover:bg-[#191919]">
              <div className="flex h-12 w-12 items-center justify-center rounded-md bg-[#d4a843]/14 text-[#f1c95c]">
                <Icon className="h-6 w-6" />
              </div>
              <h3 className="mt-5 text-xl font-black">{genre.name}</h3>
              <p className="mt-3 text-sm leading-6 text-white/62">{genre.copy}</p>
              <div className="mt-5 flex flex-wrap gap-2 text-[11px] font-bold text-white/54">
                <span>{genre.views}</span>
                <span>{genre.videos}</span>
              </div>
              <span className="mt-5 inline-flex text-sm font-black text-[#d4a843]">Make Payment</span>
            </a>
          )
        })}
      </div>
    </Section>
  )
}

function ChannelSection() {
  return (
    <section id="channel" className="bg-[#050609] px-4 py-16 sm:px-8 sm:py-24 lg:px-12">
      <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
        <div className="min-w-0">
          <div className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-[#f1c95c]">
            <Star className="h-3.5 w-3.5 fill-current" />
            Featured Channel
          </div>
          <h2 className="mt-7 max-w-xl text-5xl font-black leading-[1.04] tracking-normal text-white sm:text-7xl">
            The Home of
            <span className="mt-3 block text-[#f7df78]">eZWay Music</span>
          </h2>
          <p className="mt-7 max-w-2xl text-lg leading-8 text-[#9aa1b6]">
            eZWay Music is the flagship channel on PerformersResource.com, a curated streaming experience featuring music videos, concerts, and live performances 24/7 across all major platforms.
          </p>
          <ul className="mt-8 grid gap-4 text-sm font-semibold text-[#a8aec1]">
            {['24/7 curated music video streams', 'Live concert broadcasts and replays', 'Artist spotlight and exclusive interviews', 'Available on Roku, Apple TV, Fire TV and more', 'Millions of viewers across all genres'].map((item) => (
              <li key={item} className="flex items-center gap-3">
                <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#f1c95c] text-black">
                  <CheckCircle2 className="h-3.5 w-3.5" />
                </span>
                <span>{item}</span>
              </li>
            ))}
          </ul>
          <Button asChild className="mt-8 h-12 rounded-full bg-[#d4a843] px-6 text-black shadow-xl shadow-[#d4a843]/20 hover:bg-[#eac45b]">
            <a href={paymentUrl} target="_blank" rel="noreferrer">
              <Radio className="h-4 w-4" />
              Get Featured on eZWay Music
            </a>
          </Button>
        </div>

        <div className="min-w-0">
          <div className="overflow-hidden rounded-2xl border border-[#d4a843]/28 bg-black shadow-[0_0_70px_rgba(212,168,67,0.12)]">
            <div className="aspect-video w-full bg-black">
              <VideoJsPlayer source={streamUrl} poster="/music-landing/assets/images/hero.jpeg" muted vastAds={[]} />
            </div>
          </div>
          <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {['Roku', 'Apple TV', 'Fire TV', 'eZWay TV Live'].map((item) => (
              <span key={item} className="flex h-12 items-center justify-center gap-2 rounded-md border border-[#6b5bea]/24 bg-[#211d44] px-3 text-xs font-black text-[#aaa7c8]">
                <Tv className="h-4 w-4" />
                {item}
              </span>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

function HowItWorksSection() {
  const steps = [
    ['01', 'Create Your Account', 'Sign up on PerformersResource.com, build your performer profile and connect your social channels.'],
    ['02', 'Submit Your Video', 'Upload your video, choose your genre and submit your purchase reference for the monthly rotation.'],
    ['03', 'Get Promoted', 'Your video is reviewed and scheduled for eZWay Music channel rotation across streaming platforms.'],
  ]

  return (
    <Section id="how-it-works" label="Get Started" icon={Rocket} title="How It Works" intro="Three simple steps to reach more fans.">
      <div className="grid gap-4 md:grid-cols-3">
        {steps.map(([number, title, copy]) => (
          <article key={number} className="rounded-md border border-white/10 bg-[#141414] p-6">
            <div className="text-sm font-black text-[#d4a843]">{number}</div>
            <h3 className="mt-4 text-xl font-black">{title}</h3>
            <p className="mt-3 text-sm leading-6 text-white/62">{copy}</p>
          </article>
        ))}
      </div>
    </Section>
  )
}

function CommitteeSection() {
  const members = [
    ['J Beatzz', 'Multi-Platinum Grammy music producer bringing major-label production insight to the channel.'],
    ['MacNeal "Big Papa" Bruny', "Won-G's father and respected entertainment figure supporting artist relationships."],
    ['Septimius The Great', 'Multi-Grammy artist, actor and host bringing performance experience and visibility.'],
  ]

  return (
    <Section id="committee" label="Behind the Channel" icon={Award} title="Powered by Music Industry Leaders" intro="The EZWAY Music Channel is backed by producers, artists, hosts and entertainment leaders helping shape the rotation.">
      <div className="grid gap-4 md:grid-cols-3">
        {members.map(([name, copy]) => (
          <article key={name} className="rounded-md border border-white/10 bg-[#141414] p-6">
            <Award className="h-8 w-8 text-[#d4a843]" />
            <h3 className="mt-4 text-xl font-black">{name}</h3>
            <p className="mt-3 text-sm leading-6 text-white/62">{copy}</p>
          </article>
        ))}
      </div>
    </Section>
  )
}

function PricingSection() {
  return (
    <Section id="pricing" label="Simple Pricing" icon={ShieldCheck} title="Promote Your Music Today" intro="Get your music video in rotation on the EZWAY Music Channel for one simple monthly price.">
      <div className="grid gap-4 lg:grid-cols-3">
        <PriceCard title="Music Video Rotation" badge="Monthly Rotation" icon={Radio} featured={false} cta="Upload Video" />
        <PriceCard title="EZWAY Music Channel" badge="Featured Offer" icon={Star} featured cta="Add My Video - $24.99/mo" />
        <PriceCard title="Channel Partner" badge="For Labels" icon={Building2} featured={false} custom cta="Contact Us" />
      </div>
      <p className="mt-5 text-center text-sm font-semibold text-white/56">Secure payments - Cancel anytime - 100% satisfaction guaranteed</p>
    </Section>
  )
}

function PriceCard({ title, badge, icon: Icon, featured, custom, cta }: { title: string; badge: string; icon: typeof Star; featured: boolean; custom?: boolean; cta: string }) {
  const features = custom
    ? ['Bulk video submission', 'Dedicated account manager', 'Co-branded channel placement', 'Advanced analytics and reports', 'Custom pricing available']
    : ['Music video added to rotation', 'Genre-based placement', 'EZWAY Music Channel exposure', 'Roku, Apple TV, Fire TV and more', 'Monthly active rotation']

  return (
    <article className={['rounded-md border p-6', featured ? 'border-[#d4a843]/70 bg-[#d4a843]/10 shadow-2xl shadow-[#d4a843]/10' : 'border-white/10 bg-[#141414]'].join(' ')}>
      <span className="rounded-sm bg-[#d4a843] px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-black">{badge}</span>
      <Icon className="mt-6 h-9 w-9 text-[#d4a843]" />
      <h3 className="mt-5 text-2xl font-black">{title}</h3>
      <div className="mt-5">
        {custom ? <span className="text-4xl font-black text-white">Custom</span> : <><span className="text-5xl font-black text-white">$24</span><span className="text-2xl font-black text-[#d4a843]">.99</span><span className="ml-2 text-sm font-bold text-white/54">/ month</span></>}
      </div>
      <ul className="mt-6 grid gap-3 text-sm text-white/68">
        {features.map((feature) => (
          <li key={feature} className="flex gap-2">
            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
            <span>{feature}</span>
          </li>
        ))}
      </ul>
      <Button asChild className={['mt-7 w-full', featured ? 'bg-[#d4a843] text-black hover:bg-[#eac45b]' : 'bg-white/10 text-white hover:bg-white/16'].join(' ')}>
        <a href={custom ? 'mailto:support@ezway.tv?subject=eZWay%20Music%20Channel%20Partner' : paymentUrl} target={custom ? undefined : '_blank'} rel={custom ? undefined : 'noreferrer'}>{cta}</a>
      </Button>
    </article>
  )
}

function TestimonialsSection() {
  return (
    <Section id="testimonials" label="Performer Stories" icon={Star} title="What Artists Are Saying">
      <div className="grid gap-4 md:grid-cols-3">
        {testimonials.map(([name, role, quote]) => (
          <article key={name} className="rounded-md border border-white/10 bg-[#141414] p-6">
            <div className="flex gap-1 text-[#d4a843]">{Array.from({ length: 5 }).map((_, index) => <Star key={index} className="h-4 w-4 fill-current" />)}</div>
            <p className="mt-5 text-sm leading-7 text-white/70">"{quote}"</p>
            <div className="mt-6 text-sm">
              <div className="font-black text-white">{name}</div>
              <div className="mt-1 text-white/50">{role}</div>
            </div>
          </article>
        ))}
      </div>
    </Section>
  )
}

function FinalCta() {
  return (
    <section className="px-4 pb-16 sm:px-8 lg:px-12">
      <div className="mx-auto max-w-5xl rounded-md border border-[#d4a843]/24 bg-[#d4a843]/10 p-7 text-center sm:p-10">
        <Rocket className="mx-auto h-9 w-9 text-[#d4a843]" />
        <h2 className="mt-4 text-3xl font-black sm:text-5xl">Ready to Stream The eZWay?</h2>
        <p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-white/68">Join performers already reaching fans on Roku, Apple TV, Amazon Fire TV and eZWay TV Live.</p>
        <Button asChild size="lg" className="mt-7 bg-[#d4a843] text-black hover:bg-[#eac45b]">
          <a href={paymentUrl} target="_blank" rel="noreferrer">Add My Video - $24.99/mo</a>
        </Button>
      </div>
    </section>
  )
}

function StatCard({ stat }: { stat: (typeof stats)[number] }) {
  const Icon = stat.icon
  return (
    <article className="rounded-md border border-white/10 bg-[#141414] p-5">
      <Icon className="h-7 w-7 text-[#d4a843]" />
      <div className="mt-4 text-3xl font-black text-white">{stat.value}</div>
      <div className="mt-1 text-sm font-black text-white/78">{stat.label}</div>
      <div className="mt-1 text-xs text-white/46">{stat.sub}</div>
    </article>
  )
}

function Section({ id, label, icon: Icon, title, intro, children }: { id: string; label: string; icon: typeof Music2; title: string; intro?: string; children: ReactNode }) {
  return (
    <section id={id} className="px-4 py-14 sm:px-8 sm:py-18 lg:px-12">
      <div className="mx-auto max-w-7xl">
        <div className="mb-8 text-center">
          <div className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.12em] text-[#d4a843]">
            <Icon className="h-4 w-4" />
            {label}
          </div>
          <h2 className="mx-auto mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl">{title}</h2>
          {intro ? <p className="mx-auto mt-4 max-w-3xl text-sm leading-7 text-white/62 sm:text-base">{intro}</p> : null}
        </div>
        {children}
      </div>
    </section>
  )
}
