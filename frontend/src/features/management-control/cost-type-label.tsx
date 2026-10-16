import { useTranslation } from 'react-i18next'
import type { ExposureCostType } from '@/features/management-control/exposure-types'

/** Cost type with its colour dot (data, not theme: decorative, hidden from assistive technology). */
export function CostTypeLabel({ costType }: { costType: ExposureCostType | null }) {
  const { t } = useTranslation()

  if (costType === null) {
    return <span className="text-muted-foreground">{t('managementControl.structure.noCostType')}</span>
  }
  return (
    <span className="flex items-center gap-1.5">
      {costType.color ? (
        <span aria-hidden="true" className="size-2.5 shrink-0 rounded-full border" style={{ backgroundColor: costType.color }} />
      ) : null}
      <span className="truncate">{costType.name}</span>
    </span>
  )
}
