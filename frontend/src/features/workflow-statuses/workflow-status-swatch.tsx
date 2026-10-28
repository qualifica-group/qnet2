import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'

/** A working-status option's leading color dot, shared by the selects, the pills and the read-only summaries. */
export function WorkflowStatusSwatch({ color }: { color: string | null }) {
  return (
    <span
      className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(color) ?? 'bg-transparent')}
      aria-hidden="true"
    />
  )
}
