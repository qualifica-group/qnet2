import { CountCell, DateTimeCell } from '@/features/table/cell-renderers'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id` for the import-runs
 * history table (spec 0034). Previously the table shipped no renderers and let
 * every cell fall back to the generic formatter; wiring the shared cells brings
 * it in line with the other modules — `created_at` uses the shared datetime
 * renderer, `user` (the operator who started the run) uses the shared person
 * cell, the row counters (`total_rows`/`imported_rows`/`invalid_rows`) use
 * the shared count badge. `original_filename` stays plain text; `status` is a
 * `badge` column, already rendered by the generic `BadgeCell` (colored pill with
 * status dot), so it is intentionally not overridden here.
 */
export const importRunColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateTimeCell {...params} />,
  user: (params) => <UserCell {...params} />,
  total_rows: (params) => <CountCell {...params} />,
  imported_rows: (params) => <CountCell {...params} />,
  invalid_rows: (params) => <CountCell {...params} />,
}
