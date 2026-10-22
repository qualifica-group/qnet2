import { Check, Clock } from 'lucide-react'
import type { AbsenceCalendarEntry } from '@/features/absences/types'

/** Approved = solid fill with a check, pending = faded with a dashed edge and a clock: the state never rests on colour alone. */
export const CALENDAR_STATUS_STYLE: Record<AbsenceCalendarEntry['status'], string> = {
  approved: '',
  submitted: 'opacity-60 outline-dashed outline-1 -outline-offset-2 outline-foreground',
}

export const CALENDAR_STATUS_ICON = { approved: Check, submitted: Clock } as const
