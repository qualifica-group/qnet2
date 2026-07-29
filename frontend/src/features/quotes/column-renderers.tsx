import { Briefcase, Handshake, UserRound } from 'lucide-react'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { CodeBadgeCell, CurrencyCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0065
 * `data_contract`), built from the shared cross-module cell library so the
 * quotes grid matches every other module (relation + icon, colored status
 * pill, money, avatar). `title` falls back to the AG Grid default text cell;
 * `code` renders as a compact monospace badge (mirrors `projects`/`products`);
 * `opportunity`/`commercial`/`reporter` are `{id, name}` relations (D-3:
 * commercial/reporter are a snapshot FK to `referents`, like on Opportunities);
 * `supervisor` is a `users` FK, rendered as a person avatar+name (mirrors
 * `opportunityColumnRenderers.supervisor`); `revenue_net`/`cost_net`/
 * `margin_net` are the persisted aggregates (D-9); `created_at` reuses the
 * shared datetime renderer.
 */
export const quoteColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  opportunity: (params) => <RelationCell {...params} icon={Handshake} />,
  quote_status: (params) => <StatusBadgeCell {...params} />,
  commercial: (params) => <RelationCell {...params} icon={Briefcase} />,
  reporter: (params) => <RelationCell {...params} icon={UserRound} />,
  supervisor: (params) => <UserCell {...params} />,
  revenue_net: (params) => <CurrencyCell {...params} />,
  cost_net: (params) => <CurrencyCell {...params} />,
  margin_net: (params) => <CurrencyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
