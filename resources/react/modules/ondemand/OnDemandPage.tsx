import { useEffect, useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Check, Clapperboard, Copy, Lock, MessageCircle, Play, Search, Share2, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { WatchlistToggleButton } from '@/components/WatchlistToggleButton'
import { trackView } from '@/lib/analytics'
import { useSpaPath } from '@/lib/spa-router'
import type { MediaItem } from '@/modules/home/types'
import { loadOnDemandChannels, loadOnDemandProfile } from './ondemandApi'

type ShareIconProps = {
  className?: string
}

export function OnDemandPage() {
  const path = useSpaPath()
  const routeUsername = getUsernameFromPath(path)
  const [query, setQuery] = useState('')
  const trackedProfileViewRef = useRef<string | number | null>(null)
  const isProfileRoute = Boolean(routeUsername)
  const channelsQuery = useQuery({
    queryKey: ['ondemand-channels'],
    queryFn: loadOnDemandChannels,
    staleTime: 5 * 60_000,
  })
  const channels = channelsQuery.data ?? []

  const profileQuery = useQuery({
    queryKey: ['ondemand-profile', routeUsername],
    queryFn: () => loadOnDemandProfile(routeUsername),
    enabled: Boolean(routeUsername),
    staleTime: 60_000,
  })
  const profileState = profileQuery.data ?? { profile: null, videos: [] }

  useEffect(() => {
    const profile = profileState.profile
    if (!profile?.id) return
    if (trackedProfileViewRef.current === profile.id) return

    trackedProfileViewRef.current = profile.id

    trackView({
      content_type: 'ondemand_channel',
      content_id: profile.id,
      page_name: profile.name,
      route_name: 'ondemand.show',
    }).catch(() => undefined)
  }, [profileState.profile?.id])

  useEffect(() => {
    const profile = profileState.profile

    if (!routeUsername || !profile) {
      updatePageMeta({
        title: 'On Demand Channels',
        description: 'Watch on demand channels on eZWay TV.',
        image: null,
        url: `${window.location.origin}/on-demand`,
      })
      return
    }

    updatePageMeta({
      title: `${profile.name} | On Demand`,
      description: profile.description
        ? stripHtml(profile.description).slice(0, 160)
        : `Watch ${profile.name} on demand on eZWay TV.`,
      image: channelAvatarThumb(profile),
      url: `${window.location.origin}/on-demand/${profile.username}`,
    })
  }, [routeUsername, profileState.profile])

  const filteredChannels = useMemo(() => {
    const term = query.trim().toLowerCase()
    if (!term) return channels

    return channels.filter((channel) =>
      [channel.name, channel.username].filter(Boolean).some((value) => String(value).toLowerCase().includes(term)),
    )
  }, [channels, query])

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="on-demand" />

      <section className="relative overflow-hidden px-4 pb-12 pt-8 sm:px-8 lg:px-12">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_82%_0%,rgba(229,9,20,0.22),transparent_30%),radial-gradient(circle_at_12%_18%,rgba(212,168,67,0.12),transparent_24%)]" />
        <div className="relative mx-auto max-w-[1800px]">
          {isProfileRoute ? (
            <ProfilePanel loading={profileQuery.isLoading} profile={profileState.profile} videos={profileState.videos} />
          ) : (
            <ArchiveView
              channels={filteredChannels}
              loading={channelsQuery.isLoading}
              query={query}
              onQueryChange={setQuery}
            />
          )}
        </div>
      </section>
    </main>
  )
}

