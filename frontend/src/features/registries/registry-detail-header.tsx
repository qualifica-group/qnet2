import { useTranslation } from 'react-i18next'
import { Building2, Handshake, Truck, Users } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/**
 * Identity band and KPI strip of the anagrafica record card. Kept in one file:
 * both read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

/**
 * Identity band: monogram, name, the card's kind as subtitle, the commercial
 * pills. No edit action: the record edits in place (spec 0200).
 *
 * The pills are the flags a commercial actually scans for — supplier, its
 * qualification, the state of the convenzione — and each is ABSENT when it
 * does not hold, instead of showing "Fornitore: no". A row of negations is
 * noise; the flag rows below are where a flag is set.
 */
export function RegistryDetailHeader({ registry }: { registry: RegistryDetailWithPermissions }) {
  const { t } = useTranslation()
  const cardKind = registry.personal_data
    ? enumLabelOf('personal_data_type', registry.personal_data.type)
    : null

  return (
    <RecordCardHeader
      media={<DetailMonogram name={registry.name} className="size-10 text-base" />}
      title={registry.name}
      subtitle={cardKind}
      badges={
        <>
          {registry.is_supplier ? (
            <Badge variant="secondary" className="gap-1.5">
              <Truck aria-hidden="true" />
              {t('registries.form.isSupplier')}
            </Badge>
          ) : null}
          {registry.is_qualified_supplier ? (
            <Badge variant="outline" className="gap-1.5">
              {t('registries.form.isQualifiedSupplier')}
            </Badge>
          ) : null}
          {registry.agreement_status ? (
            <Badge variant="outline" className="gap-1.5">
              <Handshake aria-hidden="true" />
              {enumLabelOf('agreement_status', registry.agreement_status)}
            </Badge>
          ) : null}
        </>
      }
    />
  )
}

interface RegistryStatsStripProps {
  referents: number
  managers: number
  sectors: number
  employees: number | null
}

/**
 * KPI strip: the four counts that size an anagrafica at a glance — the
 * persisted ones on the detail, the draft's, live, on create (spec 0200).
 *
 * Its labels are its OWN (`registries.detail.stats.*`), not the form's: the
 * sections below already carry "Referenti"/"Settori" on the rows that list the
 * names, and a strip repeating those words would read as the same field twice
 * rather than as a counter of it.
 */
export function RegistryStatsStrip({ referents, managers, sectors, employees }: RegistryStatsStripProps) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat icon={<Users />} label={t('registries.detail.stats.referents')} value={referents} />
      <RecordStat icon={<Users />} label={t('registries.detail.stats.managers')} value={managers} />
      <RecordStat icon={<Building2 />} label={t('registries.detail.stats.sectors')} value={sectors} />
      <RecordStat icon={<Users />} label={t('registries.detail.stats.employees')} value={employees ?? <DetailEmpty />} />
    </RecordStatStrip>
  )
}
