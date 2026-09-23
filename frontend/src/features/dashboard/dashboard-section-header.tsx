import type { ReactNode } from 'react'

interface DashboardSectionHeaderProps {
  icon: ReactNode
  title: string
  /** Wires the header to the section's `aria-labelledby`. */
  id?: string
}

/**
 * "Icon in a box + title" header shared by every dashboard block (spec 0151
 * D-9), mirroring q-net's section headers 1:1 with qnet-2 tokens: the icon box
 * reuses the exact classes of `StatCard`'s own icon slot for visual
 * consistency with the statistics panels it sits above.
 */
export function DashboardSectionHeader({ icon, title, id }: DashboardSectionHeaderProps) {
  return (
    <div className="flex items-center gap-3 px-1 py-1">
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted text-muted-foreground [&_svg]:size-4">
        {icon}
      </span>
      <h2 id={id} className="text-sm font-medium">
        {title}
      </h2>
    </div>
  )
}
