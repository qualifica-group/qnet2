import { useTranslation } from 'react-i18next'
import { revenueTypeLabel } from '@/features/revenue-types/group-options'
import type { EffectiveRevenueType, RevenueTypeRef } from '@/features/revenue-types/types'

interface InheritedNoteProps {
  effective: EffectiveRevenueType | null | undefined
  className?: string
}

/**
 * "Inherited from category X: code name" — shown while a product or category has no
 * sub-account of its own, so the user sees which one its invoice lines will get.
 */
export function InheritedRevenueTypeNote({ effective, className }: InheritedNoteProps) {
  const { t } = useTranslation()
  if (!effective?.inherited) {
    return <p className={className ?? 'text-xs text-muted-foreground'}>{t('revenueTypes.effective.none')}</p>
  }
  const label = revenueTypeLabel(effective.revenue_type)
  return (
    <p className={className ?? 'text-xs text-muted-foreground'}>
      {t('revenueTypes.effective.inherited', { category: effective.source_category?.name ?? '', revenueType: label })}
    </p>
  )
}

interface RevenueTypeValueProps {
  own: (Pick<RevenueTypeRef, 'id' | 'code' | 'name'> & { path?: string }) | null | undefined
  effective: EffectiveRevenueType | null | undefined
}

/** Read-only value of the field: the own sub-account, else the effective inherited one, else an empty marker. */
export function RevenueTypeValue({ own, effective }: RevenueTypeValueProps) {
  const { t } = useTranslation()
  if (own) {
    return <span title={own.path}>{revenueTypeLabel(own)}</span>
  }
  if (effective) {
    return (
      <span className="flex min-w-0 flex-col">
        <span title={effective.revenue_type.path}>{revenueTypeLabel(effective.revenue_type)}</span>
        <InheritedRevenueTypeNote effective={effective} />
      </span>
    )
  }
  return <span className="text-muted-foreground">{t('revenueTypes.unclassified')}</span>
}
