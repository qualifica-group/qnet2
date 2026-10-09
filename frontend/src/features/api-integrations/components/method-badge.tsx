import { cn } from '@/lib/utils'
import type { ApiMethod } from '@/features/api-integrations/openapi-operations'

/**
 * Tinted pill per method. The text mixes the token toward `--foreground` so
 * it keeps AA contrast on the 15% tint in both themes (the raw status tokens
 * sit around 3.5:1 on it in light).
 */
const METHOD_CLASSES: Record<ApiMethod, string> = {
  GET: 'bg-success/15 text-[color-mix(in_srgb,var(--success)_55%,var(--foreground))]',
  POST: 'bg-primary/15 text-[color-mix(in_srgb,var(--primary)_75%,var(--foreground))]',
  PUT: 'bg-warning/15 text-[color-mix(in_srgb,var(--warning)_55%,var(--foreground))]',
  PATCH: 'bg-warning/15 text-[color-mix(in_srgb,var(--warning)_55%,var(--foreground))]',
  DELETE: 'bg-destructive/15 text-[color-mix(in_srgb,var(--destructive)_55%,var(--foreground))]',
}

interface MethodBadgeProps {
  method: ApiMethod
  className?: string
}

/** Fixed-width method pill so the paths of a list line up. */
export function MethodBadge({ method, className }: MethodBadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex w-14 shrink-0 items-center justify-center rounded-md py-0.5 font-mono text-[11px] font-semibold',
        METHOD_CLASSES[method],
        className,
      )}
    >
      {method}
    </span>
  )
}