function ArchiveView({
  channels,
  loading,
  query,
  onQueryChange,
}: {
  channels: MediaItem[]
  loading: boolean
  query: string
  onQueryChange: (value: string) => void
}) {
  return (
    <>
      <div className="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(320px,0.55fr)] lg:items-end">
        <div>
          <Badge className="rounded-sm bg-primary text-white">On Demand</Badge>
          <h1 className="mt-4 max-w-4xl text-4xl font-black leading-none text-white sm:text-5xl lg:text-6xl">
            Explore creator channels on demand.
          </h1>
          <p className="mt-4 max-w-3xl text-sm leading-6 text-white/64 sm:text-base">
            Browse eZWay creator channels, open a channel page, and play videos through the existing video detail experience.
          </p>
        </div>

        <SearchBox value={query} onChange={onQueryChange} placeholder="Search channels" />
      </div>

      <div className="mt-8">
        {loading ? (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ChannelGridSkeleton />
          </div>
        ) : channels.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {channels.map((channel) => (
              <ChannelCard key={channel.id} channel={channel} />
            ))}
          </div>
        ) : (
          <EmptyState message="No channels matched your search." />
        )}
      </div>
    </>
  )
}

function ChannelSidebar({
  channels,
  loading,
  query,
  selectedUsername,
  onQueryChange,
}: {
  channels: MediaItem[]
  loading: boolean
  query: string
  selectedUsername: string
  onQueryChange: (value: string) => void
}) {
  return (
    <aside className="hidden min-w-0 self-start rounded-md border border-white/10 bg-[#111]/86 p-4 shadow-2xl shadow-black/30 xl:block xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto">
      <Button asChild variant="outline" className="mb-4 h-10 w-full border-white/12 bg-white/[0.04] text-white hover:bg-white/[0.09]">
        <a href="/on-demand">
          <ArrowLeft className="mr-2 h-4 w-4" />
          All Channels
        </a>
      </Button>

      <div className="mb-4">
        <Badge className="rounded-sm bg-primary text-white">More Channels</Badge>
        <h2 className="mt-3 text-2xl font-black leading-tight">On Demand Channels</h2>
      </div>

      <SearchBox value={query} onChange={onQueryChange} placeholder="Search channels" compact />

      <div className="mt-4 grid gap-2">
        {loading ? (
          <ChannelListSkeleton />
        ) : channels.length > 0 ? (
          channels.map((channel) => (
            <ChannelListItem key={channel.id} channel={channel} active={selectedUsername === channel.username} />
          ))
        ) : (
          <div className="rounded-md border border-white/10 bg-white/[0.04] p-4 text-sm text-white/56">No channels matched.</div>
        )}
      </div>
    </aside>
  )
}

