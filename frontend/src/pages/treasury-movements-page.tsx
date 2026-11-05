import { Can } from '@/features/auth/can'
import { TreasuryMovementsTable } from '@/features/treasury-movements/treasury-movements-table'

/** Treasury movements (spec 0360): grid + create/edit modal, gated by `treasury-movements.viewAny`. */
export default function TreasuryMovementsPage() {
  return (
    <Can permission="treasury-movements.viewAny">
      <TreasuryMovementsTable />
    </Can>
  )
}
