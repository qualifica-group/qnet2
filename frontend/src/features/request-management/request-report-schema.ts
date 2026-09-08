import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { RequestReportCategory, RequestReportRowMode } from '@/features/request-management/report-api'

/** Display order of the three row-mode options (rev-2 D-13, AC-052). */
export const ROW_MODES: readonly RequestReportRowMode[] = ['total_only', 'operators_only', 'all']

/** `YYYY-MM-DD`, the wire format `POST /request-management/report` expects (spec 0106). */
export function buildRequestReportSchema(t: TFunction) {
  return z
    .object({
      date_from: z.string().min(1, t('requestManagement.report.errors.dateFromRequired')),
      date_to: z.string().min(1, t('requestManagement.report.errors.dateToRequired')),
      // rev-2 D-11: at least one branch, deselecting all is a client error,
      // never a request left for the server to 422 (AC-051).
      category_keys: z.array(z.string()).min(1, t('requestManagement.report.errors.categoriesRequired')),
      row_mode: z.enum(['total_only', 'operators_only', 'all']),
    })
    .refine((value) => value.date_from === '' || value.date_to === '' || value.date_to >= value.date_from, {
      message: t('requestManagement.report.errors.dateToBeforeDateFrom'),
      path: ['date_to'],
    })
}

export type RequestReportFormValues = z.infer<ReturnType<typeof buildRequestReportSchema>>

/** Zero-pads a date component to two digits (`9` -> `"09"`). */
function pad2(value: number): string {
  return String(value).padStart(2, '0')
}

/**
 * `YYYY-MM-DD` built from LOCAL date components — never `toISOString()`
 * (rev-2 AC-057): that converts to UTC first, which east of Greenwich shifts
 * the calendar day backward (23:30 local can read as the next UTC day, and
 * just after local midnight can read as the PREVIOUS UTC day).
 */
function toLocalIsoDate(date: Date): string {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
}

/**
 * Monday of `date`'s week (rev-2 AC-056). The week starts Monday: `getDay()`
 * returns `0` for Sunday, so the correct back-to-Monday offset is
 * `(getDay() + 6) % 7` — NOT `getDay() - 1`, which on a Sunday (`0 - 1 = -1`)
 * would land six days in the wrong direction instead of going back one day.
 */
function mondayOf(date: Date): Date {
  const monday = new Date(date)
  monday.setDate(date.getDate() - ((date.getDay() + 6) % 7))
  return monday
}

/**
 * Current week's Monday/Friday as local `YYYY-MM-DD` strings (rev-2
 * AC-056/AC-057). On a Saturday/Sunday the proposed Friday falls in the
 * past — intentional, not a bug (the week already ended).
 */
export function currentWeekReportRange(now: Date = new Date()): { date_from: string; date_to: string } {
  const monday = mondayOf(now)
  const friday = new Date(monday)
  friday.setDate(monday.getDate() + 4)
  return { date_from: toLocalIsoDate(monday), date_to: toLocalIsoDate(friday) }
}

/**
 * `categoryKeys` seeds the checkbox group: every branch selected by default
 * (rev-2 D-11, AC-050), an empty array before the branch list has loaded.
 * `date_from`/`date_to` default to the current week's Monday/Friday (rev-2
 * AC-056) — both stay freely editable, this is a default, not a constraint.
 */
export function requestReportDefaultValues(categoryKeys: string[] = []): RequestReportFormValues {
  return { ...currentWeekReportRange(), category_keys: categoryKeys, row_mode: 'all' }
}

/**
 * True once the branch query has settled successfully on an empty list
 * (rev-2 AC-053). Exported so every consumer (the CSV dialog and the
 * dashboard's filter bar, spec 0107 D-4) derives the SAME empty state from
 * the SAME query result, instead of two slightly different checks drifting
 * apart. Kept here, not in `request-report-filters.tsx`, because that file
 * exports only the `RequestReportFilters` component (react-refresh boundary).
 */
export function isCategoriesEmpty(
  categories: RequestReportCategory[] | undefined,
  categoriesLoading: boolean,
  categoriesError: boolean,
): boolean {
  return !categoriesLoading && !categoriesError && categories !== undefined && categories.length === 0
}

/** True while the branch list cannot drive the form: still loading, failed, or resolved empty. */
export function categoriesAreBlocked(
  categoriesLoading: boolean,
  categoriesError: boolean,
  categoriesEmpty: boolean,
): boolean {
  return categoriesLoading || categoriesError || categoriesEmpty
}

/**
 * Same rules `buildRequestReportSchema` enforces, computed SYNCHRONOUSLY
 * from a plain set of values (spec 0107 D-5/AC-044). The dashboard holds its
 * applied filters as state, outside any form, and gates the aggregates query
 * on this — never on RHF's `formState.isValid`, which belongs to the sheet
 * and lags the values it validates by a render (the resolver is async): an
 * `enabled: isValid` query could still fire with an empty `category_keys`,
 * exactly the empty-selection request AC-044 forbids.
 */
export function isRequestReportQueryReady(values: RequestReportFormValues): boolean {
  return (
    values.date_from !== '' &&
    values.date_to !== '' &&
    values.date_to >= values.date_from &&
    values.category_keys.length > 0
  )
}
