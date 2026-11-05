import type { ReactNode } from 'react'

interface OverviewCardProps {
  title: string
  children: ReactNode
}

/** Card surface of one block of the overview. */
export function OverviewCard({ title, children }: OverviewCardProps) {
  return (
    <section aria-label={title} className="flex min-w-0 flex-col gap-2 rounded-lg border bg-card p-3 shadow-sm">
      <h2 className="text-sm font-semibold">{title}</h2>
      {children}
    </section>
  )
}
