import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'

/**
 * A cross-record link inside a detail/record surface: the SAME idiom
 * `existing-opportunity-alert.tsx` uses for a non-person record — a real
 * `<Link>` to the target module's own route, so the record is openable in a new
 * tab, copyable and reachable by keyboard for free. People are NOT linked this
 * way: they keep `UserProfileHoverCard` (hover card + the shared user Sheet),
 * the other half of the idiom Opportunita' already applies.
 *
 * The path is resolved from the module registry rather than written by hand, so
 * a module that moves its `basePath` moves every link to it at once. An
 * unregistered domain degrades to plain text instead of a dead link.
 */

interface RecordLinkProps {
  /** Module registry domain slug of the TARGET record, e.g. `'campaigns'`. */
  domain: string
  id: number
  children: ReactNode
  className?: string
}

export function RecordLink({ domain, id, children, className }: RecordLinkProps) {
  const basePath = getModuleRegistryEntry(domain)?.basePath

  if (basePath === undefined) {
    return <>{children}</>
  }

  return (
    <Link
      to={`${basePath}/${id}`}
      className={cn(
        'group inline-flex max-w-full items-center gap-1 rounded-sm text-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring',
        className,
      )}
    >
      <span className="truncate">{children}</span>
      <ArrowUpRight
        aria-hidden="true"
        className="size-3.5 shrink-0 text-muted-foreground transition-opacity group-hover:text-foreground"
      />
    </Link>
  )
}
