import { Calendar, Clock, Film, Star } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useMemo } from 'react'

import { AppHeader } from '@/components/AppHeader'
import { Badge } from '@/components/ui/badge'
import { MediaThumbnail } from '@/components/MediaThumbnail'
import { PlayerBackButton } from '@/components/PlayerBackButton'
import type { MediaItem } from '@/modules/home/types'
import { loadOnDemandProfile } from '@/modules/ondemand/ondemandApi'
import { VideoJsPlayer } from '@/modules/video-detail/VideoJsPlayer'
import { loadMovieDetail } from '@/modules/video-detail/videoDetailApi'

export function MoviePlayerPage() {
  const movieId = window.location.pathname.split('/').filter(Boolean).pop() ?? ''
  const autoplay = new URLSearchParams(window.location.search).get('autoplay') === '1'
  const movieQuery = useQuery({
    queryKey: ['movie-detail', movieId],
    queryFn: () => loadMovieDetail(movieId),
    enabled: Boolean(movieId),
  })
  const movie = movieQuery.data

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="movies" />
      <section className="relative left-1/2 w-screen -translate-x-1/2 bg-black">
        <div className="flex min-h-12 items-center border-b border-white/10 bg-[#050505] px-3 py-2 sm:min-h-14 sm:px-5">
          <PlayerBackButton fallbackHref="/on-demand/ezway-movie-channel" />
        </div>
        {movieQuery.isLoading ? (
          <div className="aspect-video w-full animate-pulse bg-white/[0.05] sm:aspect-auto sm:h-[76svh]" />
        ) : movieQuery.isError || !movie ? (
          <div className="flex min-h-[55svh] items-center justify-center bg-red-500/10 p-8 text-center text-red-100">Movie could not be loaded.</div>
        ) : (
          <MovieContent movie={movie} autoplay={autoplay} />
        )}
      </section>
    </main>
  )
}

