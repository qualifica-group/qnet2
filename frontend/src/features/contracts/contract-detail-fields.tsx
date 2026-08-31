import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, CalendarClock, Contact, CreditCard } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import {
  ContractOpportunityLink,
  ContractQuoteLink,
  NoOpportunityText,
} from '@/features/contracts/contract-related-links'
import type { ContractDetail, ContractRelationRef } from '@/features/contracts/types'
import { formatDate } from '@/lib/formatting/date-display'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/**
 * A lifecycle date plus, when there is one, the person who stamped it — the
 * pairing "validated at / by" and "terminated at / by" both need, and the only
 * shape in this section that is not a bare date.
 */
function StampedDate({ date, by }: { date: string | null; by: ContractRelationRef | null }) {
  const formatted = formatDate(date)

  if (!formatted) {
    return <DetailEmpty />
  }

  return (
    <>
      {formatted}
      {by ? <span className="text-muted-foreground"> — {by.name}</span> : null}
    </>
  )
}

/** A date row that falls back to the kit's empty placeholder — most of the lifecycle section. */
function DateField({ label, value }: { label: string; value: string | null }) {
  return <RecordField label={label}>{formatDate(value) || <DetailEmpty />}</RecordField>
}

/** A relation row that falls back to the kit's empty placeholder. */
function RelationField({
  label,
  icon,
  relation,
}: {
  label: string
  icon?: ReactNode
  relation: { name: string } | null
}) {
  return (
    <RecordField label={label} icon={icon}>
      {relation ? relation.name : <DetailEmpty />}
    </RecordField>
  )
}

interface ContractDetailSectionsProps {
  contract: ContractDetail
}

/**
 * The contract record's `RecordSectionsGrid` body, on the same kit as
 * `QuoteDetailSections` (user directive 2026-08-31: the contract wants the
 * offer's view): comments callout, Cliente e opportunita', Societa' e sedi,
 * Ciclo di vita, Documento e pagamento.
 *
 * Deliberately NOT shown here (same directive): the whole Team block —
 * Supervisore, Commerciale, Segnalatore. The values are not removed —
 * `commercial`/`reporter`/`supervisor` stay on the payload, this screen just
 * does not render them.
 *
 * Every relation here is a LIVE projection through the quote (BR-7/AC-040):
 * nothing below is a persisted column on `contracts`.
 */
export function ContractDetailSections({ contract }: ContractDetailSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      {/* Lo STESSO callout dell'Offerta e dell'Opportunita': il testo libero del
          record si legge PRIMA dei campi strutturati, e si rende da se' nullo
          quando non c'e'. */}
      <GeneralNotesCallout
        title={t('contracts.detail.comments')}
        notes={contract.comments}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSection title={t('contracts.detail.sections.identity')} icon={<Contact />}>
        <RecordFieldList>
          <RelationField label={t('contracts.detail.registry')} relation={contract.registry} />
          {/* I due record correlati sono raggiungibili DAL campo che li nomina
              (direttiva utente 2026-08-31), non da bottoni nella barra azioni.
              Aprono comunque una MODALE, mai un'altra pagina. */}
          <RecordField label={t('contracts.detail.opportunity')}>
            {contract.opportunity ? (
              <ContractOpportunityLink id={contract.opportunity.id} name={contract.opportunity.name} />
            ) : (
              <NoOpportunityText />
            )}
          </RecordField>
          <RecordField label={t('contracts.detail.quote')}>
            <ContractQuoteLink quoteId={contract.quote_id} title={contract.quote.title} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('contracts.detail.sections.company')} icon={<Building2 />}>
        <RecordFieldList>
          <RelationField label={t('contracts.detail.company')} relation={contract.company} />
          <RelationField label={t('contracts.detail.companySite')} relation={contract.company_site} />
          <RecordField label={t('contracts.detail.operationalSite')}>
            {contract.operational_site ? contract.operational_site.label : <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('contracts.detail.sections.lifecycle')} icon={<CalendarClock />}>
        <RecordFieldList>
          {/* D-1: `quotes` non ha una colonna data propria — la data preventivo
              E' il suo `created_at`. */}
          <DateField label={t('contracts.detail.quoteDate')} value={contract.quote.created_at} />
          <DateField label={t('contracts.detail.acceptedAt')} value={contract.accepted_at} />
          <RecordField label={t('contracts.detail.validatedAt')}>
            <StampedDate date={contract.validated_at} by={contract.validated_by} />
          </RecordField>
          <DateField label={t('contracts.detail.renewalDate')} value={contract.renewal_date} />
          <DateField label={t('contracts.detail.expiryDate')} value={contract.expiry_date} />
          <RecordField label={t('contracts.detail.terminatedAt')}>
            <StampedDate date={contract.terminated_at} by={contract.terminated_by} />
          </RecordField>
          {contract.termination_reason ? (
            <RecordField label={t('contracts.detail.terminationReason')}>
              <span className="whitespace-pre-wrap">{contract.termination_reason}</span>
            </RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('contracts.detail.sections.payment')} icon={<CreditCard />}>
        <RecordFieldList>
          <RelationField label={t('contracts.detail.paymentMethod')} relation={contract.payment_method} />
          <RecordField label={t('contracts.detail.paymentNotes')}>
            {contract.payment_notes ? (
              <p className="max-h-40 overflow-y-auto break-words whitespace-pre-wrap">
                {contract.payment_notes}
              </p>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
