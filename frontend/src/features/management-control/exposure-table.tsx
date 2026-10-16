import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

export const HEAD_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
export const NUMERIC_HEAD_CELL = 'px-2 py-1.5 text-right text-xs font-medium text-muted-foreground'
export const CELL = 'px-2 py-1.5 text-sm'
export const NUMERIC_CELL = 'px-2 py-1.5 text-right text-sm'

/** Scrollable, keyboard-focusable table frame shared by the blocks of the exposure page. */
export function ExposureTable({ caption, children }: { caption: string; children: ReactNode }) {
  return (
    <div
      tabIndex={0}
      role="region"
      aria-label={caption}
      className="min-w-0 overflow-auto rounded-lg border bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{caption}</caption>
        {children}
      </table>
    </div>
  )
}

/** Name of a customer or supplier, linking to its ledger (Partitario). */
export function RegistryLink({ registry }: { registry: { id: number; name: string } }) {
  return (
    <Link
      to={`/ledger?registry=${registry.id}`}
      title={registry.name}
      className="block max-w-56 truncate text-primary underline-offset-2 underline decoration-primary/40 hover:decoration-primary rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      {registry.name}
    </Link>
  )
}

/** Block with a heading and its content, labelled for assistive technology. */
export function ExposureSection({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <section aria-label={title} className="flex min-w-0 flex-col gap-2">
      <h3 className="text-base font-semibold">{title}</h3>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      {children}
    </section>
  )
}
