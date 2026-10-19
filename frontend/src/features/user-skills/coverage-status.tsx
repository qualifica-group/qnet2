import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { COVERAGE_STATUS_ICON } from '@/features/user-skills/coverage-status-icons'
import type { CoverageStatus } from '@/features/user-skills/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

const COVERAGE_STATUS_VARIANT: Record<CoverageStatus, BadgeVariant> = {
  covered: 'default',
  below: 'secondary',
  missing: 'destructive',
}

/** Icon and label travel together, so the status never rests on colour alone. */
export function CoverageStatusBadge({ status }: { status: CoverageStatus }) {
  const { t } = useTranslation()
  const Icon = COVERAGE_STATUS_ICON[status]

  return (
    <Badge variant={COVERAGE_STATUS_VARIANT[status]}>
      <Icon aria-hidden="true" />
      {t(`userSkills.coverage.status.${status}`)}
    </Badge>
  )
}
