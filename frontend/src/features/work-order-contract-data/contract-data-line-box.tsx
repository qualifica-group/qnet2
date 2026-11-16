import type { ReactNode } from 'react'

interface ContractDataLineBoxProps {
  /** Accessible name of the product block (code and name). */
  label: string | undefined
  children: ReactNode
}

/**
 * The tinted box of one product of the commessa (spec 0403, user directive
 * 2026-11-16: each product must read as a separate block). A `bg-muted` veil
 * over the hosting card, not a surface rung (ui-design.md §1-bis).
 */
export function ContractDataLineBox({ label, children }: ContractDataLineBoxProps) {
  return (
    <div role="group" aria-label={label} className="rounded-lg bg-muted/40 px-3">
      {children}
    </div>
  )
}
