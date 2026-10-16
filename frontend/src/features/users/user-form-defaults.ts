import { useMemo } from 'react'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { CompetenceLineRow } from '@/features/product-lines/types'
import type { EmploymentProductLine, UserDetail, UserFormMode, UserLocale } from '@/features/users/types'
import type { EmploymentFormValues } from '@/features/users/user-schema'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * The user form's seed values: what RHF opens on (create defaults / the
 * persisted user on the in-place detail, spec 0222). Split out of
 * `use-user-form.ts` to keep that hook small.
 */

/** Locale is not user-editable on the form; new users default to Italian. */
const DEFAULT_LOCALE: UserLocale = 'it'

/**
 * Stable module-level default for the remote-sites array field: an inline
 * `[]` would create a new reference on every render and break memoized
 * dependents (rule engineering.md §1 / frontend.md §10).
 */
const EMPTY_REMOTE_SITE_IDS: number[] = []

/** Same stable-reference reasoning for the reports-to id array (spec 0166). */
const EMPTY_REPORTS_TO_IDS: number[] = []

const EMPTY_JOB_ROLE_IDS: number[] = []

/** Same stable-reference reasoning for the competence row field (spec 0111). */
const EMPTY_PRODUCT_LINES: CompetenceLineRow[] = []

/** A blank employment sub-form, used for both create and an edit user with no profile yet. */
export const EMPTY_EMPLOYMENT: EmploymentFormValues = {
  is_manager: false,
  job_role_ids: EMPTY_JOB_ROLE_IDS,
  reports_to_ids: EMPTY_REPORTS_TO_IDS,
  relationship_type: null,
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: EMPTY_REMOTE_SITE_IDS,
  is_assignable: true,
  covers_all_product_categories: false,
  product_lines: EMPTY_PRODUCT_LINES,
  qualification_type: null,
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
}

/**
 * Maps the loaded competence pairs onto the row shape the editor mutates in
 * place (spec 0111). Spec 0129 AC-023: a persisted row with a null category
 * opens with `all_categories` checked (D-3).
 */
function productLinesToRows(lines: EmploymentProductLine[] | undefined): CompetenceLineRow[] {
  if (!lines || lines.length === 0) {
    return EMPTY_PRODUCT_LINES
  }
  return lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category?.id ?? null,
    all_categories: line.product_category === null,
  }))
}

/** The persisted user as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(user: UserDetail, customFieldDefaults: Record<string, CustomFieldValue>): UserFormValues {
  const employment = user.employment
  return {
    email: user.email,
    locale: user.locale,
    is_active: user.is_active,
    roles: user.roles.map((role) => role.id),
    password: '',
    password_confirmation: '',
    employment: employment
      ? {
          is_manager: employment.is_manager,
          job_role_ids: employment.job_role_ids,
          reports_to_ids: employment.reports_to_ids,
          relationship_type: employment.relationship_type,
          company_id: employment.company_id,
          primary_operational_site_id: employment.primary_operational_site_id,
          remote_operational_site_ids: employment.remote_operational_site_ids,
          is_assignable: employment.is_assignable,
          covers_all_product_categories: employment.covers_all_product_categories,
          product_lines: productLinesToRows(employment.product_lines),
          qualification_type: employment.qualification_type,
          hired_at: employment.hired_at ?? '',
          terminated_at: employment.terminated_at ?? '',
          standard_daily_minutes: employment.standard_daily_minutes,
          break_daily_minutes: employment.break_daily_minutes,
        }
      : EMPTY_EMPLOYMENT,
    custom_fields: customFieldDefaults,
  }
}

/** RHF seed values for the whole form, rebuilt whenever the mode or the custom-field defaults change. */
export function useUserFormDefaults(
  mode: UserFormMode,
  customFieldDefaults: Record<string, CustomFieldValue>,
): UserFormValues {
  return useMemo<UserFormValues>(() => {
    if (mode.type === 'edit') {
      return editDefaults(mode.user, customFieldDefaults)
    }
    return {
      email: '',
      locale: DEFAULT_LOCALE,
      is_active: true,
      roles: [],
      password: '',
      password_confirmation: '',
      employment: EMPTY_EMPLOYMENT,
      custom_fields: customFieldDefaults,
    }
  }, [mode, customFieldDefaults])
}
