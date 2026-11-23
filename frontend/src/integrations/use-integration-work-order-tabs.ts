import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import type { WorkOrderDetailTab } from '@/features/work-orders/use-work-order-detail-tabs'
import { INTEGRATIONS } from '@/integrations'
import { useActiveIntegrations } from '@/integrations/use-active-integrations'

/** Extra work order tabs of the ACTIVE integrations, in registry order. */
export function useIntegrationWorkOrderTabs(workOrder: WorkOrderDetailWithPermissions): WorkOrderDetailTab[] {
  const { active } = useActiveIntegrations()
  const tabs: WorkOrderDetailTab[] = []

  // INTEGRATIONS is a module constant: the hook sequence is identical on every render.
  for (const integration of INTEGRATIONS) {
    if (integration.useWorkOrderTabs) {
      tabs.push(...integration.useWorkOrderTabs({ workOrder, enabled: active.includes(integration.key) }))
    }
  }

  return tabs
}
