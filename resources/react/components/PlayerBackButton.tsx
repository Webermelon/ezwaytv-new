import { ArrowLeft } from 'lucide-react'

type PlayerBackButtonProps = {
  fallbackHref: string
  className?: string
}

export function PlayerBackButton({ fallbackHref, className = '' }: PlayerBackButtonProps) {
  function handleBack() {
    if (window.history.length > 1) {
      window.history.back()
      return
    }

    window.location.href = fallbackHref
  }

  return (
    <button
      type="button"
      onClick={handleBack}
      className={[
        'inline-flex items-center gap-2 rounded-sm border border-white/15 bg-black/60 px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-white shadow-lg backdrop-blur-md transition hover:border-white/35 hover:bg-black/80',
        className,
      ].join(' ')}
      aria-label="Go back"
    >
      <ArrowLeft className="h-4 w-4" />
      Back
    </button>
  )
}
