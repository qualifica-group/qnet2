/**
 * Contract of `GET /api/dashboard/tasks` (spec 0151 data_contract): five Task
 * counters, all scoped to `TaskVisibilityScope` + `status=open` and restricted
 * by the `assignment` filter of the same name (D-3/D-4/D-5). `assigned_by_me`
 * additionally carries the `in_validation` sub-count behind the "da validare"
 * chip.
 */

export interface DashboardTaskCounterBucket {
  count: number
  total_minutes: number
}

export interface DashboardTaskAssignedByMeBucket extends DashboardTaskCounterBucket {
  to_validate: DashboardTaskCounterBucket
}

export interface DashboardTaskCounters {
  not_completed: DashboardTaskCounterBucket
  assigned_to_me: DashboardTaskCounterBucket
  assigned_by_me: DashboardTaskAssignedByMeBucket
  created_by_me: DashboardTaskCounterBucket
  observed_by_me: DashboardTaskCounterBucket
}

/** Keys rendered as one card each, in D-9's fixed display order. */
export type DashboardTaskCounterKey =
  | 'not_completed'
  | 'assigned_to_me'
  | 'assigned_by_me'
  | 'created_by_me'
  | 'observed_by_me'
