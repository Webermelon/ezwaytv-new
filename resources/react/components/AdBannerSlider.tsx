import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api'
import type { ApiEnvelope } from '@/modules/home/types'

type AdBannerSlide = {
  id?: number | string
  title?: string | null
  image?: string | null
  link?: string | null
  link_url?: string | null
  placements?: string[] | null
}

type AdBannerSliderProps = {
  placement: 'home' | 'video' | 'livetv' | 'tvshow'
  className?: string
  showNetworkAd?: boolean
}

export function AdBannerSlider({ placement, className = '', showNetworkAd = false }: AdBannerSliderProps) {
  const [activeIndex, setActiveIndex] = useState(0)
  const slidesQuery = useQuery({
    queryKey: ['ad-banner-sliders', placement],
    queryFn: () => loadAdBannerSlides(placement),
    staleTime: 60_000,
    initialData: () => readCachedAdBannerSlides(placement),
  })
  const slides = (slidesQuery.data ?? []).filter((slide) => Boolean(slide.image))
  const activeSlide = slides[activeIndex] ?? slides[0]

  useEffect(() => {
    setActiveIndex(0)
  }, [placement, slides.length])

  useEffect(() => {
    if (slides.length < 2) return

    const timer = window.setInterval(() => {
      setActiveIndex((index) => (index + 1) % slides.length)
    }, 3000)

    return () => window.clearInterval(timer)
  }, [slides.length])

  useEffect(() => {
    if (slidesQuery.data) {
      writeCachedAdBannerSlides(placement, slidesQuery.data)
    }
  }, [placement, slidesQuery.data])

  if (slides.length === 0 || !activeSlide) return null

  return (
    <section className={['bg-[#050505] px-4 py-5 sm:px-8 lg:px-12', className].filter(Boolean).join(' ')}>
      <div className="mx-auto max-w-[1800px]">
        <div className={showNetworkAd ? 'grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]' : undefined}>
          <div className="relative aspect-[16/5] min-h-[150px] min-w-0 overflow-hidden rounded-md border border-white/10 bg-black shadow-2xl shadow-black/40 sm:min-h-[210px] lg:min-h-[300px]">
            <div
              className="flex h-full w-full transition-transform duration-700 ease-out motion-reduce:transition-none"
              style={{ transform: `translateX(-${activeIndex * 100}%)` }}
            >
              {slides.map((slide, index) => (
                <div key={`${slide.id ?? index}-slide`} className="h-full w-full flex-none">
                  <SlideImage slide={slide} eager={index === activeIndex} />
                </div>
              ))}
            </div>

            {slides.length > 1 ? (
              <div className="absolute bottom-4 left-0 right-0 flex justify-center gap-2">
                {slides.map((slide, index) => (
                  <button
                    key={`${slide.id ?? index}-dot`}
                    type="button"
                    aria-label={`Show banner ${index + 1}`}
                    onClick={() => setActiveIndex(index)}
                    className={[
                      'h-1.5 rounded-full transition',
                      index === activeIndex ? 'w-7 bg-white' : 'w-1.5 bg-white/45 hover:bg-white/75',
                    ].join(' ')}
                  />
                ))}
              </div>
            ) : null}
          </div>

          {showNetworkAd ? (
            <iframe
              title="Advertisement"
              src="https://ads.ezwaynetwork.com/ad-placement/all-sites-top-square-ad-400-x-400/"
              className="hidden h-[360px] w-full rounded-md border-0 bg-black lg:block"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          ) : null}
        </div>
      </div>
    </section>
  )
}

function SlideImage({ slide, eager = false }: { slide: AdBannerSlide; eager?: boolean }) {
  const image = (
    <img
      src={slide.image ?? ''}
      alt={slide.title ?? 'Advertisement'}
      className="h-full w-full object-cover"
      loading={eager ? 'eager' : 'lazy'}
    />
  )
  const href = slide.link_url ?? slide.link

  if (!href) return image

  return (
    <a href={href} target="_blank" rel="noreferrer" className="block h-full w-full">
      {image}
    </a>
  )
}

async function loadAdBannerSlides(placement: AdBannerSliderProps['placement']) {
  const params = new URLSearchParams({
    placements: placement,
    limit: '20',
  })
  const response = await api.get<ApiEnvelope<AdBannerSlide[]>>(`/api/v3/ad-banner-sliders?${params.toString()}`)

  return response.data ?? []
}

function readCachedAdBannerSlides(placement: AdBannerSliderProps['placement']) {
  try {
    const cached = window.localStorage.getItem(`ezway_ad_banner_slides_${placement}`)

    return cached ? JSON.parse(cached) as AdBannerSlide[] : undefined
  } catch {
    return undefined
  }
}

function writeCachedAdBannerSlides(placement: AdBannerSliderProps['placement'], slides: AdBannerSlide[]) {
  try {
    window.localStorage.setItem(`ezway_ad_banner_slides_${placement}`, JSON.stringify(slides))
  } catch {
    // Ignore storage failures; the live API data is still rendered.
  }
}
