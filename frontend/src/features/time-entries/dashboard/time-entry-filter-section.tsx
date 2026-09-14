/**
 * One collapsible filter section of the drawer (spec 0122 D-13), mirroring
 * q-net's `FilterSection`: icon + title + active-count badge in the trigger,
 * body height-animated via `Collapsible` (same primitive `FormSection` uses).
 */

import type { ReactNode } from 'react'
import { ChevronDown, type LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'

interface TimeEntryFilterSectionProps {
  icon: LucideIcon
  title: string
  activeCount: number
  defaultOpen?: boolean
  children: ReactNode
}

export function TimeEntryFilterSection({
  icon: Icon,
  title,
  activeCount,
  defaultOpen = false,
  children,
}: TimeEntryFilterSectionProps) {
  return (
    <Collapsible defaultOpen={defaultOpen || activeCount > 0}>
      <CollapsibleTrigger className="group flex w-full items-center gap-2 rounded-md px-1 py-1.5 text-left outline-none hover:bg-muted/60 focus-visible:ring-[2px] focus-visible:ring-ring/50">
        <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{title}</span>
        {activeCount > 0 ? (
          <Badge variant="secondary" className="h-5 min-h-5 px-1.5">
            {activeCount}
          </Badge>
        ) : null}
        <ChevronDown
          className="size-4 shrink-0 text-muted-foreground transition-transform motion-safe:duration-200 group-data-[state=open]:rotate-180"
          aria-hidden="true"
        />
      </CollapsibleTrigger>
      <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down motion-reduce:animate-none">
        <div className="px-1 pt-2 pb-1">{children}</div>
      </CollapsibleContent>
    </Collapsible>
  )
}
