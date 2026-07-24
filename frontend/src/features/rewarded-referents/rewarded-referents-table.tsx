import { PageHeader } from '@/components/page-header'
import { TableView } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { rewardedReferentColumnRenderers } from '@/features/rewarded-referents/column-renderers'
import { RewardDetailRenderer } from '@/features/rewarded-referents/reward-detail-renderer'

/** Domain key used to mount the generic table for `rewarded-referents`. */
export const REWARDED_REFERENTS_DOMAIN = 'rewarded-referents'

/** Read-only module (spec 0059 D-6): the backend action catalog carries no row action. */
const handleAction: RowActionHandler = () => {}

/**
 * Thin `rewarded-referents` adapter over the generic table (spec 0059): the
 * columns are server-driven (`domain="rewarded-referents"`); the only
 * domain-specific wiring is the count/date renderers and the master/detail
 * expansion (D-4), whose detail panel is `RewardDetailRenderer`. No CRUD, no
 * row actions, no sheet — the module has neither a create/edit form nor a
 * detail screen (D-6), so it does not register a `moduleScreen`.
 */
export function RewardedReferentsTable() {
  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        domain={REWARDED_REFERENTS_DOMAIN}
        renderers={rewardedReferentColumnRenderers}
        onAction={handleAction}
        masterDetail
        detailCellRenderer={RewardDetailRenderer}
        detailRowAutoHeight
      />
    </div>
  )
}
