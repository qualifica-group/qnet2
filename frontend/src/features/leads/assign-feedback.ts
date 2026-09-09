/**
 * Outcome of a bulk operator assignment, shared by the three surfaces that
 * perform one (spec 0110): staged import rows, leads and Gestione richieste
 * offers. The endpoints name the written count differently (`updated` on the
 * import rows, `assigned` on the other two), so the caller maps it onto
 * `assigned` here; `skipped` is the additive count of records `balanced` left
 * without an operator because no competent one exists.
 */
export interface AssignmentOutcome {
  assigned: number
  skipped?: number
}

/**
 * Minimal shape of i18next's `t` used here: keeping it structural (instead of
 * `TFunction`) lets namespaced translators (`useTranslation('importWizard')`)
 * and the root one feed the same helper without generic friction.
 */
type TranslateFn = (key: string, options?: Record<string, unknown>) => string

/**
 * Success message of a bulk assignment (spec 0110 AC-044): how many records
 * were assigned and — ONLY when some were left behind — how many stayed
 * without an operator because none was competent for them. `keyPrefix` is the
 * surface's own assign namespace, which must expose both `success` and
 * `successWithSkipped`.
 */
export function resolveAssignFeedback(
  t: TranslateFn,
  keyPrefix: string,
  outcome: AssignmentOutcome,
): string {
  const skipped = outcome.skipped ?? 0
  return skipped > 0
    ? t(`${keyPrefix}.successWithSkipped`, { count: outcome.assigned, skipped })
    : t(`${keyPrefix}.success`, { count: outcome.assigned })
}
