import { useEffect, useMemo, useState } from 'react'
import { Clapperboard, Play, Search } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { useSpaNavigate } from '@/lib/spa-router'
import type { MediaItem } from '@/modules/home/types'
import { loadOnDemandChannels, loadOnDemandProfile } from './ondemandApi'

type ProfileState = {
  profile: MediaItem | null
  videos: MediaItem[]
}

export function OnDemandPage() {
  const initialUsername = getUsernameFromPath()
  const navigate = useSpaNavigate()
  const [channels, setChannels] = useState<MediaItem[]>([])
  const [selectedUsername, setSelectedUsername] = useState(initialUsername)
  const [profileState, setProfileState] = useState<ProfileState>({ profile: null, videos: [] })
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [profileLoading, setProfileLoading] = useState(Boolean(initialUsername))

  useEffect(() => {
    let mounted = true

    loadOnDemandChannels()
      .then((items) => {
        if (!mounted) return
        setChannels(items)
        if (!selectedUsername && items[0]?.username) {
          setSelectedUsername(items[0].username)
        }
      })
      .finally(() => {
        if (mounted) setLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [])

  useEffect(() => {
    if (!selectedUsername) return

    let mounted = true
    setProfileLoading(true)

    loadOnDemandProfile(selectedUsername)
      .then((data) => {
        if (mounted) setProfileState(data)
      })
      .finally(() => {
        if (mounted) setProfileLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [selectedUsername])

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

      <section className="relative overflow-hidden px-4 pb-10 pt-10 sm:px-8 lg:px-12">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_78%_0%,rgba(229,9,20,0.28),transparent_28%)]" />
        <div className="relative grid gap-8 lg:grid-cols-[0.78fr_1.22fr]">
          <aside className="min-w-0">
            <Badge className="rounded-sm bg-primary text-white">On Demand</Badge>
            <h1 className="mt-4 text-4xl font-black leading-none sm:text-5xl">Creator channels, rebuilt for React.</h1>
            <p className="mt-4 max-w-xl text-sm leading-6 text-white/64 sm:text-base">
              This module uses the existing On Demand APIs and keeps playback routed through the current video detail system so ads, stats, and access rules stay connected.
            </p>

            <div className="mt-7 flex items-center gap-2 rounded-md border border-white/10 bg-white/[0.06] px-3 py-2">
              <Search className="h-4 w-4 text-white/48" />
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search channels"
                className="h-9 min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/42"
              />
            </div>

            <div className="mt-5 grid gap-3">
              {loading ? (
                <ChannelSkeleton />
              ) : filteredChannels.length > 0 ? (
                filteredChannels.map((channel) => (
                  <button
                    key={channel.id}
                    type="button"
                    onClick={() => {
                      setSelectedUsername(channel.username ?? '')
                      navigate(channel.username ? `/on-demand/${channel.username}` : '/on-demand', { replace: true })
                    }}
                    className={[
                      'flex min-h-20 w-full items-center gap-3 rounded-md border p-3 text-left transition',
                      selectedUsername === channel.username
                        ? 'border-primary/70 bg-primary/12'
                        : 'border-white/10 bg-white/[0.045] hover:bg-white/[0.075]',
                    ].join(' ')}
                  >
                    <MediaThumbnail
                      src={channelThumb(channel)}
                      alt={channel.name}
                      className="h-16 w-24 shrink-0 rounded-md"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-bold text-white">{channel.name}</span>
                      <span className="mt-1 block text-xs text-white/52">@{channel.username} · {channel.videos_count ?? 0} videos</span>
                    </span>
                  </button>
                ))
              ) : (
                <div className="rounded-md border border-white/10 bg-white/[0.04] p-4 text-sm text-white/56">No channels matched.</div>
              )}
            </div>
          </aside>

          <section className="min-w-0">
            <ProfilePanel loading={profileLoading} profile={profileState.profile} videos={profileState.videos} />
          </section>
        </div>
      </section>
    </main>
  )
}

function ProfilePanel({ loading, profile, videos }: { loading: boolean; profile: MediaItem | null; videos: MediaItem[] }) {
  if (loading) {
    return <div className="min-h-[620px] rounded-md border border-white/10 bg-white/[0.04]" />
  }

  if (!profile) {
    return (
      <div className="flex min-h-[620px] items-center justify-center rounded-md border border-white/10 bg-white/[0.04] text-white/56">
        Select an On Demand channel.
      </div>
    )
  }

  return (
    <article className="overflow-hidden rounded-md border border-white/10 bg-white/[0.045] shadow-2xl">
      <div className="relative aspect-[21/8] min-h-56 overflow-hidden bg-white/[0.04]">
        {profile.cover_image_url ? <img src={profile.cover_image_url} alt="" className="h-full w-full object-cover" /> : null}
        <div className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />
      </div>

      <div className="relative px-4 pb-6 sm:px-6">
        <div className="-mt-12 flex flex-wrap items-end gap-4">
          <MediaThumbnail
            src={profile.avatar_image_url ?? profile.cover_image_url}
            alt={profile.name}
            className="h-24 w-24 shrink-0 rounded-md border-4 border-[#050505] shadow-xl"
          />
          <div className="min-w-0 flex-1 pb-2">
            <h2 className="truncate text-3xl font-black text-white">{profile.name}</h2>
            <p className="mt-1 text-sm text-white/58">@{profile.username} · {profile.videos_count ?? videos.length} videos</p>
          </div>
          <Button asChild className="mb-2 bg-white text-black hover:bg-white/85">
            <a href={`/on-demand/${profile.username}`}>View Channel</a>
          </Button>
        </div>

        {profile.description ? (
          <p className="mt-5 line-clamp-3 max-w-4xl text-sm leading-6 text-white/64">{stripHtml(profile.description)}</p>
        ) : null}

        <div className="mt-8">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-xl font-bold">Videos</h3>
            <Badge variant="outline" className="border-white/16 text-white/70">{videos.length} loaded</Badge>
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

function VideoCard({ video, channelId }: { video: MediaItem; channelId: string | number }) {
  const image = videoThumb(video)
  const href = `/video-details/${video.slug}?autoplay=1&ondemand_channel=${channelId}`

  return (
    <a href={href} className="group block min-w-0">
      <div className="relative overflow-hidden rounded-md border border-white/10 bg-black shadow-lg transition group-hover:scale-[1.02] group-hover:border-primary/60">
        <MediaThumbnail src={image} alt={video.name} previewSrc={previewHref(video)} />
        <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/82 to-transparent" />
        <div className="absolute inset-0 flex items-center justify-center opacity-0 transition duration-200 group-hover:opacity-100">
          <span className="flex h-12 w-12 items-center justify-center rounded-full bg-white/92 text-black shadow-xl ring-1 ring-black/20">
            <Play className="ml-0.5 h-6 w-6 fill-current" />
          </span>
        </div>
        {video.duration ? <Badge className="absolute bottom-3 right-3 rounded-sm bg-black/70 text-white">{video.duration}</Badge> : null}
      </div>
      <h4 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{video.name}</h4>
    </a>
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

function videoThumb(video: MediaItem) {
  return video.poster_tv_image
    ?? video.thumbnail_url
    ?? video.poster_url
    ?? video.poster_image
    ?? video.cover_image_url
    ?? null
}

function ChannelSkeleton() {
  return (
    <>
      {[0, 1, 2].map((item) => (
        <div key={item} className="h-20 animate-pulse rounded-md border border-white/10 bg-white/[0.045]" />
      ))}
    </>
  )
}

function getUsernameFromPath() {
  const match = window.location.pathname.match(/^\/(?:on-demand|react-ondemand|spa\/ondemand)\/([^/]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

function stripHtml(value: string) {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

function previewHref(video: MediaItem) {
  return video.video_url_input ?? video.video_url ?? video.trailer_url ?? null
}
