import { useTranslation } from 'react-i18next'
import { Mail } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { OutboundEmailsPanel } from '@/features/outbound-emails/outbound-emails-panel'
import type { CreateOutboundEmailPayload, EmailOwnerRef } from '@/features/outbound-emails/types'

/** Stable per-owner tab ids (the work order one predates the generalization and stays unchanged). */
const TAB_VALUE: Record<EmailOwnerRef['type'], string> = {
  'work-orders': 'work-order-emails',
  invoices: 'invoice-emails',
}

/**
 * "Email" tab of the work order detail (spec 0175, AC-019). Gated on
 * `view_emails` alone — `send_email` only toggles the "Nuova email"
 * affordance inside the panel — mirroring `useRegistryDocumentsTab`'s
 * null-or-tab shape.
 */
export function useOutboundEmailsTab(
  owner: EmailOwnerRef,
  canViewEmails: boolean,
  canSendEmail: boolean,
  newDraftPayload?: CreateOutboundEmailPayload,
): RecordCollaborationTab | null {
  const { t } = useTranslation()

  if (!canViewEmails) {
    return null
  }

  return {
    value: TAB_VALUE[owner.type],
    label: t('outboundEmails.tab.title'),
    icon: <Mail className="size-3.5" aria-hidden="true" />,
    content: <OutboundEmailsPanel owner={owner} canSend={canSendEmail} newDraftPayload={newDraftPayload} />,
  }
}
