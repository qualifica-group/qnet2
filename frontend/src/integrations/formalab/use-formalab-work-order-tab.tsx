import { useTranslation } from 'react-i18next'
import { GraduationCap } from 'lucide-react'
import type { WorkOrderDetailTab } from '@/features/work-orders/use-work-order-detail-tabs'
import { FormalabWorkOrderPanel } from '@/integrations/formalab/components/work-order-panel'
import { useFormalabWorkOrderLink } from '@/integrations/formalab/use-formalab-work-order-link'
import type { IntegrationWorkOrderTabsArgs } from '@/integrations/types'

const FORMALAB_TAB_VALUE = 'formalab'

/** The "Formalab" tab: present only when the integration is active and the work order is eligible or already linked. */
export function useFormalabWorkOrderTab({ workOrder, enabled }: IntegrationWorkOrderTabsArgs): WorkOrderDetailTab[] {
  const { t } = useTranslation('formalab')
  const { data } = useFormalabWorkOrderLink(workOrder.id, enabled)

  if (!enabled || !data || (!data.eligible && data.link === null)) {
    return []
  }

  return [
    {
      value: FORMALAB_TAB_VALUE,
      label: t('workOrder.tab'),
      icon: <GraduationCap className="size-3.5" aria-hidden="true" />,
      content: <FormalabWorkOrderPanel workOrderId={workOrder.id} />,
    },
  ]
}
