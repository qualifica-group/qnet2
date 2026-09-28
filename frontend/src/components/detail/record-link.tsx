import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { useRecordModalLink } from '@/features/modules/use-record-modal-link'
import { useViewableDomain } from '@/features/modules/use-viewable-domain'

/**
 * A cross-record link inside a detail/record surface. A plain click opens the
 * target record in a MODAL (`useRecordModalLink`), whose toolbar then leads to
 * the record's dedicated page; it stays a real `<Link>` to the target module's
 * route, so new tab, copy and keyboard reach keep working. People are NOT
 * linked this way: they keep `UserProfileHoverCard` (hover card + the shared
 * user Sheet).
 *
 * The path is resolved from the module registry rather than written by hand, so
 * a module that moves its `basePath` moves every link to it at once. The target
 * module is the first one the actor may view (`useViewableDomain`: an Offerta
 * falls back to Gestione Richieste); with none, the value degrades to plain
 * text instead of a link to a record the server would refuse.
 */

interface RecordLinkProps {
  /** Module registry domain slug of the TARGET record, e.g. `'campaigns'`. */
  domain: string
  id: number
  children: ReactNode
  className?: string
}

export function RecordLink({ domain, id, children, className }: RecordLinkProps) {
  const targetDomain = useViewableDomain(domain)
  const basePath = targetDomain === null ? undefined : getModuleRegistryEntry(targetDomain)?.basePath

  if (targetDomain === null || basePath === undefined) {
    return <>{children}</>
  }

  return (
    <ModalRecordLink domain={targetDomain} id={id} path={`${basePath}/${id}`} className={className}>
      {children}
    </ModalRecordLink>
  )
}

/** Split out so the modal hook (which requires a registered domain) only mounts once the lookup succeeded. */
function ModalRecordLink({ domain, id, path, children, className }: RecordLinkProps & { path: string }) {
  const { onClick, sheet } = useRecordModalLink(domain, id)

  return (
    <>
      <Link
        to={path}
        onClick={onClick}
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
      {sheet}
    </>
  )
}
