import { useMemo } from 'react'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'
import type { EmploymentRelationRef, UserLocale } from '@/features/users/types'
import type { EmploymentFormValues } from '@/features/users/user-schema'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * The user form's seed values: what RHF opens on (create defaults / the loaded
 * user in edit mode) and the pre-known `{id, label}` items that let each
 * relation picker show its label without a hydration round-trip (spec 0015
 * AC-016). Split out of `use-user-form.ts` to keep that orchestration hook
 * within the engineering size limits (`.claude/rules/engineering.md` §6).
 */

/** Locale is not user-editable on the form; new users default to Italian. */
const DEFAULT_LOCALE: UserLocale = 'it'

/**
 * Stable module-level default for the remote-sites array field: an inline
 * `[]` would create a new reference on every render and break memoized
 * dependents (rule engineering.md §1 / frontend.md §10).
 */
const EMPTY_REMOTE_SITE_IDS: number[] = []

/** Same stable-reference reasoning for the competence row field (spec 0111). */
const EMPTY_PRODUCT_LINES: ProductLineRow[] = []

/** No relations loaded: stable module-level reference, mirrors `EMPTY_REMOTE_SITE_IDS`. */
const EMPTY_RELATION_REFS: ForSelectItem[] = []

/** Same, for the competence pairs handed to the row editor as known labels. */
const EMPTY_KNOWN_LINES: ProductLine[] = []

/** A blank employment sub-form, used for both create and an edit user with no profile yet. */
export const EMPTY_EMPLOYMENT: EmploymentFormValues = {
  is_manager: false,
  job_description: '',
  reports_to_id: null,
  relationship_type: null,
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: EMPTY_REMOTE_SITE_IDS,
  product_lines: EMPTY_PRODUCT_LINES,
  qualification_type: null,
  hired_at: '',
  terminated_at: '',
  standard_daily_minutes: null,
  break_daily_minutes: null,
}

/** Maps a loaded `{id, label}` relation ref to the AsyncPaginatedSelect hydration prop. */
function relationToForSelectItem(
  ref: EmploymentRelationRef | null | undefined,
): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.label, subtitle: ref.subtitle ?? null } : null
}

/** Array counterpart of {@link relationToForSelectItem}, for multi-select hydration. */
function relationsToForSelectItems(
  refs: EmploymentRelationRef[] | null | undefined,
): ForSelectItem[] {
  if (!refs || refs.length === 0) {
    return EMPTY_RELATION_REFS
  }
  return refs.map((ref) => ({ id: ref.id, label: ref.label, subtitle: ref.subtitle ?? null }))
}

/** Maps the loaded competence pairs onto the row shape the editor mutates in place (spec 0111). */
function productLinesToRows(lines: ProductLine[] | undefined): ProductLineRow[] {
  if (!lines || lines.length === 0) {
    return EMPTY_PRODUCT_LINES
  }
  return lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category.id,
  }))
}

/** RHF seed values for the whole form, rebuilt whenever the mode or the custom-field defaults change. */
export function useUserFormDefaults(
  mode: UserFormMode,
  customFieldDefaults: Record<string, CustomFieldValue>,
): UserFormValues {
  return useMemo<UserFormValues>(() => {
    if (mode.type === 'edit') {
      const employment = mode.user.employment
      return {
        email: mode.user.email,
        locale: mode.user.locale,
        is_active: mode.user.is_active,
        roles: mode.user.roles.map((role) => role.id),
        password: '',
        password_confirmation: '',
        employment: employment
          ? {
              is_manager: employment.is_manager,
              job_description: employment.job_description ?? '',
              reports_to_id: employment.reports_to_id,
              relationship_type: employment.relationship_type,
              company_id: employment.company_id,
              primary_operational_site_id: employment.primary_operational_site_id,
              remote_operational_site_ids: employment.remote_operational_site_ids,
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

/**
 * EDIT: everything a picker needs to render its current selection immediately.
 * The role names come from the user resource even for roles outside the
 * actor's assignable set; the employment refs and the competence pairs come
 * from the loaded profile (no extra `/for-select` fetch).
 */
export function useUserFormHydration(mode: UserFormMode) {
  const employment = mode.type === 'edit' ? mode.user.employment : null

  return useMemo(
    () => ({
      selectedRoleItems:
        mode.type === 'edit' ? mode.user.roles.map((role) => ({ id: role.id, label: role.name })) : [],
      selectedCompanyItem: relationToForSelectItem(employment?.company),
      selectedPrimaryOperationalSiteItem: relationToForSelectItem(employment?.primary_operational_site),
      selectedRemoteOperationalSiteItems: relationsToForSelectItems(employment?.remote_operational_sites),
      knownProductLines: employment?.product_lines ?? EMPTY_KNOWN_LINES,
      selectedReportsToItem: relationToForSelectItem(employment?.reports_to),
    }),
    [mode, employment],
  )
}
