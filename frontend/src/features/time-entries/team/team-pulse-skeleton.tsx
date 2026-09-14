/**
 * Loading placeholder of the team view card (spec 0122 D-10), q-net parity
 * (`team-pulse/team-pulse-skeleton.tsx`).
 */

import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

const SKELETON_ROWS = 5

export function TeamPulseSkeleton() {
  return (
    <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
      <div className="flex flex-col gap-3 border-b border-border/60 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2">
          <Skeleton className="size-4 rounded" />
          <Skeleton className="h-4 w-20" />
          <Skeleton className="h-5 w-10 rounded-full" />
        </div>
        <Skeleton className="h-9 w-full rounded-md sm:w-72" />
      </div>
      <div className="space-y-1 p-4">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <div className="flex items-center gap-3 py-2" key={index}>
            <Skeleton className="size-8 rounded-full" />
            <div className="flex-1 space-y-1.5">
              <Skeleton className="h-4 w-48" />
              <Skeleton className="h-3 w-32" />
            </div>
            <Skeleton className="hidden h-3 w-24 sm:block" />
          </div>
        ))}
      </div>
    </Card>
  )
}
