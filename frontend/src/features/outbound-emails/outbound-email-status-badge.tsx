import { useTranslation } from 'react-i18next'
import { CircleAlert, CircleCheck, Clock, PenLine, type LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { OutboundEmailStatus } from '@/features/outbound-emails/types'

interface StatusAppearance {
  variant: 'default' | 'secondary' | 'destructive' | 'outline'
  icon: LucideIcon
  className?: string
}

/** Colour is never the only signal (ui-design.md §4): every status carries its own icon and label. */
const STATUS_APPEARANCE: Record<OutboundEmailStatus, StatusAppearance> = {
  draft: { variant: 'outline', icon: PenLine },
  queued: { variant: 'secondary', icon: Clock },
  sent: { variant: 'default', icon: CircleCheck, className: 'bg-success/15 text-success' },
  failed: { variant: 'destructive', icon: CircleAlert },
}

export function OutboundEmailStatusBadge({ status }: { status: OutboundEmailStatus }) {
  const { t } = useTranslation()
  const { variant, icon: Icon, className } = STATUS_APPEARANCE[status]
  return (
    <Badge variant={variant} className={className}>
      <Icon aria-hidden="true" />
      {t(`outboundEmails.status.${status}`)}
    </Badge>
  )
}
