import { useTranslation } from 'react-i18next'
import { Hash, MapPin } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { formatDateTime } from '@/features/table/cell-renderers'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompany } from '@/features/companies/api'
import {
  CompanyDetailHeader,
  CompanyDetailStats,
} from '@/features/companies/company-detail-header'
import type { CompanyAddress } from '@/features/companies/types'

interface CompanyDetailProps {
  companyId: number
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single company, rendered as an enterprise-CRM record on
 * the same kit Opportunita', Lead, Campagne, Progetti e Sedi use: the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. Container-query driven (`RecordCanvas`) so the same tree
 * renders correctly both inside a resizable Sheet and on a full-bleed page.
 *
 * It owns its own fetch (like `UserDetailView`): every caller opens this card
 * by id.
 */
export function CompanyDetailView({ companyId, onEdit }: CompanyDetailProps) {
  const { t } = useTranslation()
  const {
    data: company,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['companies', 'detail', companyId], () => fetchCompany(companyId))

  if (isError) {
    return (
      <DetailError
        message={t('companies.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !company) {
    return <DetailLoading />
  }

  const createdAt = formatDateTime(company.created_at)
  const collaborationTabs = company.permissions.actions.view_activity
    ? [activityLogTab('companies', company.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={collaborationTabs.length > 0 ? <RecordCollaborationCard tabs={collaborationTabs} /> : null}
      >
        <RecordCard>
          <CompanyDetailHeader company={company} onEdit={onEdit} />
          <CompanyDetailStats company={company} />

          <RecordSectionsGrid>
            <RecordSection title={t('companies.form.sections.address.title')} icon={<MapPin />} full>
              <AddressFields address={company.address} />
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('companies.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}

/**
 * The address of record: street, second line, postal code and the region. The
 * comune/province/country are NOT repeated here — the KPI strip above already
 * answers "where", and printing the same three values twice on one card is the
 * duplication the record kit exists to avoid.
 */
function AddressFields({ address }: { address: CompanyAddress | null }) {
  const { t } = useTranslation()

  if (!address) {
    return <DetailEmpty />
  }

  return (
    <RecordFieldList>
      <RecordField label={t('companies.form.line1')}>{address.line1 || <DetailEmpty />}</RecordField>
      {address.line2 ? (
        <RecordField label={t('companies.form.line2')}>{address.line2}</RecordField>
      ) : null}
      <RecordField label={t('companies.form.postalCode')} icon={<Hash />}>
        {address.postal_code || <DetailEmpty />}
      </RecordField>
      <RecordField label={t('companies.detail.region')}>
        {address.region || <DetailEmpty />}
      </RecordField>
    </RecordFieldList>
  )
}
