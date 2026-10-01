import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'
import type { HealthStatus } from '@/features/system-health/types'

const dotVariants = cva('inline-block size-2 shrink-0 rounded-full', {
  variants: {
    status: {
      ok: 'bg-success',
      degraded: 'bg-warning',
      down: 'bg-destructive',
    },
  },
})

interface HealthStatusIndicatorProps {
  status: HealthStatus
  /** Localized status word, always visible: state is never conveyed by colour alone. */
  statusLabel: string
  className?: string
}

/** Colour dot followed by the localized status text. */
export function HealthStatusIndicator({
  status,
  statusLabel,
  className,
}: HealthStatusIndicatorProps) {
  return (
    <span className={cn('inline-flex items-center gap-1.5 text-xs font-medium', className)}>
      <span aria-hidden="true" className={dotVariants({ status })} />
      {statusLabel}
    </span>
  )
}
