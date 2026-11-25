import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { INTEGRATIONS } from '@/integrations'
import { useActiveIntegrations } from '@/integrations/use-active-integrations'

/** The work order accounting actions (Amministrazione > Costi) of the ACTIVE integrations, in registry order. */
export function IntegrationWorkOrderAccountingActions({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { active } = useActiveIntegrations()

  return INTEGRATIONS.map((integration) => {
    const Actions = integration.WorkOrderAccountingActions
    return Actions && active.includes(integration.key) ? <Actions key={integration.key} workOrder={workOrder} /> : null
  })
}
