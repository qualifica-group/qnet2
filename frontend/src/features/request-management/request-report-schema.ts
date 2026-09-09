import { z } from 'zod'
import type { TFunction } from 'i18next'
import type {
  RequestReportCategory,
  RequestReportFilterPayload,
  RequestReportRowMode,
} from '@/features/request-management/report-api'

/** Display order of the three row-mode options (rev-2 D-13, AC-052). */
export const ROW_MODES: readonly RequestReportRowMode[] = ['total_only', 'operators_only', 'all']

/**
 * True when a key group's selection is a REQUIREMENT for these values: only
 * for the row modes that actually emit operator rows (spec 0109 D-4, spec
 * 0112 D-7), and only once the picker has options to offer — with an empty
 * list there is nothing to deselect, so an empty selection is "all of
 * nothing", not an error the user could ever clear. Governs BOTH narrowing
 * groups, operators and sites: the two hide and become required together, so
 * one predicate is what keeps them from drifting apart.
 */
function selectionIsRequired(rowMode: RequestReportRowMode, availableKeys: string[]): boolean {
  return rowMode !== 'total_only' && availableKeys.length > 0
}

/**
 * What one narrowing group contributes to the wire (spec 0109 D-9, spec 0112
 * D-4): `undefined` — the field DROPPED, never sent empty — in the three
 * cases that all mean "everything", and the raw selection otherwise.
 * Dropping it on a full selection is deliberate: an explicit list would
 * freeze the roster at the moment the panel loaded, and an entry added later
 * would silently vanish from the report.
 */
function narrowedSelection(
  rowMode: RequestReportRowMode,
  selected: string[],
  availableKeys: string[],
): string[] | undefined {
  const isEverything = availableKeys.length > 0 && availableKeys.every((key) => selected.includes(key))

  if (!selectionIsRequired(rowMode, availableKeys) || isEverything) {
    return undefined
  }

  return selected
}

/**
 * `YYYY-MM-DD`, the wire format `POST /request-management/report` expects
 * (spec 0106). `availableOperatorKeys` (spec 0109) and `availableSiteKeys`
 * (spec 0112) are what the two pickers currently offer: passing them is what
 * turns "at least one operator"/"at least one site" into real rules, and
 * their default of `[]` keeps the schema usable (and the rules inert)
 * wherever a list is irrelevant.
 */
export function buildRequestReportSchema(
  t: TFunction,
  availableOperatorKeys: string[] = [],
  availableSiteKeys: string[] = [],
) {
  return z
    .object({
      date_from: z.string().min(1, t('requestManagement.report.errors.dateFromRequired')),
      date_to: z.string().min(1, t('requestManagement.report.errors.dateToRequired')),
      // rev-2 D-11: at least one branch, deselecting all is a client error,
      // never a request left for the server to 422 (AC-051).
      category_keys: z.array(z.string()).min(1, t('requestManagement.report.errors.categoriesRequired')),
      row_mode: z.enum(['total_only', 'operators_only', 'all']),
      operator_keys: z.array(z.string()),
      site_keys: z.array(z.string()),
    })
    .refine((value) => value.date_from === '' || value.date_to === '' || value.date_to >= value.date_from, {
      message: t('requestManagement.report.errors.dateToBeforeDateFrom'),
      path: ['date_to'],
    })
    .refine(
      (value) => !selectionIsRequired(value.row_mode, availableOperatorKeys) || value.operator_keys.length > 0,
      {
        message: t('requestManagement.report.errors.operatorsRequired'),
        path: ['operator_keys'],
      },
    )
    .refine((value) => !selectionIsRequired(value.row_mode, availableSiteKeys) || value.site_keys.length > 0, {
      message: t('requestManagement.report.errors.sitesRequired'),
      path: ['site_keys'],
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
export function requestReportDefaultValues(
  categoryKeys: string[] = [],
  operatorKeys: string[] = [],
  siteKeys: string[] = [],
): RequestReportFormValues {
  return {
    ...currentWeekReportRange(),
    category_keys: categoryKeys,
    row_mode: 'all',
    operator_keys: operatorKeys,
    site_keys: siteKeys,
  }
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
export function isRequestReportQueryReady(
  values: RequestReportFormValues,
  availableOperatorKeys: string[] = [],
  availableSiteKeys: string[] = [],
): boolean {
  return (
    values.date_from !== '' &&
    values.date_to !== '' &&
    values.date_to >= values.date_from &&
    values.category_keys.length > 0 &&
    (!selectionIsRequired(values.row_mode, availableOperatorKeys) || values.operator_keys.length > 0) &&
    (!selectionIsRequired(values.row_mode, availableSiteKeys) || values.site_keys.length > 0)
  )
}

/**
 * The ONE place that decides what the two narrowing fields look like on the
 * wire (spec 0109 D-9, spec 0112 D-4), used by BOTH consumers — the dashboard
 * query (and its cache key) and the report file — so the charts and the CSV
 * can never disagree about the very filters meant to unite them.
 *
 * The two axes are INDEPENDENT (spec 0112 D-10): each is dropped or sent on
 * its own list alone, so a narrowed operator selection travels next to "every
 * site" without either one widening the other.
 */
export function toRequestReportFilterPayload(
  values: RequestReportFormValues,
  availableOperatorKeys: string[],
  availableSiteKeys: string[],
): RequestReportFilterPayload {
  const { operator_keys: operators, site_keys: sites, ...filters } = values

  const operatorKeys = narrowedSelection(values.row_mode, operators, availableOperatorKeys)
  const siteKeys = narrowedSelection(values.row_mode, sites, availableSiteKeys)

  return {
    ...filters,
    ...(operatorKeys ? { operator_keys: operatorKeys } : {}),
    ...(siteKeys ? { site_keys: siteKeys } : {}),
  }
}
