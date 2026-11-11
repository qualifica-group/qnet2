import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

interface IconCountBadgeProps extends Omit<ComponentProps<'span'>, 'children'> {
  count: number
}

/**
 * Small numeric badge overlaid on an icon's top-right corner (the host must be
 * `relative`): the count of a grid row action (documents, notes) or of a
 * record's collaboration tab. Purely presentational: the caller decides
 * whether there is anything to show, and whether the count is already in an
 * accessible label (then `aria-hidden`).
 */
export function IconCountBadge({ count, className, ...props }: IconCountBadgeProps) {
  return (
    <span
      data-slot="icon-count-badge"
      className={cn(
        'pointer-events-none absolute -top-px -right-px flex h-2.5 min-w-2.5 items-center justify-center rounded-full bg-primary px-0.5 text-[7px] leading-none text-primary-foreground tabular-nums',
        className,
      )}
      {...props}
    >
      {count}
    </span>
  )
}