function ProfilePanel({ loading, profile, videos }: { loading: boolean; profile: MediaItem | null; videos: MediaItem[] }) {
  const [copiedShareUrl, setCopiedShareUrl] = useState(false)

  if (loading) {
    return <div className="min-h-[620px] animate-pulse rounded-md border border-white/10 bg-white/[0.04]" />
  }

  if (!profile) {
    return (
      <div className="flex min-h-[620px] flex-col items-center justify-center rounded-md border border-white/10 bg-white/[0.04] px-6 text-center text-white/56">
        <Tv className="mb-3 h-9 w-9 text-white/36" />
        <p className="text-lg font-bold text-white">Channel not found</p>
        <p className="mt-2 max-w-sm text-sm leading-6">This On Demand channel could not be loaded.</p>
        <Button asChild className="mt-5 bg-white text-black hover:bg-white/85">
          <a href="/on-demand">Browse all channels</a>
        </Button>
      </div>
    )
  }

  const channelLocked = isPremiumChannelLocked(profile)

  return (
    <article className="min-w-0 overflow-hidden rounded-md border border-white/10 bg-[#111]/86 shadow-2xl shadow-black/40">
      <div className="relative h-[38vw] max-h-48 min-h-36 overflow-hidden bg-black sm:h-[30vw] sm:max-h-52 lg:h-[24vw] lg:max-h-56">
        {profile.cover_image_url ? (
          <img
            src={profile.cover_image_url}
            alt=""
            className="h-full w-full object-contain sm:object-cover"
          />
        ) : null}
        <div className="absolute inset-0 bg-gradient-to-t from-[#111] via-black/25 to-black/10" />
      </div>

      <div className="relative px-4 pb-6 sm:px-6">
        <div className="-mt-8 grid gap-4 sm:-mt-10 sm:flex sm:flex-wrap sm:items-end">
          <div className="flex min-w-0 items-end gap-3 sm:flex-1 sm:gap-4">
            <MediaThumbnail
              src={profile.avatar_image_url ?? profile.cover_image_url}
              alt={profile.name}
              className="h-20 w-20 shrink-0 rounded-full border-4 border-[#d6a83a] shadow-xl ring-4 ring-[#050505] sm:h-24 sm:w-24"
            />
            <div className="min-w-0 flex-1 pb-1 sm:pb-2">
              <div className="flex min-w-0 flex-wrap items-center gap-2">
                <h2 className="line-clamp-2 text-2xl font-black leading-tight text-white sm:truncate sm:text-3xl">{profile.name}</h2>
                {profile.access === 'paid' ? (
                  <Badge className="rounded-sm bg-primary text-black">
                    <Lock className="mr-1 h-3.5 w-3.5" />
                    Premium
                  </Badge>
                ) : null}
              </div>
              <p className="mt-1 text-sm leading-5 text-white/58">@{profile.username} · {profile.videos_count ?? videos.length} videos</p>
            </div>
          </div>
          <div className="grid gap-2 sm:mb-2 sm:flex sm:w-auto">
            <OnDemandShareMenu
              profile={profile}
              copied={copiedShareUrl}
              onCopy={() => {
                copyText(channelShareUrl(profile)).then(() => {
                  setCopiedShareUrl(true)
                  window.setTimeout(() => setCopiedShareUrl(false), 1800)
                })
              }}
            />
            <Button asChild variant="outline" className="h-10 w-full border-white/12 bg-white/[0.04] text-white hover:bg-white/[0.09] sm:w-auto">
              <a href="/on-demand">
                <ArrowLeft className="mr-2 h-4 w-4" />
                View All Channels
              </a>
            </Button>
          </div>
        </div>

        {profile.description ? (
          <p className="mt-5 line-clamp-3 max-w-4xl text-sm leading-6 text-white/64">{stripHtml(profile.description)}</p>
        ) : null}

        {channelLocked ? <OnDemandPremiumPanel item={profile} /> : null}

        <div className="mt-8">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-xl font-bold">Videos</h3>
            <Badge variant="outline" className="border-white/16 text-white/70">{videos.length} videos</Badge>
          </div>

          {channelLocked ? (
            <div className="rounded-md border border-primary/30 bg-primary/10 p-8 text-center text-white/72">
              <Lock className="mx-auto mb-3 h-8 w-8 text-primary" />
              <p className="text-base font-bold text-white">Videos are available with {requiredPlanLabel(profile)}.</p>
            </div>
          ) : videos.length > 0 ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
              {videos.map((video) => (
                <VideoCard key={video.id} video={video} channelId={profile.id} />
              ))}
            </div>
          ) : (
            <div className="rounded-md border border-white/10 bg-black/24 p-8 text-center text-white/56">
              <Clapperboard className="mx-auto mb-3 h-8 w-8 text-white/36" />
              No videos yet.
            </div>
          )}
        </div>
      </div>
    </article>
  )
}

function OnDemandPremiumPanel({ item }: { item: MediaItem }) {
  return (
    <div className="mt-6 rounded-md border border-primary/30 bg-[linear-gradient(135deg,rgba(214,168,58,0.18),rgba(229,9,20,0.12))] p-5 shadow-xl shadow-black/20">
      <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
        <div className="flex min-w-0 gap-4">
          <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-black">
            <Lock className="h-6 w-6" />
          </span>
          <div className="min-w-0">
            <h3 className="text-xl font-black leading-tight text-white">Premium On Demand Channel</h3>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-white/72">
              This channel is included with {requiredPlanLabel(item)}. Sign in or upgrade your plan to watch its videos.
            </p>
          </div>
        </div>
        <div className="grid gap-2 sm:flex md:justify-end">
          <PremiumActionButton item={item} />
          <Button asChild variant="outline" className="border-white/14 bg-white/[0.04] text-white hover:bg-white/[0.09]">
            <a href="/login">Sign In</a>
          </Button>
        </div>
      </div>
    </div>
  )
}

