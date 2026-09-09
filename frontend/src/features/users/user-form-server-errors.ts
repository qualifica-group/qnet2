import axios from 'axios'

/**
 * How a 422 from the user write endpoints is routed back onto the form. Split
 * out of `use-user-form.ts` to keep that orchestration hook within the
 * engineering size limits (`.claude/rules/engineering.md` §6).
 */

/** The competence editor's own RHF path, target of both its 422s and its schema issue. */
export const PRODUCT_LINES_FIELD = 'employment.product_lines' as const

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
  'employment.reports_to_id',
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
 * The competence rows are ONE `MetaField`, but the server reports their 422s
 * per row (`employment.product_lines.{i}.business_function_id`, spec 0111):
 * an indexed key matches no RHF path this form renders, so the messages are
 * joined onto the collection's own path instead of being silently dropped.
 */
export function collectProductLinesServerError(error: unknown): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => key.startsWith(`${PRODUCT_LINES_FIELD}.`))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}
