import { useEffect, useState } from 'react'

import { useBranding } from '@/lib/branding'

const fallbackLogo = 'https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/ezwaytv_white_6a26f75c71a3d.png'

type BrandLogoProps = {
  className?: string
  imageClassName?: string
  textClassName?: string
  placeholderClassName?: string
}

export function BrandLogo({
  className = '',
  imageClassName = '',
  textClassName = '',
  placeholderClassName = '',
}: BrandLogoProps) {
  const { appName, logo, loading } = useBranding()
  const displayLogo = logo ?? fallbackLogo
  const [imageFailed, setImageFailed] = useState(false)

  useEffect(() => {
    setImageFailed(false)
  }, [displayLogo])

  return (
    <a className={`inline-flex h-10 shrink-0 items-center ${className}`} href="/" aria-label={appName}>
      {displayLogo && !imageFailed ? (
        <img
          src={displayLogo}
          alt={appName}
          className={`max-h-10 w-auto max-w-[170px] object-contain ${imageClassName}`}
          onError={() => setImageFailed(true)}
        />
      ) : loading ? (
        <span
          className={`block h-9 w-[170px] animate-pulse rounded-sm bg-[linear-gradient(90deg,rgba(212,168,67,0.16),rgba(255,255,255,0.08),rgba(212,168,67,0.16))] ${placeholderClassName}`}
          aria-hidden="true"
        />
      ) : (
        <span className={`text-2xl font-black uppercase ${loading ? 'text-white' : 'text-primary'} ${textClassName}`}>{appName}</span>
      )}
    </a>
  )
}