function PremiumActionButton({ item }: { item: MediaItem }) {
  const isSignedIn = Number(item.current_plan_level ?? 0) > 0

  return (
    <Button asChild className="bg-primary text-black hover:bg-primary/90">
      <a href={isSignedIn ? '/subscription-plan' : '/register'}>
        {isSignedIn ? 'Upgrade Plan' : 'Join to Watch'}
      </a>
    </Button>
  )
}

function OnDemandShareMenu({ profile, copied, onCopy }: { profile: MediaItem; copied: boolean; onCopy: () => void }) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement | null>(null)
  const shareUrl = channelShareUrl(profile)
  const shareText = `Watch ${profile.name ?? 'this channel'} on eZWay TV`
  const shareTargets = [
    {
      label: 'LinkedIn',
      icon: LinkedInIcon,
      tone: 'hover:border-[#0a66c2]/70 hover:bg-[#0a66c2]/18',
      href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}`,
    },
    {
      label: 'Facebook',
      icon: FacebookIcon,
      tone: 'hover:border-[#1877f2]/70 hover:bg-[#1877f2]/18',
      href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`,
    },
    {
      label: 'X',
      icon: XIcon,
      tone: 'hover:border-white/50 hover:bg-white/14',
      href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`,
    },
    {
      label: 'WhatsApp',
      icon: WhatsAppIcon,
      tone: 'hover:border-[#25d366]/70 hover:bg-[#25d366]/18',
      href: `https://wa.me/?text=${encodeURIComponent(`${shareText} ${shareUrl}`)}`,
    },
    {
      label: 'SMS',
      icon: MessageCircle,
      tone: 'hover:border-primary/70 hover:bg-primary/16',
      href: `sms:?&body=${encodeURIComponent(`${shareText} ${shareUrl}`)}`,
    },
  ]

  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={menuRef} className="relative z-[90]">
      <Button
        type="button"
        variant="outline"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        className="h-10 w-full border-white/12 bg-white/[0.04] text-white hover:bg-white/[0.09] sm:w-auto"
      >
        <Share2 className="mr-2 h-4 w-4" />
        Share Channel
      </Button>
      <div
        role="menu"
        className={[
          'absolute left-0 top-full z-[120] mt-3 w-[min(13.5rem,calc(100vw-2rem))] rounded-md border border-white/12 bg-[#111]/98 p-3 shadow-2xl shadow-black/50 backdrop-blur transition',
          open ? 'visible opacity-100' : 'pointer-events-none invisible opacity-0',
        ].join(' ')}
      >
        <div className="grid grid-cols-3 gap-2">
          {shareTargets.map(({ label, icon: Icon, href, tone }) => (
            <a
              key={label}
              href={href}
              target={href.startsWith('http') ? '_blank' : undefined}
              rel={href.startsWith('http') ? 'noreferrer' : undefined}
              role="menuitem"
              onClick={() => setOpen(false)}
              aria-label={`Share on ${label}`}
              title={label}
              className={[
                'inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:text-white',
                tone,
              ].join(' ')}
            >
              <Icon className="h-[18px] w-[18px] shrink-0" />
              <span className="sr-only">{label}</span>
            </a>
          ))}
          <button
            type="button"
            role="menuitem"
            onClick={() => {
              onCopy()
              setOpen(false)
            }}
            aria-label={copied ? 'Link copied' : 'Copy link'}
            title={copied ? 'Copied' : 'Copy link'}
            className="inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/10 bg-white/[0.07] text-white/86 transition hover:border-primary/70 hover:bg-primary/16 hover:text-white"
          >
            {copied ? <Check className="h-[18px] w-[18px] shrink-0" /> : <Copy className="h-[18px] w-[18px] shrink-0" />}
            <span className="sr-only">{copied ? 'Copied' : 'Copy Link'}</span>
          </button>
        </div>
      </div>
    </div>
  )
}

function FacebookIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M14.2 8.1V6.6c0-.7.5-.9.9-.9h2.2V2.2L14.2 2c-3.4 0-4.2 2.1-4.2 4.1v2H7.3v3.9H10V22h4.2v-10h3.1l.5-3.9h-3.6Z" />
    </svg>
  )
}

function XIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M18.2 2h3.3l-7.2 8.2L22.7 22h-6.6l-5.2-6.8L5 22H1.7l7.7-8.8L1.3 2h6.8l4.7 6.2L18.2 2Zm-1.1 17.9h1.8L7.1 4H5.2l11.9 15.9Z" />
    </svg>
  )
}

function WhatsAppIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M12 2a9.8 9.8 0 0 0-8.5 14.7L2.3 22l5.4-1.4A9.9 9.9 0 1 0 12 2Zm0 18.1a8 8 0 0 1-4.1-1.1l-.3-.2-3.2.8.9-3.1-.2-.3A8.1 8.1 0 1 1 12 20.1Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1c-.2.2-.7.8-.8 1-.2.2-.3.2-.6.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.3 0-.4.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-1 1-1 2.4s1 2.7 1.2 2.9c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.7.6.7.2 1.3.2 1.8.1.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3Z" />
    </svg>
  )
}

function LinkedInIcon({ className }: ShareIconProps) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className} fill="currentColor">
      <path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5ZM.3 8.2h4.4V23H.3V8.2ZM8 8.2h4.2v2h.1c.6-1.1 2-2.3 4.2-2.3 4.5 0 5.3 3 5.3 6.8V23h-4.4v-7.4c0-1.8 0-4-2.4-4s-2.8 1.9-2.8 3.9V23H8V8.2Z" />
    </svg>
  )
}

function ChannelCard({ channel }: { channel: MediaItem }) {
  const href = channel.username ? `/on-demand/${channel.username}` : '/on-demand'

  return (
    <a href={href} className="group block min-w-0 overflow-hidden rounded-md border border-white/10 bg-[#141414] transition hover:-translate-y-0.5 hover:border-primary/60 hover:bg-[#191919]">
      <div className="relative">
        <MediaThumbnail
          src={channelThumb(channel)}
          alt={channel.name}
          className="aspect-[16/10] w-full"
        />
        <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/88 to-transparent" />
        <Badge className="absolute bottom-3 left-3 rounded-sm bg-black/70 text-white">{channel.videos_count ?? 0} videos</Badge>
        {channel.access === 'paid' ? (
          <Badge className="absolute right-3 top-3 rounded-sm bg-primary text-black">
            <Lock className="mr-1 h-3.5 w-3.5" />
            Premium
          </Badge>
        ) : null}
      </div>
      <div className="grid min-h-24 grid-cols-[56px_minmax(0,1fr)] gap-3 p-4">
        <MediaThumbnail
          src={channel.avatar_image_url ?? channel.profile_image ?? channelThumb(channel)}
          alt={channel.name}
          className="h-14 w-14 shrink-0 rounded-full border-2 border-[#d6a83a] ring-2 ring-black"
        />
        <div className="min-w-0">
          <h2 className="line-clamp-2 text-base font-black leading-tight text-white">{channel.name}</h2>
          <p className="mt-1 truncate text-xs font-semibold text-white/52">@{channel.username}</p>
        </div>
      </div>
    </a>
  )
}

