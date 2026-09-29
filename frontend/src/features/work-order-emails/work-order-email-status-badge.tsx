import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import type { OutboundEmailStatus } from '@/features/work-order-emails/types'

/** Colour is never the only signal (ui-design.md §4): the badge text always carries the status too. */
const STATUS_VARIANT: Record<OutboundEmailStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  draft: 'outline',
  queued: 'secondary',
  sent: 'default',
  failed: 'destructive',
}

export function WorkOrderEmailStatusBadge({ status }: { status: OutboundEmailStatus }) {
  const { t } = useTranslation()
  return <Badge variant={STATUS_VARIANT[status]}>{t(`workOrderEmails.status.${status}`)}</Badge>
}
