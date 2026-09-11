import { Skeleton } from '@/components/ui/skeleton'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'

/** Placeholder section count: enough to fill the fold, never a promise about the real form. */
const PLACEHOLDER_SECTIONS = [0, 1, 2]

/**
 * Loading placeholder mirroring a record form's real layout — identity bar plus
 * the two-column body — so the swap to the loaded form does not shift the
 * panel.
 *
 * Shared rather than copied per module: the whole point of the record-form kit
 * is that the screens cannot drift apart, and a skeleton that no longer matches
 * the layout it stands in for is worse than no skeleton at all.
 */
export function RecordFormSkeleton() {
  return (
    <div className="@container flex flex-1 flex-col bg-surface" aria-hidden="true">
      <div className="flex items-center gap-3 border-b bg-card px-4 py-3">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-5 w-24" />
        <Skeleton className="ml-auto h-8 w-20" />
      </div>
      <div className={PANEL_GRID_CLASS}>
        <div className={MAIN_COLUMN_CLASS}>
          {PLACEHOLDER_SECTIONS.map((section) => (
            <div key={section} className="rounded-xl border bg-card p-4 shadow-sm">
              <Skeleton className="h-3.5 w-40" />
              <Skeleton className="mt-4 h-9 w-full" />
            </div>
          ))}
        </div>
        <div className={SIDE_COLUMN_CLASS}>
          <div className="rounded-xl border bg-card p-4 shadow-sm">
            <Skeleton className="h-3.5 w-32" />
            <Skeleton className="mt-4 h-24 w-full" />
          </div>
        </div>
      </div>
    </div>
  )
}