function ChannelListItem({ channel, active }: { channel: MediaItem; active: boolean }) {
  const href = channel.username ? `/on-demand/${channel.username}` : '/on-demand'

  return (
    <a
      href={href}
      className={[
        'flex min-h-20 w-full items-center gap-3 rounded-md border p-3 text-left transition',
        active
          ? 'border-primary/70 bg-primary/12 text-white'
          : 'border-white/10 bg-white/[0.045] text-white hover:bg-white/[0.075]',
      ].join(' ')}
    >
      <MediaThumbnail
        src={channelThumb(channel)}
        alt={channel.name}
        className="h-16 w-24 shrink-0 rounded-md"
      />
      <span className="min-w-0 flex-1">
        <span className="flex min-w-0 items-center gap-2">
          <span className="block min-w-0 truncate text-sm font-bold">{channel.name}</span>
          {channel.access === 'paid' ? <Lock className="h-3.5 w-3.5 shrink-0 text-primary" /> : null}
        </span>
        <span className="mt-1 block truncate text-xs text-white/52">@{channel.username} · {channel.videos_count ?? 0} videos</span>
      </span>
    </a>
  )
}

function VideoCard({ video, channelId }: { video: MediaItem; channelId: string | number }) {
  const image = videoThumb(video)
  const href = `/video-details/${video.slug}?autoplay=1&ondemand_channel=${channelId}`
  const locked = isPremiumVideoCard(video)
  const inWatchlist = video.is_watch_list ?? video.is_in_watchlist

  return (
    <a href={href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.02] group-hover:border-primary/60">
        <MediaThumbnail src={image} alt={video.name} previewSrc={previewHref(video)} />
        <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/82 to-transparent" />
        {locked ? <div className="absolute inset-0 bg-black/38" /> : null}
        <div className="absolute inset-0 flex items-center justify-center">
          <span className={[
            'flex h-12 w-12 items-center justify-center rounded-full shadow-xl ring-1 ring-black/20 transition duration-200 group-hover:scale-110',
            locked ? 'bg-primary text-black group-hover:bg-primary group-hover:text-black' : 'bg-white/92 text-black group-hover:bg-primary group-hover:text-white',
          ].join(' ')}>
            {locked ? <Lock className="h-6 w-6" /> : <Play className="ml-0.5 h-6 w-6 fill-current" />}
          </span>
        </div>
        {locked ? (
          <Badge className="absolute left-3 top-3 rounded-sm bg-primary text-black">
            <Lock className="mr-1 h-3.5 w-3.5" />
            Premium
          </Badge>
        ) : null}
        <div className="absolute right-3 top-3 z-10">
          <WatchlistToggleButton
            entertainmentId={video.id}
            type="video"
            initialInWatchlist={inWatchlist}
          />
        </div>
        {video.duration ? <Badge className="absolute bottom-3 right-3 rounded-sm bg-black/70 text-white">{video.duration}</Badge> : null}
      </div>
      <h4 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{video.name}</h4>
    </a>
  )
}

function SearchBox({
  value,
  onChange,
  placeholder,
  compact = false,
}: {
  value: string
  onChange: (value: string) => void
  placeholder: string
  compact?: boolean
}) {
  return (
    <label className={['flex items-center gap-2 rounded-md border border-white/10 bg-white/[0.06] px-3 py-2', compact ? 'w-full' : 'w-full'].join(' ')}>
      <Search className="h-4 w-4 shrink-0 text-white/48" />
      <input
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className="h-9 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
      />
    </label>
  )
}

function channelThumb(channel: MediaItem) {
  return channel.cover_image_url
    ?? channel.poster_tv_image
    ?? channel.poster_image
    ?? channel.thumbnail_url
    ?? channel.avatar_image_url
    ?? channel.profile_image
    ?? null
}

