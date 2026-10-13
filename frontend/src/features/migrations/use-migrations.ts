import { useQuery } from '@tanstack/react-query'
import {
  fetchMigrationColumns,
  fetchMigrationPreview,
  fetchMigrationSources,
} from '@/features/migrations/api'
import { resolveMigrationSourceState } from '@/features/migrations/migration-source-state'
import { migrationKeys } from '@/features/migrations/query-keys'

/** Default page size for the read-only external preview (fase 1). */
export const MIGRATION_PREVIEW_PER_PAGE = 20

/** How often the source list refreshes while one of its sources is importing, in ms. */
const SOURCES_POLL_INTERVAL_MS = 3000

/**
 * The registered migration sources with their migration state, populating the
 * source selector and the status overview. Re-polled while a source imports,
 * so its state moves on even when the import dialog was closed.
 */
export function useMigrationSources() {
  return useQuery({
    queryKey: migrationKeys.sources,
    queryFn: fetchMigrationSources,
    refetchInterval: (query) =>
      query.state.data?.some((source) => resolveMigrationSourceState(source) === 'running')
        ? SOURCES_POLL_INTERVAL_MS
        : false,
  })
}

/** The columns exposed by the selected source, driving the preview table header. */
export function useMigrationColumns(source: string | null) {
  return useQuery({
    queryKey: migrationKeys.columns(source ?? ''),
    queryFn: () => fetchMigrationColumns(source as string),
    enabled: source != null,
  })
}

/**
 * One page of the selected source's read-only external preview. This hits
 * the external system, so unlike `useMigrationColumns` (the static, contract
 * template) it is opt-in: callers gate it behind `enabled` so selecting a
 * source alone never fires a live external request.
 */
export function useMigrationPreview(
  source: string | null,
  page: number,
  perPage: number = MIGRATION_PREVIEW_PER_PAGE,
  enabled: boolean = true,
) {
  return useQuery({
    queryKey: migrationKeys.preview(source ?? '', page, perPage),
    queryFn: () => fetchMigrationPreview(source as string, page, perPage),
    enabled: source != null && enabled,
    // Keeps the previous page's rows on screen while the next page loads,
    // instead of flashing the loading skeleton on every prev/next click.
    placeholderData: (previous) => previous,
  })
}
