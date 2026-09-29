import { FolderKanban, MapPin } from 'lucide-react'
import { DateTimeCell } from '@/features/table/cell-renderers'
import {
  CodeBadgeCell,
  CurrencyCell,
  DateCell,
  GeoScopeCell,
  RelationCell,
  StatusBadgeCell,
} from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the shared
 * cross-module cell library so the campaign grid matches projects/leads. The
 * effective `pipeline_status` now carries its color token from
 * `CampaignsTableDefinition::mapRow`, so it renders as the same colored status
 * badge as projects/leads. `code` renders as a compact monospace badge; `name`/
 * `target_lead` fall back to the AG Grid default cell; `created_at` reuses the
 * shared datetime renderer (spec 0023). `geo_scope` is the merged, display-only
 * campaign-or-project geo badge with its resolved place name (spec 0027 D-2).
 */
export const campaignColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  project: (params) => <RelationCell {...params} icon={FolderKanban} />,
  pipeline_status: (params) => <StatusBadgeCell {...params} />,
  country: (params) => <RelationCell {...params} />,
  state: (params) => <RelationCell {...params} />,
  province: (params) => <RelationCell {...params} />,
  city: (params) => <RelationCell {...params} />,
  geo_scope: (params) => <GeoScopeCell {...params} withPlace />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  source: (params) => <RelationCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <CurrencyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
