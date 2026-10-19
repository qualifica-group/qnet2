import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import type { AbsenceRequestStatus } from '@/features/absences/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

/** The label always travels with the colour, so the state never rests on colour alone. */
const STATUS_VARIANT: Record<AbsenceRequestStatus, BadgeVariant> = {
  draft: 'outline',
  submitted: 'secondary',
  approved: 'default',
  rejected: 'destructive',
  cancelled: 'outline',
}

export function AbsenceRequestStatusBadge({ status }: { status: AbsenceRequestStatus }) {
  const { t } = useTranslation()
  return <Badge variant={STATUS_VARIANT[status]}>{t(`absences.status.${status}`)}</Badge>
}
