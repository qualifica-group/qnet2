import { useTranslation } from 'react-i18next'
import { CircleCheck, CircleX } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { StaffTrainingOutcome } from '@/features/staff-trainings/types'

const OUTCOME_ICON = { passed: CircleCheck, failed: CircleX } as const

/** Icon and label travel together, so the outcome never rests on colour alone. */
export function StaffTrainingOutcomeBadge({ outcome }: { outcome: StaffTrainingOutcome }) {
  const { t } = useTranslation()
  const Icon = OUTCOME_ICON[outcome]

  return (
    <Badge variant={outcome === 'passed' ? 'default' : 'destructive'}>
      <Icon aria-hidden="true" />
      {t(`staffTrainings.outcome.${outcome}`)}
    </Badge>
  )
}
