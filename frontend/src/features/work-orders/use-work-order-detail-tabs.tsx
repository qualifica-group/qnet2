import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Euro, FileCheck2, Info, ListChecks, MessageSquare, MessagesSquare, NotebookPen, Paperclip, Receipt, ReceiptText } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { DocumentsTabCount } from '@/features/attachments/documents-tab-count'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { NotesTabCount } from '@/features/notes/notes-tab-count'
import { DocumentRequestsSection } from '@/features/document-requests/document-requests-section'
import { useOutboundEmailsTab } from '@/features/outbound-emails/use-outbound-emails-tab'
import { useRegistryDocumentsTab } from '@/features/registries/use-registry-documents-tab'
import { WorkOrderChatPanel } from '@/features/work-order-chat/work-order-chat-panel'
import { WorkOrderChatTabLabel } from '@/features/work-order-chat/work-order-chat-tab-label'
import { WorkOrderCostsSection } from '@/features/work-order-costs/work-order-costs-section'
import { WorkOrderPagePanel } from '@/features/work-order-page/work-order-page-panel'
import { WorkOrderInvoicesSection } from '@/features/work-order-invoices/work-order-invoices-section'
import { WORK_ORDER_ATTACHABLE_ALIAS, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { workOrderModuleFor } from '@/features/work-orders/work-order-type-routing'
import { WorkOrderTaskBoard } from '@/features/work-orders/task-board/work-order-task-board'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { IntegrationWorkOrderAccountingActions } from '@/integrations/integration-work-order-accounting-actions'
import { useIntegrationWorkOrderTabs } from '@/integrations/use-integration-work-order-tabs'

export const WORK_ORDER_INFO_TAB = 'info'

/**
 * One tab of the work order detail. `padded`: the content is a collaboration
 * section that expects the host to pad it (notes, documents, email, activity);
 * the others (Info, Task, Costi, Proforma, Chat) bring their own padding.
 * `subTabs`: a group tab (Documenti, Amministrazione) that switches between
 * its own sub-tabs.
 */
export interface WorkOrderDetailTab extends RecordCollaborationTab {
  padded?: boolean
  subTabs?: WorkOrderDetailTab[]
}

interface GroupTab {
  value: string
  label: string
  icon: ReactNode
  badge?: ReactNode
  subTabs: WorkOrderDetailTab[]
}

/**
 * A legacy group tab: absent with no authorized sub-tab, the sub-tab itself
 * when it is the only one (no strip of one), the group otherwise.
 */
function groupTab({ value, label, icon, badge, subTabs }: GroupTab): WorkOrderDetailTab | null {
  if (subTabs.length === 0) {
    return null
  }
  if (subTabs.length === 1) {
    return subTabs[0]
  }

  return { value, label, icon, badge, content: null, subTabs }
}

interface UseWorkOrderDetailTabsArgs {
  workOrder: WorkOrderDetailWithPermissions
  /** The Info tab's body (the record sections). */
  info: ReactNode
  /** Slot on the right of the strip where the Task board portals its header actions. */
  actionsContainer: HTMLElement | null
  /** A note may have added Participants (spec 0353): the host rereads the detail. */
  onMembersAdded: () => void
}

/**
 * The work order detail's tabs in the legacy order (user directive
 * 2026-11-16): Info | Task | Documenti | Amministrazione (the legacy "€") |
 * Note, then the tabs the legacy did not have (Chat, Email, Attivita'). Info
 * is always there and open by default; every other tab keeps its OWN
 * authorization gate and is absent when unauthorized (spec 0134 D-3, 0146,
 * 0173, 0175, 0190, 0282, 0359).
 */
export function useWorkOrderDetailTabs({
  workOrder,
  info,
  actionsContainer,
  onMembersAdded,
}: UseWorkOrderDetailTabsArgs): WorkOrderDetailTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { actions } = workOrder.permissions
  const integrationTabs = useIntegrationWorkOrderTabs(workOrder)
  const registryDocumentsTab = useRegistryDocumentsTab(workOrder.registry?.id)
  const outboundEmailsTab = useOutboundEmailsTab(
    { type: 'work-orders', id: workOrder.id },
    actions.view_emails,
    actions.send_email,
  )

  // Step 1: Info, always present.
  const tabs: WorkOrderDetailTab[] = [
    {
      value: WORK_ORDER_INFO_TAB,
      label: t('workOrders.detail.tabs.info'),
      icon: <Info className="size-3.5" aria-hidden="true" />,
      content: info,
    },
  ]

  // Step 2: Task board.
  if (can('tasks.viewAny')) {
    tabs.push({
      value: 'tasks',
      label: t('workOrders.taskBoard.title'),
      icon: <ListChecks className="size-3.5" aria-hidden="true" />,
      content: (
        <WorkOrderTaskBoard workOrderId={workOrder.id} workOrderType={workOrder.type} actionsContainer={actionsContainer} />
      ),
    })
  }

  // Step 3: Documenti = the work order's own documents + the registry's (legacy "Documenti aziendali").
  const documentTabs: WorkOrderDetailTab[] = []
  if (actions.view_documents) {
    documentTabs.push({
      value: 'documents',
      label: t('workOrders.detail.tabs.workOrderDocuments'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      badge: <DocumentsTabCount resource={WORK_ORDER_ATTACHABLE_ALIAS} id={workOrder.id} />,
      padded: true,
      content: (
        <DocumentsSection
          resource={WORK_ORDER_ATTACHABLE_ALIAS}
          id={workOrder.id}
          canUpload={can('attachments.create')}
          canDelete={can('attachments.delete')}
        />
      ),
    })
  }
  if (registryDocumentsTab) {
    documentTabs.push({ ...registryDocumentsTab, padded: true })
  }
  // Document requests to the customer through ArchivioSmart (spec 0428): own gate, own padding.
  if (can('document-requests.viewAny')) {
    documentTabs.push({
      value: 'document-requests',
      label: t('documentRequests.tab'),
      icon: <FileCheck2 className="size-3.5" aria-hidden="true" />,
      content: <DocumentRequestsSection workOrderId={workOrder.id} />,
    })
  }

  // Step 4: Amministrazione = Costi + proforma attive e passive.
  const accountingTabs: WorkOrderDetailTab[] = []
  if (actions.view_costs) {
    accountingTabs.push({
      value: 'costs',
      label: t('workOrders.costs.title'),
      icon: <Euro className="size-3.5" aria-hidden="true" />,
      // Integration actions on top of the costs (e.g. Formalab "Prospetto contabile discenti", spec 0427).
      content: (
        <>
          <IntegrationWorkOrderAccountingActions workOrder={workOrder} />
          <WorkOrderCostsSection workOrderId={workOrder.id} canManage={actions.manage_costs === true} />
        </>
      ),
    })
  }
  if (actions.view_invoices) {
    accountingTabs.push(
      {
        value: 'active-invoices',
        label: t('workOrders.invoices.tabs.active'),
        icon: <Receipt className="size-3.5" aria-hidden="true" />,
        content: <WorkOrderInvoicesSection workOrderId={workOrder.id} kind="active" />,
      },
      {
        value: 'passive-invoices',
        label: t('workOrders.invoices.tabs.passive'),
        icon: <ReceiptText className="size-3.5" aria-hidden="true" />,
        content: <WorkOrderInvoicesSection workOrderId={workOrder.id} kind="passive" />,
      },
    )
  }

  const documentsGroup = groupTab({
    value: 'documents-group',
    label: t('attachments.title'),
    icon: <Paperclip className="size-3.5" aria-hidden="true" />,
    badge: documentTabs[0]?.badge,
    subTabs: documentTabs,
  })
  const accountingGroup = groupTab({
    value: 'accounting',
    label: t('workOrders.detail.tabs.accounting'),
    icon: <Euro className="size-3.5" aria-hidden="true" />,
    subTabs: accountingTabs,
  })
  for (const group of [documentsGroup, accountingGroup]) {
    if (group) {
      tabs.push(group)
    }
  }

  // Step 5: Note. Gated on the viewing permission of the work order's module alone (2026-11-25: a project
  // one is `project-work-orders.view`): the membership half of the server rule
  // (`WorkOrderNotable::authorizeRead`) is not evaluable client-side; `NotesSection` owns that error state.
  if (can(`${workOrderModuleFor(workOrder.type).domain}.view`)) {
    tabs.push({
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      badge: <NotesTabCount entityType={WORK_ORDERS_DOMAIN} entityId={workOrder.id} />,
      padded: true,
      content: (
        <NotesSection
          entityType={WORK_ORDERS_DOMAIN}
          entityId={workOrder.id}
          showHeader={false}
          onMembersAdded={onMembersAdded}
        />
      ),
    })
  }

  // Step 6: the tabs the legacy did not have. The chat label loads the thread for the unread badge.
  if (actions.view_chat) {
    tabs.push({
      value: 'chat',
      label: <WorkOrderChatTabLabel workOrderId={workOrder.id} />,
      icon: <MessageSquare className="size-3.5" aria-hidden="true" />,
      content: <WorkOrderChatPanel workOrderId={workOrder.id} canWriteAction={actions.write_chat === true} />,
    })
  }
  // Workspace notes page (spec 0420): right after the chat, same access rule.
  if (actions.view_page) {
    tabs.push({
      value: 'page',
      label: t('workOrderPage.tabTitle'),
      icon: <NotebookPen className="size-3.5" aria-hidden="true" />,
      content: <WorkOrderPagePanel workOrderId={workOrder.id} canEditAction={actions.edit_page === true} />,
    })
  }
  if (outboundEmailsTab) {
    tabs.push({ ...outboundEmailsTab, padded: true })
  }
  if (actions.view_activity) {
    tabs.push({ ...activityLogTab(WORK_ORDERS_DOMAIN, workOrder.id, t('activityLog.title')), padded: true })
  }

  // Step 7: tabs contributed by the active integrations, after the core ones.
  tabs.push(...integrationTabs)

  return tabs
}
