import { useTranslation } from 'react-i18next'
import { Globe, Map, MapPin, MapPinned, Pencil } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'

/**
 * Identity band and KPI strip of the operational-site record card. Kept in one
 * file: both pieces read the same handful of top-level fields and are always
 * mounted together — the same split `opportunity-detail-header.tsx` makes.
 */

interface OperationalSiteDetailHeaderProps {
  operationalSite: OperationalSiteDetailWithPermissions
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band. A site has no name of its own — it IS its address — so the
 * alias headlines it when there is one and the street does otherwise, exactly
 * as the previous card decided.
 *
 * Deliberately NO subtitle: the street would have to be it, and the address
 * section already shows the street under precisely the same condition (only
 * when an alias took the title). A subtitle here printed it twice.
 */
export function OperationalSiteDetailHeader({
  operationalSite,
  onEdit,
}: OperationalSiteDetailHeaderProps) {
  const { t } = useTranslation()
  const title = operationalSite.alias ?? operationalSite.line1
  const canEdit = Boolean(onEdit) && operationalSite.permissions.resource.update

  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={title}
          icon={<MapPin />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={title}
      actions={
        canEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

interface OperationalSiteDetailStatsProps {
  operationalSite: OperationalSiteDetailWithPermissions
}

/**
 * KPI strip: WHERE the site is, comune-first — the same order the address form
 * reads in (directive 2026-09-10: the Comune is the primary fact, the ancestors
 * are context). The street and the CAP stay in the section below: they are the
 * address of record, not the at-a-glance answer.
 */
export function OperationalSiteDetailStats({ operationalSite }: OperationalSiteDetailStatsProps) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('operationalSites.detail.city')}
        icon={<MapPinned />}
        value={operationalSite.city?.name ?? <DetailEmpty />}
      />
      <RecordStat
        label={t('operationalSites.detail.province')}
        icon={<Map />}
        value={operationalSite.province?.name ?? <DetailEmpty />}
      />
      <RecordStat
        label={t('operationalSites.detail.region')}
        value={operationalSite.region?.name ?? <DetailEmpty />}
      />
      <RecordStat
        label={t('operationalSites.detail.country')}
        icon={<Globe />}
        value={operationalSite.country?.name ?? <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
