import { describe, expect, it } from 'vitest'
import { resolveMigrationSourceState } from '@/features/migrations/migration-source-state'
import type { MigrationLastRun, MigrationSourceSummary } from '@/features/migrations/types'

function run(overrides: Partial<MigrationLastRun> = {}): MigrationLastRun {
  return {
    id: 1,
    source: 'roles',
    status: 'completed',
    total_rows: 3,
    created_rows: 3,
    skipped_rows: 0,
    failed_rows: 0,
    has_report: false,
    created_at: '2026-10-12T09:00:00.000000Z',
    ...overrides,
  }
}

function source(lastRun: MigrationLastRun | null): MigrationSourceSummary {
  return { key: 'roles', label: 'Roles', phase: 1, last_run: lastRun, migrated_at: null }
}

describe('resolveMigrationSourceState', () => {
  it('is notMigrated when the source never ran', () => {
    expect(resolveMigrationSourceState(source(null))).toBe('notMigrated')
  })

  it('is running while the latest run is queued or in progress', () => {
    expect(resolveMigrationSourceState(source(run({ status: 'pending' })))).toBe('running')
    expect(resolveMigrationSourceState(source(run({ status: 'processing' })))).toBe('running')
  })

  it('is failed when the latest run failed', () => {
    expect(resolveMigrationSourceState(source(run({ status: 'failed' })))).toBe('failed')
  })

  it('is partial when the latest run completed with failed rows, migrated otherwise', () => {
    expect(resolveMigrationSourceState(source(run({ failed_rows: 2 })))).toBe('partial')
    expect(resolveMigrationSourceState(source(run()))).toBe('migrated')
  })
})
