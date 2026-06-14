import { useEffect, useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Clapperboard, Play, Search, Tv } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { trackView } from '@/lib/analytics'
import { useSpaPath } from '@/lib/spa-router'
import type { MediaItem } from '@/modules/home/types'
import { loadOnDemandChannels, loadOnDemandProfile } from './ondemandApi'

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
            <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
              <ChannelSidebar
                channels={filteredChannels}
                loading={channelsQuery.isLoading}
                query={query}
                selectedUsername={routeUsername}
                onQueryChange={setQuery}
              />
              <ProfilePanel loading={profileQuery.isLoading} profile={profileState.profile} videos={profileState.videos} />
            </div>
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
    <aside className="min-w-0 rounded-md border border-white/10 bg-[#111]/86 p-4 shadow-2xl shadow-black/30 xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto">
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

  return (
    <article className="min-w-0 overflow-hidden rounded-md border border-white/10 bg-[#111]/86 shadow-2xl shadow-black/40">
      <div className="relative aspect-[16/9] overflow-hidden bg-white/[0.04] sm:aspect-[21/8] sm:min-h-72">
        {profile.cover_image_url ? <img src={profile.cover_image_url} alt="" className="h-full w-full object-cover" /> : null}
        <div className="absolute inset-0 bg-gradient-to-t from-[#111] via-black/25 to-black/10" />
      </div>

      <div className="relative px-4 pb-6 sm:px-6">
        <div className="-mt-10 grid gap-4 sm:-mt-12 sm:flex sm:flex-wrap sm:items-end">
          <div className="flex min-w-0 items-end gap-3 sm:flex-1 sm:gap-4">
            <MediaThumbnail
              src={profile.avatar_image_url ?? profile.cover_image_url}
              alt={profile.name}
              className="h-20 w-20 shrink-0 rounded-full border-4 border-[#d6a83a] shadow-xl ring-4 ring-[#050505] sm:h-24 sm:w-24"
            />
            <div className="min-w-0 flex-1 pb-1 sm:pb-2">
              <h2 className="line-clamp-2 text-2xl font-black leading-tight text-white sm:truncate sm:text-3xl">{profile.name}</h2>
              <p className="mt-1 text-sm leading-5 text-white/58">@{profile.username} · {profile.videos_count ?? videos.length} videos</p>
            </div>
          </div>
          <Button asChild variant="outline" className="h-10 w-full border-white/12 bg-white/[0.04] text-white hover:bg-white/[0.09] sm:mb-2 sm:w-auto xl:hidden">
            <a href="/on-demand">
              <ArrowLeft className="mr-2 h-4 w-4" />
              All Channels
            </a>
          </Button>
        </div>

        {profile.description ? (
          <p className="mt-5 line-clamp-3 max-w-4xl text-sm leading-6 text-white/64">{stripHtml(profile.description)}</p>
        ) : null}

        <div className="mt-8">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-xl font-bold">Videos</h3>
            <Badge variant="outline" className="border-white/16 text-white/70">{videos.length} videos</Badge>
          </div>

          {videos.length > 0 ? (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
        <span className="block truncate text-sm font-bold">{channel.name}</span>
        <span className="mt-1 block truncate text-xs text-white/52">@{channel.username} · {channel.videos_count ?? 0} videos</span>
      </span>
    </a>
  )
}

function VideoCard({ video, channelId }: { video: MediaItem; channelId: string | number }) {
  const image = videoThumb(video)
  const href = `/video-details/${video.slug}?autoplay=1&ondemand_channel=${channelId}`

  return (
    <a href={href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.02] group-hover:border-primary/60">
        <MediaThumbnail src={image} alt={video.name} previewSrc={previewHref(video)} />
        <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/82 to-transparent" />
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="flex h-12 w-12 items-center justify-center rounded-full bg-white/92 text-black shadow-xl ring-1 ring-black/20 transition duration-200 group-hover:scale-110 group-hover:bg-primary group-hover:text-white">
            <Play className="ml-0.5 h-6 w-6 fill-current" />
          </span>
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
