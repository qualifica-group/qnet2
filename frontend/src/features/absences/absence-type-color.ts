import type { AbsenceTypeColor } from '@/features/absences/types'

/** Fill of the colour dot of an absence type: the palette of `#[Color]` of the backend. The type name always travels with it. */
const DOT_CLASS: Record<AbsenceTypeColor, string> = {
  slate: 'bg-slate-500',
  gray: 'bg-gray-500',
  amber: 'bg-amber-500',
  blue: 'bg-blue-500',
  emerald: 'bg-emerald-500',
  green: 'bg-green-500',
  red: 'bg-red-500',
  violet: 'bg-violet-500',
}

export function absenceTypeDotClass(color: AbsenceTypeColor): string {
  return DOT_CLASS[color]
}
