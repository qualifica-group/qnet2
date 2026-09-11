import { useTranslation } from 'react-i18next'
import { Building2, Hash, History, MapPinned } from 'lucide-react'
import {
  RecordCanvas,
  RecordCard,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { cn } from '@/lib/utils'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import {
  OperationalSiteDetailHeader,
  OperationalSiteDetailStats,
} from '@/features/operational-sites/operational-site-detail-header'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'

interface OperationalSiteDetailViewProps {
  operationalSite: OperationalSiteDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single operational site, rendered as an enterprise-CRM
 * record on the same kit Opportunita', Lead, Campagne e Progetti use: the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. Container-query driven (`RecordCanvas`) so the same tree
 * renders correctly both inside a resizable Sheet and on the full-bleed
 * `/operational-sites/:id` page.
 *
 * Purely presentational: the caller fetches the fresh, re-authorized detail.
 */
export function OperationalSiteDetailView({
  operationalSite,
  onEdit,
}: OperationalSiteDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(operationalSite.created_at)
  const canViewActivity = operationalSite.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <OperationalSiteDetailHeader operationalSite={operationalSite} onEdit={onEdit} />
            <OperationalSiteDetailStats operationalSite={operationalSite} />

            <RecordSectionsGrid>
              <RecordSection
                title={t('operationalSites.form.sections.address.title')}
                icon={<MapPinned />}
              >
                <RecordFieldList>
                  {/* Only when an alias headlines the card: without one the
                      street IS the title, and repeating it here would say the
                      same thing twice (the decision the previous card made). */}
                  {operationalSite.alias ? (
                    <RecordField label={t('operationalSites.detail.line1')} icon={<Building2 />}>
                      {operationalSite.line1}
                    </RecordField>
                  ) : null}
                  <RecordField label={t('operationalSites.detail.postal_code')} icon={<Hash />}>
                    {operationalSite.postal_code || <DetailEmpty />}
                  </RecordField>
                </RecordFieldList>
              </RecordSection>
            </RecordSectionsGrid>
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="operational-sites" id={operationalSite.id} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('operationalSites.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
