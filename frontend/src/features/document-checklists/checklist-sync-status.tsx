import { useTranslation } from 'react-i18next'
import { CircleAlert, CircleCheck, Clock, type LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { DocumentChecklistSyncStatus } from '@/features/document-checklists/types'

type BadgeVariant = 'secondary' | 'destructive' | 'outline'

const SYNC_STATUS_PRESENTATION: Record<DocumentChecklistSyncStatus, { variant: BadgeVariant; Icon: LucideIcon }> = {
  synced: { variant: 'secondary', Icon: CircleCheck },
  failed: { variant: 'destructive', Icon: CircleAlert },
  pending: { variant: 'outline', Icon: Clock },
}

interface ChecklistSyncStatusBadgeProps {
  status: DocumentChecklistSyncStatus
  className?: string
}

/** Sync state of a checklist: always a text label plus an icon, never color alone. */
export function ChecklistSyncStatusBadge({ status, className }: ChecklistSyncStatusBadgeProps) {
  const { t } = useTranslation()
  const { variant, Icon } = SYNC_STATUS_PRESENTATION[status]

  return (
    <Badge variant={variant} className={className}>
      <Icon aria-hidden="true" />
      {t(`documentChecklists.syncStatus.${status}`)}
    </Badge>
  )
}
