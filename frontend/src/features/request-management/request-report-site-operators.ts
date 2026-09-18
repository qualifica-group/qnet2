import type { RequestReportOperator } from '@/features/request-management/report-api'

/**
 * The GA2 the operator picker offers under the chosen Sedi (user directive
 * 2026-09-18): with every Sede selected — or none on offer — the whole list,
 * "Non assegnato" included; otherwise only the operators belonging to at
 * least one chosen Sede, which is the same membership the server's site
 * filter reads, so the picker never offers an operator the site axis would
 * then exclude.
 */
export function operatorsForSites(
  operators: RequestReportOperator[],
  siteKeys: string[],
  availableSiteKeys: string[],
): RequestReportOperator[] {
  if (availableSiteKeys.every((key) => siteKeys.includes(key))) {
    return operators
  }

  return operators.filter((operator) => operator.site_keys.some((key) => siteKeys.includes(key)))
}

/**
 * The operator selection after the Sedi changed: a selection that covered
 * every operator on offer keeps covering every operator of the new list (so
 * "all" stays "all" while narrowing the Sedi), a partial one keeps only the
 * picks still on offer — never an operator the picker no longer shows.
 */
export function followSiteSelection(
  operatorKeys: string[],
  previousOffered: string[],
  nextOffered: string[],
): string[] {
  if (previousOffered.every((key) => operatorKeys.includes(key))) {
    return nextOffered
  }

  return operatorKeys.filter((key) => nextOffered.includes(key))
}
