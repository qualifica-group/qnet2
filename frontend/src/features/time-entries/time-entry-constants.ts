/**
 * Shared, fixed UI constants of the time entries module (spec 0122): daily
 * status presentation, team coverage color thresholds, sizing. These are
 * FIXED app icons/colors (not admin-configurable), so they import lucide
 * directly instead of going through the curated `custom-fields/icon-catalog`
 * (that catalog exists for user-picked icons only).
 */

import { CheckCircle2, MinusCircle, TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react'
import { badgeColorClass } from '@/features/table/cell-renderers'
import type { DailyStatus } from '@/features/time-entries/types'

/** Page size of `GET /api/time-entries` (D-13 default). */
export const TIME_ENTRIES_PER_PAGE = 15

/** Entries shown before a day card collapses the rest behind "show all" (D-14/AC-040). */
export const TIME_ENTRY_DAY_ENTRIES_COLLAPSE_THRESHOLD = 3

/** Clamp for a coverage/utilization progress bar so a >100% day never overflows the track. */
export const TIME_ENTRY_COVERAGE_MAX_PERCENTAGE = 101

/** Debounce (ms) applied to every server-search field of the filters drawer. */
export const TIME_ENTRY_FILTER_SEARCH_DEBOUNCE_MS = 200

/** Team coverage color thresholds (D-10): >=high emerald, >=medium amber, >0 red, 0 slate. */
export const TEAM_COVERAGE_HIGH_THRESHOLD = 80
export const TEAM_COVERAGE_MEDIUM_THRESHOLD = 50

/** A `BADGE_COLOR_TOKENS` token, resolved through `badgeColorClass` by the consuming component. */
export type TeamCoverageColorToken = 'emerald' | 'amber' | 'red' | 'slate'

/** Resolves a team member's coverage percentage to its badge color token (D-10). */
export function resolveTeamCoverageColorToken(percentage: number): TeamCoverageColorToken {
  if (percentage >= TEAM_COVERAGE_HIGH_THRESHOLD) {
    return 'emerald'
  }
  if (percentage >= TEAM_COVERAGE_MEDIUM_THRESHOLD) {
    return 'amber'
  }
  return percentage > 0 ? 'red' : 'slate'
}

/** Resolves a team member's coverage percentage straight to its badge classes. */
export function teamCoverageBadgeClass(percentage: number): string | undefined {
  return badgeColorClass(resolveTeamCoverageColorToken(percentage))
}

/** Presentation of one `DailyStatus` value: i18n key (under `timeEntries.dailyStatus`), icon, color. */
export interface DailyStatusMeta {
  labelKey: string
  icon: LucideIcon
  colorToken: TeamCoverageColorToken | 'slate'
}

/** Icon/color per `DailyStatus` (D-7), lucide equivalents of q-net's Tabler set. */
export const DAILY_STATUS_META: Record<DailyStatus, DailyStatusMeta> = {
  no_target: { labelKey: 'noTarget', icon: MinusCircle, colorToken: 'slate' },
  under_target: { labelKey: 'underTarget', icon: TrendingDown, colorToken: 'amber' },
  on_target: { labelKey: 'onTarget', icon: CheckCircle2, colorToken: 'emerald' },
  over_target: { labelKey: 'overTarget', icon: TrendingUp, colorToken: 'red' },
}

/** Resolves a `DailyStatus` straight to its badge classes. */
export function dailyStatusBadgeClass(status: DailyStatus): string | undefined {
  return badgeColorClass(DAILY_STATUS_META[status].colorToken)
}
