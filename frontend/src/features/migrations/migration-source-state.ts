import type { MigrationSourceSummary } from '@/features/migrations/types'

/**
 * Where a source stands in the migration:
 * - `notMigrated`: it never ran;
 * - `running`: its latest run is queued or in progress;
 * - `failed`: its latest run failed (it may still have been migrated earlier);
 * - `partial`: its latest run completed with failed rows;
 * - `migrated`: its latest run completed with no failed row.
 */
export type MigrationSourceState = 'notMigrated' | 'running' | 'failed' | 'partial' | 'migrated'

export function resolveMigrationSourceState(source: MigrationSourceSummary): MigrationSourceState {
  const run = source.last_run
  if (run === null) return 'notMigrated'
  if (run.status === 'pending' || run.status === 'processing') return 'running'
  if (run.status === 'failed') return 'failed'
  return run.failed_rows > 0 ? 'partial' : 'migrated'
}
