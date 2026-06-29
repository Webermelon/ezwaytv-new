import { useRef, useState } from 'react'

type MediaThumbnailProps = {
  src?: string | null
  alt?: string
  previewSrc?: string | null
  className?: string
  imageClassName?: string
}

export function MediaThumbnail({ src, alt = '', previewSrc, className = '', imageClassName = 'object-contain' }: MediaThumbnailProps) {
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const [previewing, setPreviewing] = useState(false)
  const imageSrc = isPlaceholderImage(src) ? null : src

  function playPreview() {
    const video = videoRef.current
    if (!video) return

    setPreviewing(true)
    const result = video.play()
    if (result && typeof result.catch === 'function') {
      result.catch(() => undefined)
    }
  }

  function stopPreview() {
    const video = videoRef.current
    if (!video) return

    setPreviewing(false)
    video.pause()
    video.currentTime = 0
  }

  return (
    <div
      className={['relative aspect-[3/2] overflow-hidden bg-black', className].join(' ')}
      onMouseEnter={playPreview}
      onMouseLeave={stopPreview}
      onFocus={playPreview}
      onBlur={stopPreview}
    >
      {imageSrc ? (
        <>
          <img src={imageSrc} alt="" className="absolute inset-0 h-full w-full scale-110 object-cover opacity-45 blur-xl" loading="lazy" aria-hidden="true" />
          <img src={imageSrc} alt={alt} className={['relative z-10 h-full w-full transition-opacity duration-300', imageClassName].join(' ')} loading="lazy" />
        </>
      ) : null}
      {previewSrc ? (
        <video
          ref={videoRef}
          src={previewSrc}
          className={[
            'absolute inset-0 z-20 h-full w-full object-cover transition-opacity duration-300',
            previewing || !imageSrc ? 'opacity-100' : 'opacity-0',
          ].join(' ')}
          muted
          loop
          playsInline
          preload={imageSrc ? 'none' : 'metadata'}
        />
      ) : null}
    </div>
  )
}

function isPlaceholderImage(src?: string | null) {
  if (!src) return false

  const normalized = src.toLowerCase()

  return normalized.includes('/default-image/') || normalized.includes('default-image.jpg')
}
