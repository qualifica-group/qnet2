import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { History, IdCard, Landmark, MapPin, Phone, Receipt, Star } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import {
  DetailEmpty,
  DetailError,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailLoading,
  DetailMeta,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
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
}

/**
 * Read-only detail of a single company site, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered inside a Sheet. Also hosts the "Società di Default" action
 * (AC-020): shown only when the site is not already the default.
 */
export function CompanySiteDetailView({ companySiteId, onDefaultChange }: CompanySiteDetailProps) {
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
  const card = site.personal_data
  const draft = card ? cardToDraft(card) : null

  return (
    <DetailPanel>
      <DetailHero
        media={<UserAvatar name={site.name} src={site.logo_url} size="xl" />}
        title={site.name}
        subtitle={card?.company_name ?? undefined}
        badges={
          site.is_default ? (
            <Badge variant="secondary">{t('companySites.detail.defaultBadge')}</Badge>
          ) : null
        }
      />

      {canSetDefault && (
        <div className="flex justify-end border-b px-6 py-3">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => void handleSetDefault()}
            disabled={settingDefault}
          >
            <Star aria-hidden="true" />
            {settingDefault ? t('companySites.form.settingDefault') : t('companySites.form.setDefault')}
          </Button>
        </div>
      )}

      <DetailSection title={t('companySites.form.sections.identity.title')} icon={<IdCard />}>
        {card ? (
          <DetailGrid>
            <DetailField label={t('personalData.form.companyName')} full>
              {card.company_name || <DetailEmpty />}
            </DetailField>
            <DetailField label={t('personalData.form.vatNumber')} icon={<Receipt />}>
              {card.vat_number || <DetailEmpty />}
            </DetailField>
            <DetailField label={t('personalData.form.taxCode')} icon={<Receipt />}>
              {card.tax_code || <DetailEmpty />}
            </DetailField>
          </DetailGrid>
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.contacts.title')} icon={<Phone />}>
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
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.address.title')} icon={<MapPin />}>
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
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.banks.title')} icon={<Landmark />}>
        <BanksBlock banks={site.banks} />
      </DetailSection>

      {site.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="company-sites" id={site.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('companySites.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
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
