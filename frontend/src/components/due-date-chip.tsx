import { AlertTriangle, CalendarClock } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'

/** Where a due date stands: passed on an open record, falling today, or neither. */
export type DueState = 'overdue' | 'today' | null

const CHIP_CLASS = 'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums'
const OVERDUE_CLASS = 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/30'
const TODAY_CLASS = 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20'

interface DueDateChipProps {
  /** The already formatted date. */
  date: string
  state: Exclude<DueState, null>
  /** The state's word ("Scaduto", "Oggi"): tooltip and accessible name. */
  label: string
}

/**
 * A due date that needs attention (user directive 2026-09-22, the commessa
 * Task board): a red chip with the alert triangle once it has passed, a tinted
 * one with the calendar when it falls today. Never colour-only: each state has
 * its own icon and an accessible name carrying the word. Shared by the
 * commessa board, the Task Kanban, the Task grid and the Task detail; it
 * brings its own `TooltipProvider`, like the app's other tooltip badges, so
 * it works in any host.
 */
export function DueDateChip({ date, state, label }: DueDateChipProps) {
  const overdue = state === 'overdue'
  const Icon = overdue ? AlertTriangle : CalendarClock

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <span
            tabIndex={0}
            aria-label={`${date}, ${label}`}
            className={cn(CHIP_CLASS, 'outline-none focus-visible:ring-2', overdue ? OVERDUE_CLASS : TODAY_CLASS)}
          >
            <Icon className="size-3 shrink-0" aria-hidden="true" />
            {date}
          </span>
        </TooltipTrigger>
        <TooltipContent>{label}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