function channelAvatarThumb(channel: MediaItem) {
  return channel.avatar_image_url
    ?? channel.profile_image
    ?? channel.cover_image_url
    ?? channel.poster_tv_image
    ?? channel.poster_image
    ?? channel.thumbnail_url
    ?? null
}

function channelShareUrl(profile: MediaItem) {
  return `${window.location.origin}/on-demand/${encodeURIComponent(String(profile.username ?? ''))}`
}

async function copyText(value: string) {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value)
    return
  }

  const textArea = document.createElement('textarea')
  textArea.value = value
  textArea.setAttribute('readonly', '')
  textArea.style.position = 'fixed'
  textArea.style.top = '-9999px'
  document.body.appendChild(textArea)
  textArea.select()
  document.execCommand('copy')
  document.body.removeChild(textArea)
}

function videoThumb(video: MediaItem) {
  return video.poster_tv_image
    ?? video.thumbnail_url
    ?? video.poster_url
    ?? video.poster_image
    ?? video.cover_image_url
    ?? null
}

function ChannelGridSkeleton() {
  return (
    <>
      {[0, 1, 2, 3, 4, 5, 6, 7].map((item) => (
        <div key={item} className="h-72 animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </>
  )
}

function ChannelListSkeleton() {
  return (
    <>
      {[0, 1, 2, 3].map((item) => (
        <div key={item} className="h-20 animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </>
  )
}

function EmptyState({ message }: { message: string }) {
  return (
    <div className="rounded-md border border-white/10 bg-white/[0.04] p-10 text-center text-white/56">
      <Tv className="mx-auto mb-3 h-9 w-9 text-white/36" />
      <p className="font-semibold">{message}</p>
    </div>
  )
}

function getUsernameFromPath(path: string) {
  const pathname = path.split(/[?#]/)[0] || '/'
  const match = pathname.match(/^\/(?:on-demand|react-ondemand|spa\/ondemand)\/([^/]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

function previewHref(video: MediaItem) {
  return video.video_url_input ?? video.video_url ?? video.trailer_url ?? null
}

function isPremiumVideoCard(video: MediaItem) {
  if (video.access !== 'paid') return false

  if (typeof video.has_content_access !== 'undefined' && video.has_content_access !== null) {
    return !Boolean(video.has_content_access)
  }

  return true
}

function isPremiumChannelLocked(item: MediaItem) {
  if (item.access !== 'paid') return false

  if (typeof item.has_content_access !== 'undefined' && item.has_content_access !== null) {
    return !Boolean(item.has_content_access)
  }

  return true
}

function requiredPlanLabel(item: MediaItem) {
  return item.required_plan_name ? `${item.required_plan_name} plan` : 'a premium plan'
}

function updatePageMeta({ title, description, image, url }: { title: string; description: string; image: string | null; url: string }) {
  const metaImage = image ?? `${window.location.origin}/default-image/Default-Image.jpg`

  document.title = title
  setMeta('name', 'title', title)
  setMeta('name', 'description', description)
  setMeta('property', 'og:title', title)
  setMeta('property', 'og:description', description)
  setMeta('property', 'og:url', url)
  setMeta('name', 'twitter:title', title)
  setMeta('name', 'twitter:description', description)
  setLink('canonical', url)
  setMeta('property', 'og:image', metaImage)
  setMeta('property', 'og:image:secure_url', metaImage)
  setMeta('name', 'twitter:image', metaImage)
  setMeta('name', 'seo_image', metaImage)
}

function setMeta(attribute: 'name' | 'property', key: string, content: string) {
  let element = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`)

  if (!element) {
    element = document.createElement('meta')
    element.setAttribute(attribute, key)
    document.head.appendChild(element)
  }

  element.setAttribute('content', content)
}

function setLink(rel: string, href: string) {
  let element = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`)

  if (!element) {
    element = document.createElement('link')
    element.setAttribute('rel', rel)
    document.head.appendChild(element)
  }

  element.setAttribute('href', href)
}
