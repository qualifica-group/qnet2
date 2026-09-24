/**
 * ISO-8601 weekday order (Monday=1..Sunday=7), shared by the weekly checkbox
 * field, the ordinal-weekday picker (`task-recurrence-section.tsx`) and the
 * detail sentence formatter (`task-recurrence-format.ts`). Split out of the
 * checkbox field's own component file: a `.tsx` file may only export
 * components (`react-refresh/only-export-components`).
 */
export const WEEKDAY_ORDER = [1, 2, 3, 4, 5, 6, 7] as const
export const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const
