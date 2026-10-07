import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Euro, FileText, ListChecks } from 'lucide-react'
import { RecordCard } from '@/components/detail/record-panel'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAbilities } from '@/features/auth/use-abilities'
import { WorkOrderContractDataSection } from '@/features/work-order-contract-data/work-order-contract-data-section'
import { WorkOrderCostsSection } from '@/features/work-order-costs/work-order-costs-section'
import { WorkOrderTaskBoard } from '@/features/work-orders/task-board/work-order-task-board'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Compact trigger sizing, same as the collaboration card's strip. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface WorkTab {
  value: string
  label: string
  icon: ReactNode
  content: ReactNode
}

/**
 * Full-width card below the work order record hosting the Dati contrattuali
 * section (spec 0201), the Task board (spec 0146) and the Costi section (spec
 * 0190) as switchable tabs, in that order: the first tab present is the one
 * open by default (user directive 2026-10-07: Dati contrattuali first). Each
 * tab keeps its own gate (`view_contract_data`, `tasks.viewAny`, `view_costs`)
 * and is absent when unauthorized; no tab left = no card. The active tab's
 * header actions are portaled by the tab itself into the slot on the right of
 * the strip, so the strip stays the only header row of the card.
 */
export function WorkOrderDetailWorkTabs({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [actionsContainer, setActionsContainer] = useState<HTMLDivElement | null>(null)
  const tabs: WorkTab[] = []

  if (workOrder.permissions.actions.view_contract_data) {
    tabs.push({
      value: 'contract-data',
      label: t('workOrders.contractData.title'),
      icon: <FileText className="size-3.5" aria-hidden="true" />,
      content: (
        <WorkOrderContractDataSection
          workOrderId={workOrder.id}
          canManage={workOrder.permissions.actions.manage_payments === true}
        />
      ),
    })
  }

  if (can('tasks.viewAny')) {
    tabs.push({
      value: 'tasks',
      label: t('workOrders.taskBoard.title'),
      icon: <ListChecks className="size-3.5" aria-hidden="true" />,
      content: <WorkOrderTaskBoard workOrderId={workOrder.id} actionsContainer={actionsContainer} />,
    })
  }

  if (workOrder.permissions.actions.view_costs) {
    tabs.push({
      value: 'costs',
      label: t('workOrders.costs.title'),
      icon: <Euro className="size-3.5" aria-hidden="true" />,
      content: (
        <WorkOrderCostsSection workOrderId={workOrder.id} canManage={workOrder.permissions.actions.manage_costs === true} />
      ),
    })
  }

  if (tabs.length === 0) {
    return null
  }

  return (
    <RecordCard>
      <Tabs defaultValue={tabs[0].value} className="gap-0">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
          <TabsList className="w-auto">
            {tabs.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value} className={TRIGGER_CLASS}>
                {tab.icon}
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
          <div ref={setActionsContainer} className="flex items-center gap-1.5" />
        </div>
        {tabs.map((tab) => (
          <TabsContent key={tab.value} value={tab.value} className="min-w-0">
            {tab.content}
          </TabsContent>
        ))}
      </Tabs>
    </RecordCard>
  )
}
