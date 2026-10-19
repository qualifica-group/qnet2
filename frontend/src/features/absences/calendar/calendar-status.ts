import { Check, Clock } from 'lucide-react'
import type { AbsenceCalendarEntry } from '@/features/absences/types'

/** Approved = filled with a check, pending = dashed with a clock: the state never rests on colour alone. */
export const CALENDAR_STATUS_CLASS: Record<AbsenceCalendarEntry['status'], string> = {
  approved: 'bg-primary text-primary-foreground',
  submitted: 'border border-dashed border-warning bg-card text-warning',
}

export const CALENDAR_STATUS_ICON = { approved: Check, submitted: Clock } as const
