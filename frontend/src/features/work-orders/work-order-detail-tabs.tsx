import { useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { RecordCard } from '@/components/detail/record-panel'
import { RecordTabIcon } from '@/components/detail/record-tab-icon'
import { TABS_LIST_CLASS, TABS_TRIGGER_CLASS, Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn } from '@/lib/utils'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import {
  WORK_ORDER_INFO_TAB,
  useWorkOrderDetailTabs,
  type WorkOrderDetailTab,
} from '@/features/work-orders/use-work-order-detail-tabs'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { useWorkOrderActiveTab } from '@/features/work-orders/use-work-order-active-tab'

/** Padding of the collaboration sections, which leave it to their host. */
const PADDED_CONTENT_CLASS = 'p-4'

function TabTriggers({ tabs }: { tabs: WorkOrderDetailTab[] }) {
  return tabs.map((tab) => (
    <TabsTrigger key={tab.value} value={tab.value}>
      <RecordTabIcon icon={tab.icon} badge={tab.badge} />
      {tab.label}
    </TabsTrigger>
  ))
}

function TabContents({ tabs }: { tabs: WorkOrderDetailTab[] }) {
  return tabs.map((tab) => (
    <TabsContent key={tab.value} value={tab.value} className={cn('min-w-0', tab.padded && PADDED_CONTENT_CLASS)}>
      {tab.subTabs ? <WorkOrderDetailSubTabs tabs={tab.subTabs} /> : tab.content}
    </TabsContent>
  ))
}

/** The sub-tab strip of a group tab (Documenti, Amministrazione), first sub-tab open. */
function WorkOrderDetailSubTabs({ tabs }: { tabs: WorkOrderDetailTab[] }) {
  return (
    <Tabs defaultValue={tabs[0].value} className="gap-0">
      <div className="px-4 pt-3">
        <TabsList className="w-auto">
          <TabTriggers tabs={tabs} />
        </TabsList>
      </div>
      <TabContents tabs={tabs} />
    </Tabs>
  )
}

interface WorkOrderTitleTabProps {
  title: string
  onSelect: () => void
}

/**
 * The legacy's last "tab": the work order title, set a little apart from the
 * strip (user directive 2026-11-16). Like the legacy button, it brings back
 * the Info tab. Not a Radix tab (it opens no panel of its own), so it borrows
 * the strip's classes to read the same.
 */
function WorkOrderTitleTab({ title, onSelect }: WorkOrderTitleTabProps) {
  return (
    <div className={cn(TABS_LIST_CLASS, 'w-auto max-w-full sm:ml-4')}>
      <button
        type="button"
        data-state="active"
        title={title}
        onClick={onSelect}
        className={cn(TABS_TRIGGER_CLASS, 'max-w-xs min-w-0 shrink')}
      >
        <span className="truncate">{title}</span>
      </button>
    </div>
  )
}

interface WorkOrderDetailTabsProps {
  workOrder: WorkOrderDetailWithPermissions
  /** The Info tab's body (the record sections). */
  info: ReactNode
  /** The tab to open first outside the work order's page (spec 0405); Info when absent. */
  initialTab?: string
  /** After a change made from a tab (the progress popup of Amministrazione): the host refreshes its lists. */
  onChanged?: () => void
}

/**
 * The work order's tab card, below the identity band, laid out like the
 * legacy "Gestione commessa" (user directive 2026-11-16): Info | Task |
 * Documenti | Amministrazione | Note (+ Chat, Email, Attivita'), then the
 * title "tab" set apart. Info opens by default; on the work order's page the
 * open tab follows the URL like a page (`useWorkOrderActiveTab`). The active tab's header
 * actions (the Task board's) are portaled into the slot on the right of the
 * strip, so the strip stays the only header row of the card.
 */
export function WorkOrderDetailTabs({ workOrder, info, initialTab, onChanged }: WorkOrderDetailTabsProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [actionsContainer, setActionsContainer] = useState<HTMLDivElement | null>(null)
  const tabs = useWorkOrderDetailTabs({
    workOrder,
    info,
    actionsContainer,
    onChanged,
    onMembersAdded: () => {
      // Spec 0353: una nota puo' aver aggiunto Partecipanti; il dettaglio li rilegge.
      void queryClient.invalidateQueries({ queryKey: workOrderDetailQueryKey(workOrder.id) })
    },
  })
  const { activeTab, setActiveTab } = useWorkOrderActiveTab(workOrder.id, tabs, initialTab)

  return (
    <RecordCard>
      <Tabs value={activeTab} onValueChange={setActiveTab} className="gap-0">
        <div className="flex flex-wrap items-center gap-2 border-b px-4 py-3">
          <TabsList className="w-auto" aria-label={t('workOrders.detail.tabs.label')}>
            <TabTriggers tabs={tabs} />
          </TabsList>
          <WorkOrderTitleTab title={workOrder.title} onSelect={() => setActiveTab(WORK_ORDER_INFO_TAB)} />
          <div ref={setActionsContainer} className="ml-auto flex items-center gap-1.5" />
        </div>
        <TabContents tabs={tabs} />
      </Tabs>
    </RecordCard>
  )
}
