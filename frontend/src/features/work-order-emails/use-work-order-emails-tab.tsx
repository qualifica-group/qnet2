import { useTranslation } from 'react-i18next'
import { Mail } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { WorkOrderEmailsPanel } from '@/features/work-order-emails/work-order-emails-panel'

/**
 * "Email" tab of the work order detail (spec 0175, AC-019). Gated on
 * `view_emails` alone — `send_email` only toggles the "Nuova email"
 * affordance inside the panel — mirroring `useRegistryDocumentsTab`'s
 * null-or-tab shape.
 */
export function useWorkOrderEmailsTab(
  workOrderId: number,
  canViewEmails: boolean,
  canSendEmail: boolean,
): RecordCollaborationTab | null {
  const { t } = useTranslation()

  if (!canViewEmails) {
    return null
  }

  return {
    value: 'work-order-emails',
    label: t('workOrderEmails.tab.title'),
    icon: <Mail className="size-3.5" aria-hidden="true" />,
    content: <WorkOrderEmailsPanel workOrderId={workOrderId} canSend={canSendEmail} />,
  }
}
