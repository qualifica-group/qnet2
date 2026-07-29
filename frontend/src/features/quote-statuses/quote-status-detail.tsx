import { useTranslation } from 'react-i18next'
import { Flag, History } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { QuoteStatusDetailWithPermissions } from '@/features/quote-statuses/types'

interface QuoteStatusDetailViewProps {
  quoteStatus: QuoteStatusDetailWithPermissions
}

/**
 * Read-only detail of a single quote status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look.
 */
export function QuoteStatusDetailView({ quoteStatus }: QuoteStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(quoteStatus.created_at)
  const swatch = swatchClassFor(quoteStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={quoteStatus.name} icon={<Flag />} />}
        title={quoteStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('quoteStatuses.detail.color')}>
            {quoteStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${quoteStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('quoteStatuses.detail.sort_order')}>
            {quoteStatus.sort_order}
          </DetailField>
          <DetailField label={t('quoteStatuses.detail.group')}>
            {t(`quoteStatuses.form.group.${quoteStatus.group}`)}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {quoteStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="quote-statuses" id={quoteStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('quoteStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