function MovieContent({ movie, autoplay }: { movie: MediaItem; autoplay: boolean }) {
  const source = resolveMovieSource(movie)
  const poster = movie.thumbnail_url ?? movie.poster_tv_image ?? movie.poster_image ?? movie.poster_url
  const subtitles = useMemo(() => {
    const available = (movie.subtitle_info ?? []).filter((subtitle) => Boolean(subtitle.subtitle_file))
    const hasExplicitDefault = available.some((subtitle) => subtitle.is_default === true || subtitle.is_default === 1 || subtitle.is_default === '1')

    return available.map((subtitle, index) => ({
      src: subtitle.subtitle_file as string,
      label: subtitle.language?.trim() || subtitle.language_code?.trim() || 'Subtitles',
      language: subtitle.language_code?.trim() || undefined,
      default: subtitle.is_default === true || subtitle.is_default === 1 || subtitle.is_default === '1' || (!hasExplicitDefault && index === 0),
    }))
  }, [movie.subtitle_info])
  const castNames = names(movie.casts)
  const directorNames = names(movie.directors)
  const descriptionHasCredits = /\b(?:starring|cast|director):/i.test(movie.description ?? '')
  const otherMoviesQuery = useQuery({
    queryKey: ['other-movies', 'ezway-movie-channel'],
    queryFn: () => loadOnDemandProfile('ezway-movie-channel'),
    staleTime: 5 * 60_000,
  })
  const otherMovies = (otherMoviesQuery.data?.movies ?? []).filter((item) => String(item.id) !== String(movie.id))

  return (
    <>
      <div className="relative aspect-video h-auto min-h-0 w-full overflow-hidden bg-black sm:aspect-auto sm:h-[76svh] sm:min-h-[430px]">
        {source ? (
          <VideoJsPlayer source={source} poster={poster} autoplay={autoplay} muted={autoplay} vastAds={[]} subtitles={subtitles} />
        ) : (
          <div className="flex h-full items-center justify-center text-white/55">No playable movie source was returned.</div>
        )}
      </div>

      <div className="border-t border-white/10 bg-[#050505] px-3 pb-4 pt-4 sm:px-6 lg:px-8">
        <div className="mx-auto flex w-full max-w-[1800px] min-w-0 items-center gap-4">
          <MediaThumbnail src={movie.poster_image ?? movie.poster_url} alt={movie.name} className="h-20 w-14 shrink-0 !aspect-auto rounded-sm border border-white/10" imageClassName="object-cover" />
          <div className="min-w-0 flex-1">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <Badge className="rounded-sm bg-primary text-black">Movie</Badge>
              {movie.genres?.map((genre) => genre.name).filter(Boolean).map((genre) => <Badge key={genre} className="rounded-sm bg-white/14 text-white">{genre}</Badge>)}
            </div>
            <h1 className="line-clamp-2 text-xl font-black leading-snug text-white sm:text-2xl lg:text-[1.65rem]">{movie.name}</h1>
            <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-white/58">
              {movie.release_date ? <span className="inline-flex items-center gap-1.5"><Calendar className="h-4 w-4 text-primary" />{String(movie.release_date).slice(0, 4)}</span> : null}
              {movie.duration ? <span className="inline-flex items-center gap-1.5"><Clock className="h-4 w-4 text-primary" />{movie.duration}</span> : null}
              {movie.language ? <span className="inline-flex items-center gap-1.5"><Film className="h-4 w-4 text-primary" />{movie.language}</span> : null}
              {movie.imdb_rating ? <span className="inline-flex items-center gap-1.5"><Star className="h-4 w-4 fill-primary text-primary" />{movie.imdb_rating}</span> : null}
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto w-full max-w-[1800px] px-3 pb-8 pt-6 sm:px-6 lg:px-8">
        <section className="rounded-md border border-white/10 bg-white/[0.035] p-4 sm:p-6">
          <h2 className="border-b border-white/10 pb-3 text-lg font-black text-white">About this movie</h2>
          {movie.description ? (
            <div
              className="mt-4 max-w-5xl text-sm leading-7 text-white/70 [&_h1]:mb-4 [&_h1]:text-xl [&_h1]:font-black [&_h2]:mb-4 [&_h2]:text-xl [&_h2]:font-black [&_h3]:mb-3 [&_h3]:text-lg [&_h3]:font-bold [&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-black [&_strong]:text-white [&_em]:italic"
              dangerouslySetInnerHTML={{ __html: movie.description }}
            />
          ) : null}

          {!descriptionHasCredits && (castNames || directorNames) ? (
            <dl className="mt-5 grid gap-2 border-t border-white/10 pt-4 text-sm">
              {castNames ? <div className="flex gap-2"><dt className="font-bold text-white">Cast:</dt><dd className="text-white/65">{castNames}</dd></div> : null}
              {directorNames ? <div className="flex gap-2"><dt className="font-bold text-white">Director:</dt><dd className="text-white/65">{directorNames}</dd></div> : null}
            </dl>
          ) : null}
        </section>
        {otherMovies.length ? <OtherMovies movies={otherMovies} /> : null}
      </div>
    </>
  )
}

function OtherMovies({ movies }: { movies: MediaItem[] }) {
  return (
    <section className="mt-8 min-w-0">
      <div className="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
        <h2 className="text-xl font-black text-white">Other Movies</h2>
        <Badge variant="outline" className="border-white/15 text-white/65">{movies.length} movies</Badge>
      </div>
      <div className="grid grid-flow-col auto-cols-[42%] gap-3 overflow-x-auto pb-5 [scrollbar-width:none] [-ms-overflow-style:none] sm:auto-cols-[calc((100%-4rem)/5)] lg:auto-cols-[calc((100%-6rem)/7)] 2xl:auto-cols-[calc((100%-8rem)/9)] [&::-webkit-scrollbar]:hidden">
        {movies.map((item) => (
          <a key={item.id} href={`/watch-movie/${item.id}?autoplay=1`} className="group block min-w-0">
            <div className="relative aspect-[2/3] overflow-hidden rounded-md border border-white/10 bg-[#111] transition duration-300 group-hover:-translate-y-1 group-hover:border-primary/60">
              <MediaThumbnail src={item.poster_image ?? item.poster_url ?? item.thumbnail_url} alt={item.name} className="h-full w-full" imageClassName="object-cover" />
            </div>
            <h3 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-white">{item.name}</h3>
          </a>
        ))}
      </div>
    </section>
  )
}

function resolveMovieSource(movie: MediaItem) {
  return movie.video_url_input ?? movie.video_url ?? movie.video_qualities?.find((quality) => quality.url)?.url ?? null
}

function names(people?: Array<{ name?: string | null }>) {
  return people?.map((person) => person.name?.trim()).filter(Boolean).join(', ') ?? ''
}
