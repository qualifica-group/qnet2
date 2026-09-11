import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { History, Landmark, MapPin, Phone } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'
import {
  CompanySiteDetailHeader,
  CompanySiteDetailStats,
} from '@/features/company-sites/company-site-detail-header'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardToDraft } from '@/features/personal-data/drafts'
import type { PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompanySite, setDefaultCompanySite } from '@/features/company-sites/api'
import type { CompanySiteBank } from '@/features/company-sites/types'

/**
 * Renders the (owner-agnostic, reused unchanged) contacts/addresses managers
 * in pure read-only mode: visible, never editable — no add/edit/remove
 * affordance shows up.
 */
const READ_ONLY_FIELD_PERMISSION: PersonalDataFieldPermissionResolver = () => ({
  visible: true,
  editable: false,
  required: false,
  disabled: false,
  readonly: true,
})

/** No-op change handler: the read-only managers never call it. */
function noopChange(): void {}

interface CompanySiteDetailProps {
  companySiteId: number
  /** Called after a successful set-default so the caller can refresh the grid. */
  onDefaultChange?: () => void
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single company site, rendered as an enterprise-CRM
 * record on the same kit Opportunita', Lead, Campagne, Progetti e Sedi use: the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. Container-query driven (`RecordCanvas`) so the same tree
 * renders correctly both inside a resizable Sheet and on a full-bleed page.
 *
 * Still hosts the "Società di Default" action (AC-020), now in the identity
 * band beside Edit: it is shown only when the site is not already the default
 * and the actor may perform it.
 */
export function CompanySiteDetailView({
  companySiteId,
  onDefaultChange,
  onEdit,
}: CompanySiteDetailProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [settingDefault, setSettingDefault] = useState(false)
  const {
    data: site,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['company-sites', 'detail', companySiteId], () =>
    fetchCompanySite(companySiteId),
  )

  if (isError) {
    return (
      <DetailError
        message={t('companySites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !site) {
    return <DetailLoading />
  }

  const handleSetDefault = async () => {
    setSettingDefault(true)
    try {
      const updated = await setDefaultCompanySite(site.id)
      queryClient.setQueryData(['company-sites', 'detail', site.id], {
        ...updated,
        permissions: site.permissions,
      })
      toast.success(t('companySites.form.defaultSet'))
      onDefaultChange?.()
    } catch {
      toast.error(t('companySites.form.defaultError'))
    } finally {
      setSettingDefault(false)
    }
  }

  const createdAt = formatDateTime(site.created_at)
  const canSetDefault = !site.is_default && site.permissions.actions.set_default
  const canViewActivity = site.permissions.actions.view_activity
  const card = site.personal_data
  const draft = card ? cardToDraft(card) : null

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <CompanySiteDetailHeader
              site={site}
              onEdit={onEdit}
              onSetDefault={canSetDefault ? () => void handleSetDefault() : undefined}
              isSettingDefault={settingDefault}
            />
            <CompanySiteDetailStats site={site} />

            <RecordSectionsGrid>
              <RecordSection
                title={t('companySites.form.sections.contacts.title')}
                icon={<Phone />}
              >
                {draft ? (
                  <ContactsManager
                    value={draft.contacts}
                    onChange={noopChange}
                    fieldPermission={READ_ONLY_FIELD_PERMISSION}
                    showHeader={false}
                  />
                ) : (
                  <DetailEmpty />
                )}
              </RecordSection>

              <RecordSection
                title={t('companySites.form.sections.address.title')}
                icon={<MapPin />}
              >
                {draft ? (
                  <AddressesManager
                    value={draft.addresses}
                    onChange={noopChange}
                    fieldPermission={READ_ONLY_FIELD_PERMISSION}
                    showHeader={false}
                    showSiteType
                  />
                ) : (
                  <DetailEmpty />
                )}
              </RecordSection>

              <RecordSection
                title={t('companySites.form.sections.banks.title')}
                icon={<Landmark />}
                full
              >
                <BanksBlock banks={site.banks} />
              </RecordSection>
            </RecordSectionsGrid>
          </RecordCard>
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="company-sites" id={site.id} />
              </RecordSection>
            </RecordCard>
          </div>
        ) : null}
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('companySites.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}

/** The site's banks as a compact name + IBAN list. */
function BanksBlock({ banks }: { banks: CompanySiteBank[] }) {
  if (banks.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-2">
      {banks.map((bank) => (
        <li key={bank.id} className="flex flex-col gap-0.5 text-sm">
          <span className="font-medium text-foreground">{bank.name}</span>
          {bank.iban ? <span className="text-muted-foreground">{bank.iban}</span> : null}
        </li>
      ))}
    </ul>
  )
}
