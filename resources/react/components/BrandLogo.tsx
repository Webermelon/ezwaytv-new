import { useEffect, useState } from 'react'

import { useBranding } from '@/lib/branding'

type BrandLogoProps = {
  className?: string
  imageClassName?: string
  textClassName?: string
}

export function BrandLogo({
  className = '',
  imageClassName = '',
  textClassName = '',
}: BrandLogoProps) {
  const { appName, logo } = useBranding()
  const [imageFailed, setImageFailed] = useState(false)

  useEffect(() => {
    setImageFailed(false)
  }, [logo])

  return (
    <a className={`inline-flex h-10 shrink-0 items-center ${className}`} href="/" aria-label={appName}>
      {logo && !imageFailed ? (
        <img
          src={logo}
          alt={appName}
          className={`max-h-10 w-auto max-w-[170px] object-contain ${imageClassName}`}
          onError={() => setImageFailed(true)}
        />
      ) : (
        <span className={`text-2xl font-black uppercase text-primary ${textClassName}`}>{appName}</span>
      )}
    </a>
  )
}
