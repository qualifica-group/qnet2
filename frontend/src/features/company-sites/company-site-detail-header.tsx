import { useTranslation } from 'react-i18next'
import { Building2, Landmark, Pencil, Receipt, Star } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/**
 * Identity band and KPI strip of the company-site record card. Kept in one
 * file: both pieces read the same handful of top-level fields and are always
 * mounted together — the same split `opportunity-detail-header.tsx` makes.
 */

interface CompanySiteDetailHeaderProps {
  site: CompanySiteDetailWithPermissions
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
  /** Promotes this site to the organization's default one; absent when the actor may not, or it already is. */
  onSetDefault?: () => void
  isSettingDefault?: boolean
}

/**
 * Identity band: logo, site name, the owning company as subtitle, the "default"
 * pill and the two actions this record offers. The pill is ABSENT when the site
 * is not the default — the bar states what this site IS, not what it is not.
 */
export function CompanySiteDetailHeader({
  site,
  onEdit,
  onSetDefault,
  isSettingDefault = false,
}: CompanySiteDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && site.permissions.resource.update

  return (
    <RecordCardHeader
      media={<UserAvatar name={site.name} src={site.logo_url} size="lg" />}
      title={site.name}
      subtitle={site.personal_data?.company_name ?? undefined}
      badges={
        site.is_default ? (
          <Badge variant="secondary">{t('companySites.detail.defaultBadge')}</Badge>
        ) : null
      }
      actions={
        <>
          {onSetDefault ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={onSetDefault}
              disabled={isSettingDefault}
            >
              <Star aria-hidden="true" />
              {isSettingDefault
                ? t('companySites.form.settingDefault')
                : t('companySites.form.setDefault')}
            </Button>
          ) : null}
          {canEdit ? (
            <Button size="sm" onClick={onEdit}>
              <Pencil aria-hidden="true" />
              {t('common.edit')}
            </Button>
          ) : null}
        </>
      }
    />
  )
}

interface CompanySiteDetailStatsProps {
  site: CompanySiteDetailWithPermissions
}

/** KPI strip: the fiscal identity of the site plus how many banks hang off it. */
export function CompanySiteDetailStats({ site }: CompanySiteDetailStatsProps) {
  const { t } = useTranslation()
  const card = site.personal_data

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('personalData.form.vatNumber')}
        icon={<Receipt />}
        value={card?.vat_number || <DetailEmpty />}
      />
      <RecordStat
        label={t('personalData.form.taxCode')}
        icon={<Receipt />}
        value={card?.tax_code || <DetailEmpty />}
      />
      <RecordStat
        label={t('companySites.form.sections.banks.title')}
        icon={<Landmark />}
        value={site.banks.length}
      />
      <RecordStat
        label={t('companySites.form.company')}
        icon={<Building2 />}
        value={site.company?.label || <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
