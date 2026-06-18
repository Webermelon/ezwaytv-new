import { MouseEvent, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Check, Loader2, Plus } from 'lucide-react'

import { addToWatchlist, removeFromWatchlist } from '@/modules/account/watchlistApi'

type WatchlistToggleButtonProps = {
  entertainmentId?: string | number | null
  type?: string | null
  initialInWatchlist?: boolean | number | string | null
  className?: string
}

export function WatchlistToggleButton({
  entertainmentId,
  type = 'video',
  initialInWatchlist,
  className = '',
}: WatchlistToggleButtonProps) {
  const queryClient = useQueryClient()
  const [inWatchlist, setInWatchlist] = useState(toBoolean(initialInWatchlist))
  const [error, setError] = useState('')

  const mutation = useMutation({
    mutationFn: async () => {
      if (!window.isAuthenticated) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`
        return ''
      }

      if (!entertainmentId || !type) {
        throw new Error('This item cannot be added to your watchlist.')
      }

      return inWatchlist
        ? removeFromWatchlist(entertainmentId, type)
        : addToWatchlist(entertainmentId, type)
    },
    onSuccess: () => {
      setInWatchlist((current) => !current)
      setError('')
      queryClient.invalidateQueries({ queryKey: ['watchlist'] })
    },
    onError: (mutationError) => {
      setError(mutationError instanceof Error ? mutationError.message : 'Watchlist update failed.')
      window.setTimeout(() => setError(''), 2400)
    },
  })

  function handleClick(event: MouseEvent<HTMLButtonElement>) {
    event.preventDefault()
    event.stopPropagation()
    mutation.mutate()
  }

  return (
    <span className="relative inline-flex">
      <button
        type="button"
        onClick={handleClick}
        disabled={mutation.isPending}
        aria-label={inWatchlist ? 'Remove from watchlist' : 'Add to watchlist'}
        title={inWatchlist ? 'Remove from watchlist' : 'Add to watchlist'}
        className={[
          'flex h-10 w-10 items-center justify-center rounded-full border backdrop-blur transition',
          inWatchlist
            ? 'border-[#d4a843]/55 bg-[#edc342] text-black hover:bg-[#f4ce4d]'
            : 'border-white/18 bg-black/62 text-white hover:border-[#d4a843]/55 hover:bg-[#d4a843]/18 hover:text-[#edc342]',
          mutation.isPending ? 'cursor-wait opacity-80' : '',
          className,
        ].join(' ')}
      >
        {mutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : inWatchlist ? <Check className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
      </button>
      {error ? (
        <span className="pointer-events-none absolute right-0 top-full z-20 mt-2 w-48 rounded-md border border-red-400/20 bg-red-500/90 px-3 py-2 text-xs font-bold text-white shadow-xl">
          {error}
        </span>
      ) : null}
    </span>
  )
}

function toBoolean(value: unknown) {
  return value === true || value === 1 || value === '1' || value === 'true'
}
