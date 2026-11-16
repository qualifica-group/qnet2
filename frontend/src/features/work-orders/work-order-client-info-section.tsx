import { useTranslation } from 'react-i18next'
import { Building } from 'lucide-react'
import { ContactChips } from '@/components/detail/contact-chips'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { ReferentValue } from '@/features/referents/referent-value'
import type { WorkOrderClientInfo } from '@/features/work-orders/types'

/** Legacy "Referente 1" and "Referente 2": both rows always shown, empty when missing. */
const REFERENT_SLOTS = [1, 2] as const

interface WorkOrderClientInfoSectionProps {
  registry: { id: number; name: string }
  info: WorkOrderClientInfo
}

/** Tags or sectors as compact chips, the empty marker without any. */
function ChipList({ items }: { items: string[] }) {
  if (items.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-wrap gap-1">
      {items.map((item) => (
        <li key={item}>
          <Badge variant="secondary" className="font-normal">
            {item}
          </Badge>
        </li>
      ))}
    </ul>
  )
}

/** Legal seat with the fiscal ids under it, as the legacy "Sede legale" row. */
function LegalSeatValue({ info }: { info: WorkOrderClientInfo }) {
  const { t } = useTranslation()
  const fiscalIds = [
    info.vat_number ? `${t('workOrders.detail.clientInfo.vatNumber')} ${info.vat_number}` : null,
    info.tax_code ? `${t('workOrders.detail.clientInfo.taxCode')} ${info.tax_code}` : null,
  ].filter((part): part is string => part !== null)

  if (!info.legal_address && fiscalIds.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className="flex min-w-0 flex-col gap-0.5">
      {info.legal_address ? <span>{info.legal_address}</span> : null}
      {fiscalIds.length > 0 ? <span className="text-xs text-muted-foreground">{fiscalIds.join(' - ')}</span> : null}
    </div>
  )
}

/**
 * "Info cliente" of the commessa (spec 0400), the legacy "Gestione commessa"
 * left block: Azienda, Sede legale (with VAT number and tax code), Tag,
 * Email/PEC, Telefono, Referente 1 and 2, Commerciale, Settore, Commenti.
 * Read-only: everything belongs to the anagrafica and is edited there.
 */
export function WorkOrderClientInfoSection({ registry, info }: WorkOrderClientInfoSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('workOrders.detail.clientInfo.title')} icon={<Building />}>
      <RecordFieldList>
        <RecordField label={t('workOrders.detail.clientInfo.company')}>
          <RecordLink domain="registries" id={registry.id}>
            {registry.name}
          </RecordLink>
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.legalSeat')}>
          <LegalSeatValue info={info} />
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.tags')}>
          <ChipList items={info.tags} />
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.emails')}>
          {info.emails.length > 0 ? <ContactChips contacts={info.emails} /> : <DetailEmpty />}
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.phones')}>
          {info.phones.length > 0 ? <ContactChips contacts={info.phones} /> : <DetailEmpty />}
        </RecordField>
        {REFERENT_SLOTS.map((slot, index) => (
          <RecordField key={slot} label={t('workOrders.detail.clientInfo.referent', { slot })}>
            <ReferentValue referent={info.referents[index] ?? null} />
          </RecordField>
        ))}
        <RecordField label={t('workOrders.detail.clientInfo.commercial')}>
          <ReferentValue referent={info.commercial} />
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.sectors')}>
          <ChipList items={info.sectors} />
        </RecordField>
        <RecordField label={t('workOrders.detail.clientInfo.comments')}>
          {info.comments ? <span className="whitespace-pre-line">{info.comments}</span> : <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}
