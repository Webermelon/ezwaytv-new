import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'

import { api } from '@/lib/api'
import type { ApiEnvelope } from '@/modules/home/types'

type AdBannerSlide = {
  id?: number | string
  title?: string | null
  image?: string | null
  image_proxy_url?: string | null
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
  const [activeIndex, setActiveIndex] = useState(1)
  const [isTransitioning, setIsTransitioning] = useState(true)
  const slidesQuery = useQuery({
    queryKey: ['ad-banner-sliders', placement],
    queryFn: () => loadAdBannerSlides(placement),
    staleTime: 60_000,
  })
  const slides = (slidesQuery.data ?? []).filter((slide) => Boolean(slide.image))
  const displaySlides = useMemo(() => {
    if (slides.length < 2) return slides

    return [slides[slides.length - 1], ...slides, slides[0]]
  }, [slides])
  const realActiveIndex = slides.length < 2 ? 0 : normalizeSlideIndex(activeIndex, slides.length)

  useEffect(() => {
    setIsTransitioning(false)
    setActiveIndex(slides.length < 2 ? 0 : 1)

    const frame = window.requestAnimationFrame(() => {
      setIsTransitioning(true)
    })

    return () => window.cancelAnimationFrame(frame)
  }, [placement, slides.length])

  useEffect(() => {
    if (slides.length < 2) return

    const timer = window.setInterval(() => {
      setIsTransitioning(true)
      setActiveIndex((index) => index + 1)
    }, 3000)

    return () => window.clearInterval(timer)
  }, [slides.length])

  useEffect(() => {
    clearCachedAdBannerSlides()
  }, [])

  if (slides.length === 0) return null

  const handleTransitionEnd = () => {
    if (slides.length < 2) return

    if (activeIndex === 0) {
      setIsTransitioning(false)
      setActiveIndex(slides.length)
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => setIsTransitioning(true))
      })
    }

    if (activeIndex === slides.length + 1) {
      setIsTransitioning(false)
      setActiveIndex(1)
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => setIsTransitioning(true))
      })
    }
  }

  return (
    <section className={['bg-[#050505] px-4 py-5 sm:px-8 lg:px-12', className].filter(Boolean).join(' ')}>
      <div className="mx-auto max-w-[1800px]">
        <div className={showNetworkAd ? 'grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]' : undefined}>
          <div className="relative aspect-[16/5] min-h-[150px] min-w-0 overflow-hidden rounded-md border border-white/10 bg-[#050505] shadow-2xl shadow-black/40 sm:min-h-[210px] lg:min-h-[300px]">
            <div
              className={[
                'flex h-full w-full motion-reduce:transition-none',
                isTransitioning ? 'transition-transform duration-700 ease-out' : '',
              ].join(' ')}
              style={{ transform: `translateX(-${activeIndex * 100}%)` }}
              onTransitionEnd={handleTransitionEnd}
            >
              {displaySlides.map((slide, index) => (
                <div key={`${slide.id ?? index}-slide-${index}`} className="h-full w-full flex-none">
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
                    onClick={() => {
                      setIsTransitioning(true)
                      setActiveIndex(index + 1)
                    }}
                    className={[
                      'h-1.5 rounded-full transition',
                      index === realActiveIndex ? 'w-7 bg-white' : 'w-1.5 bg-white/45 hover:bg-white/75',
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

function normalizeSlideIndex(index: number, slideCount: number) {
  if (slideCount < 2) return 0
  if (index === 0) return slideCount - 1
  if (index === slideCount + 1) return 0

  return index - 1
}

function SlideImage({ slide, eager = false }: { slide: AdBannerSlide; eager?: boolean }) {
  const imageSrc = resolveSlideImage(slide)
  const image = (
    <img
      src={imageSrc}
      alt={slide.title ?? 'Promotion'}
      className="h-full w-full object-contain"
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

function resolveSlideImage(slide: AdBannerSlide) {
  if (slide.image_proxy_url) return slide.image_proxy_url
  if (slide.id) return `/api/v3/promo-slides/${slide.id}/image`

  return slide.image ?? ''
}

async function loadAdBannerSlides(placement: AdBannerSliderProps['placement']) {
  const params = new URLSearchParams({
    placements: placement,
    limit: '20',
  })
  const response = await api.get<ApiEnvelope<AdBannerSlide[]>>(`/api/v3/promo-slides?${params.toString()}`)

  return response.data ?? []
}

function clearCachedAdBannerSlides() {
  try {
    ;['home', 'video', 'livetv', 'tvshow'].forEach((placement) => {
      window.localStorage.removeItem(`ezway_ad_banner_slides_${placement}`)
    })
  } catch {
    // Ignore storage failures; the live API data is still rendered.
  }
}
