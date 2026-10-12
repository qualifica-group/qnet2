import { useTranslation } from 'react-i18next'
import { Contact, MapPin, Package, UserCheck } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { LeadConversionAction } from '@/features/leads/lead-conversion-action'
import type {
  LeadDetailWithPermissions as LeadDetailData,
  LeadLifecycleStatus,
} from '@/features/leads/types'

/**
 * Identity band and KPI strip of the lead record card. Kept in one file: both
 * pieces read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

/** Lifecycle status -> badge hue. The colours the leads grid already uses for the same values. */
const LEAD_STATUS_COLOR: Record<LeadLifecycleStatus, string> = {
  not_associated: 'slate',
  associated: 'blue',
  converted_to_opportunity: 'green',
}

/**
 * Identity band: monogram, the anagrafica as the title (a Lead has no name of
 * its own — D-3, spec 0041 D-1: the identity IS the anagrafica), the campaign
 * as the subtitle, the lifecycle badge and the conversion action. No edit
 * action: the record edits in place (spec 0217).
 */
export function LeadDetailHeader({ lead }: { lead: LeadDetailData }) {
  const { t } = useTranslation()
  const registryName = lead.registry?.name ?? t('leads.detail.unknownRegistry')

  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={registryName}
          icon={<Contact />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={registryName}
      subtitle={lead.campaign ? `${lead.campaign.code} — ${lead.campaign.name}` : undefined}
      badges={<LeadStatusBadge status={lead.lead_status} />}
      actions={<LeadConversionAction leadId={lead.id} opportunity={lead.opportunity} />}
    />
  )
}

/** The lifecycle status as a coloured dot + label, the same pill the grid's status cell wears. */
export function LeadStatusBadge({ status }: { status: LeadLifecycleStatus }) {
  const { t } = useTranslation()
  const color = LEAD_STATUS_COLOR[status]

  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(color))}>
      <span
        className={cn('size-1.5 shrink-0 rounded-full', swatchClassFor(color))}
        aria-hidden="true"
      />
      {t(`enums.lead_lifecycle_status.${status}`)}
    </Badge>
  )
}

interface LeadStatsStripProps {
  operator: string | null
  site: string | null
  source: string | null
  productCount: number
}

/**
 * KPI strip: the four answers to "can this lead be worked, and by whom" —
 * who owns it, out of which Sede, what it is interested in — the persisted
 * ones on the detail, the draft's, live, on create (spec 0217). The lifecycle
 * status is NOT repeated here: it already sits in the identity band above.
 */
export function LeadStatsStrip({ operator, site, source, productCount }: LeadStatsStripProps) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('leads.form.operator')}
        icon={<UserCheck />}
        value={operator ?? <DetailEmpty />}
        hint={operator ? undefined : t('leads.detail.stats.unassignedHint')}
      />
      <RecordStat label={t('leads.form.operationalSite')} icon={<MapPin />} value={site || <DetailEmpty />} />
      <RecordStat label={t('leads.form.source')} value={source ?? <DetailEmpty />} />
      <RecordStat
        label={t('leads.form.sections.productsOfInterest.title')}
        icon={<Package />}
        value={productCount}
      />
    </RecordStatStrip>
  )
}
