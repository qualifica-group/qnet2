import { useTranslation } from 'react-i18next'
import { Building2, Handshake, Pencil, Truck, Users } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { enumLabelOf } from '@/features/config/enum-label'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/**
 * Identity band and KPI strip of the anagrafica record card. Kept in one file:
 * both read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

interface RegistryDetailHeaderProps {
  registry: RegistryDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band: monogram, name, the card's kind as subtitle, the commercial
 * pills, edit action.
 *
 * The pills are the flags a commercial actually scans for — supplier, its
 * qualification, the state of the convenzione — and each is ABSENT when it
 * does not hold, instead of showing "Fornitore: no". A row of negations is
 * noise; the form is where a flag is set, not the card.
 */
export function RegistryDetailHeader({ registry, onEdit }: RegistryDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && registry.permissions.resource.update
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

/**
 * KPI strip: the four counts that size an anagrafica at a glance.
 *
 * Its labels are its OWN (`registries.detail.stats.*`), not the form's: the
 * sections below already carry "Referenti"/"Settori" on the rows that list the
 * names, and a strip repeating those words would read as the same field twice
 * rather than as a counter of it.
 */
export function RegistryDetailStats({ registry }: { registry: RegistryDetailWithPermissions }) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat
        icon={<Users />}
        label={t('registries.detail.stats.referents')}
        value={registry.referents.length}
      />
      <RecordStat
        icon={<Users />}
        label={t('registries.detail.stats.managers')}
        value={registry.managers.length}
      />
      <RecordStat
        icon={<Building2 />}
        label={t('registries.detail.stats.sectors')}
        value={registry.sectors.length}
      />
      <RecordStat
        icon={<Users />}
        label={t('registries.detail.stats.employees')}
        value={registry.employee_count ?? <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
