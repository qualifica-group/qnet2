import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { formatEuro } from '@/features/invoices/invoice-format'

interface OverviewFigureProps {
  label: ReactNode
  amount: string
  to: string
  strong?: boolean
}

/** One labelled amount that links to the list composing it. */
export function OverviewFigure({ label, amount, to, strong = false }: OverviewFigureProps) {
  return (
    <div className="flex items-baseline justify-between gap-3 text-sm">
      <span className={cn('min-w-0 truncate', strong ? 'font-medium' : 'text-muted-foreground')}>{label}</span>
      <Link
        to={to}
        className={cn('shrink-0 tabular-nums underline-offset-2 hover:underline', strong && 'font-semibold')}
      >
        {formatEuro(amount)}
      </Link>
    </div>
  )
}
