import axios from 'axios'

/**
 * How a 422 from the user write endpoints is routed back onto the form. Split
 * out of `use-user-form.ts` to keep that orchestration hook within the
 * engineering size limits (`.claude/rules/engineering.md` §6).
 */

/** The competence editor's own RHF path, target of both its 422s and its schema issue. */
export const PRODUCT_LINES_FIELD = 'employment.product_lines' as const

/** The reports-to picker's own RHF path (spec 0166), target of its indexed 422s. */
export const REPORTS_TO_IDS_FIELD = 'employment.reports_to_ids' as const

/**
 * Server-side field names mapped onto the form for 422 handling. `avatar` only
 * applies to the create flow's deferred upload; it is mapped to a form-level
 * error since the AvatarUpload control is not an RHF field. The nested
 * `employment.*` paths (spec 0015) match Laravel's flat dot-key error shape
 * 1:1, so RHF resolves them onto the right nested field with no extra mapping.
 */
export const SERVER_ERROR_FIELDS = [
  'email',
  'locale',
  'is_active',
  'roles',
  'password',
  'employment.is_manager',
  'employment.job_description',
  REPORTS_TO_IDS_FIELD,
  'employment.relationship_type',
  'employment.company_id',
  'employment.primary_operational_site_id',
  'employment.remote_operational_site_ids',
  PRODUCT_LINES_FIELD,
  'employment.qualification_type',
  'employment.hired_at',
  'employment.terminated_at',
  'employment.standard_daily_minutes',
  'employment.break_daily_minutes',
] as const

/**
 * Joins every 422 message reported under an INDEXED sub-key of `fieldPath`
 * (`{fieldPath}.{i}...`) onto that field's own path. Shared by the two array
 * fields whose per-row/per-id 422s match no RHF path this form renders
 * (competence rows, spec 0111; reports-to ids, spec 0166 AC-014).
 */
function collectIndexedServerError(error: unknown, fieldPath: string): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => key.startsWith(`${fieldPath}.`))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}

/**
 * The competence rows are ONE `MetaField`, but the server reports their 422s
 * per row (`employment.product_lines.{i}.business_function_id`, spec 0111).
 */
export function collectProductLinesServerError(error: unknown): string | null {
  return collectIndexedServerError(error, PRODUCT_LINES_FIELD)
}

/**
 * The reports-to picker is ONE `MetaField`, but the server reports duplicate/
 * non-existent/self ids per index (`employment.reports_to_ids.{i}`, spec 0166
 * AC-014).
 */
export function collectReportsToServerError(error: unknown): string | null {
  return collectIndexedServerError(error, REPORTS_TO_IDS_FIELD)
}
