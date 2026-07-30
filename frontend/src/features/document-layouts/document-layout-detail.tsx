import { useTranslation } from 'react-i18next'
import { FileText, History } from 'lucide-react'
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
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'

interface DocumentLayoutDetailViewProps {
  documentLayout: DocumentLayoutDetailWithPermissions
}

/**
 * Read-only detail of a single document layout's METADATA (name/code/
 * description/module/active/default — spec 0069 wave 1). Purely
 * presentational, composed from the shared detail kit for a consistent CRM
 * look (mirrors `PaymentMethodDetailView`). It deliberately does NOT render
 * the block/zone `config` or an A4 preview: that surface belongs to the
 * visual editor (`features/document-layouts/editor/`), a different owner.
 */
export function DocumentLayoutDetailView({ documentLayout }: DocumentLayoutDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(documentLayout.created_at)
  const updatedAt = formatDateTime(documentLayout.updated_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={documentLayout.name} icon={<FileText />} />}
        title={documentLayout.name}
        subtitle={documentLayout.code}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('documentLayouts.detail.description')}>
            {documentLayout.description ? documentLayout.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('documentLayouts.detail.module')}>
            {documentLayout.module_label}
          </DetailField>
          <DetailField label={t('documentLayouts.detail.isActive')}>
            {documentLayout.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
          <DetailField label={t('documentLayouts.detail.isDefault')}>
            {documentLayout.is_default ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {documentLayout.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="document-layouts" id={documentLayout.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('documentLayouts.detail.createdAt')}>{createdAt}</DetailMeta>
      ) : null}
      {updatedAt ? (
        <DetailMeta label={t('documentLayouts.detail.updatedAt')}>{updatedAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
