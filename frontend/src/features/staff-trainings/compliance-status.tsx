import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { COMPLIANCE_STATUS_ICON } from '@/features/staff-trainings/compliance-status-icons'
import type { ComplianceStatus } from '@/features/staff-trainings/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

const COMPLIANCE_STATUS_VARIANT: Record<ComplianceStatus, BadgeVariant> = {
  valid: 'default',
  expiring: 'secondary',
  expired: 'destructive',
  missing: 'outline',
}

/** Icon and label travel together, so the status never rests on colour alone. */
export function ComplianceStatusBadge({ status }: { status: ComplianceStatus }) {
  const { t } = useTranslation()
  const Icon = COMPLIANCE_STATUS_ICON[status]

  return (
    <Badge variant={COMPLIANCE_STATUS_VARIANT[status]}>
      <Icon aria-hidden="true" />
      {t(`staffTrainings.compliance.status.${status}`)}
    </Badge>
  )
}
