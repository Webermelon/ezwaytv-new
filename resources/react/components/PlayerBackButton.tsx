import { ArrowLeft } from 'lucide-react'

type PlayerBackButtonProps = {
  fallbackHref: string
  className?: string
  label?: string
  variant?: 'player' | 'inline'
}

export function PlayerBackButton({ fallbackHref, className = '', label = 'Back', variant = 'player' }: PlayerBackButtonProps) {
  function handleBack() {
    window.location.href = fallbackHref
  }

  return (
    <button
      type="button"
      onClick={handleBack}
      className={[
        variant === 'inline'
          ? 'inline-flex items-center gap-2 py-1 text-sm font-semibold text-white/75 transition hover:text-primary'
          : 'inline-flex items-center gap-2 rounded-sm border border-white/15 bg-black/60 px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-white shadow-lg backdrop-blur-md transition hover:border-white/35 hover:bg-black/80',
        className,
      ].join(' ')}
      aria-label="Go back"
    >
      <ArrowLeft className="h-4 w-4" />
      {label}
    </button>
  )
}
