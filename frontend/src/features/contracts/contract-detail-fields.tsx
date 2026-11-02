import { useTranslation } from 'react-i18next'
import { Building2, CalendarClock, FileSignature } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordSkeletonSections } from '@/components/detail/record-skeleton-sections'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { ContractCommentsField, ContractDateField, ContractPaymentNotesField } from '@/features/contracts/contract-fields'
import {
  ContractOpportunityLink,
  ContractQuoteLink,
  NoOpportunityText,
} from '@/features/contracts/contract-related-links'
import type { ContractDetail, ContractRelationRef } from '@/features/contracts/types'
import type { ContractDetailEditor } from '@/features/contracts/use-contract-inline-edit'
import {
  ProjectedContactsSection,
  ProjectedContextSection,
  ProjectedDocumentSection,
  ProjectedTeamSection,
} from '@/features/quotes/quote-projection-sections'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
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

/**
 * A relation row linking to the related record, or the kit's empty placeholder.
 */
function RelationField({
  label,
  relation,
  domain,
}: {
  label: string
  relation: { id: number; name: string } | null
  domain: string
}) {
  return (
    <RecordField label={label}>
      {relation ? (
        <RecordLink domain={domain} id={relation.id}>
          {relation.name}
        </RecordLink>
      ) : (
        <DetailEmpty />
      )}
    </RecordField>
  )
}

interface ContractDetailSectionsProps {
  contract: ContractDetail
  editor: ContractDetailEditor
  /** The contract may still be edited: `contracts.update` and a working status group (spec 0219 D-3). */
  canEdit: boolean
}

/**
 * The contract record's `RecordSectionsGrid` body, on the shared record
 * skeleton (spec 0331): comments callout, then Dati contratto, Contesto,
 * Anagrafica e contatti, Buoni, Team, Societa' e sedi, Documento e pagamento
 * and, as the record's own block, Ciclo di vita.
 *
 * Everything but the four fields the contract owns is a LIVE, read-only
 * projection through the quote (BR-7/AC-040): the offer's Team included
 * (Supervisore and Gestori account, user decision 2026-10-29, superseding the
 * 2026-08-31 directive that hid it). The four owned fields (comments, renewal
 * and expiry dates, payment notes) edit in place (spec 0219): there is no
 * "Modifica dati" dialog any more.
 */
export function ContractDetailSections({ contract, editor, canEdit }: ContractDetailSectionsProps) {
  const { t } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSectionsGrid>
      {/* Lo STESSO callout dell'Offerta e dell'Opportunita': il testo libero del
          record si legge PRIMA dei campi strutturati, e si rende da se' nullo
          quando non c'e'. */}
      <NotesCalloutRow
        field="comments"
        title={t('contracts.detail.comments')}
        notes={contract.comments}
        placeholder={t('contracts.detail.commentsPlaceholder')}
        inline={inline}
        editor={<ContractCommentsField control={form.control} />}
        canEdit={canEdit}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSkeletonSections
        details={
          <RecordSection title={t('contracts.detail.sections.data')} icon={<FileSignature />}>
            <RecordFieldList>
              {/* I due record correlati sono raggiungibili DAL campo che li nomina
                  (direttiva utente 2026-08-31), non da bottoni nella barra azioni.
                  Aprono comunque una MODALE, mai un'altra pagina. */}
              <RecordField label={t('contracts.detail.quote')}>
                <ContractQuoteLink quoteId={contract.quote_id} title={contract.quote.title} />
              </RecordField>
              <RecordField label={t('contracts.detail.opportunity')}>
                {contract.opportunity ? (
                  <ContractOpportunityLink id={contract.opportunity.id} name={contract.opportunity.name} />
                ) : (
                  <NoOpportunityText />
                )}
              </RecordField>
              {/* D-1: `quotes` non ha una colonna data propria — la data preventivo
                  E' il suo `created_at`. */}
              <DateField label={t('contracts.detail.quoteDate')} value={contract.quote.created_at} />
            </RecordFieldList>
          </RecordSection>
        }
        context={<ProjectedContextSection source={contract.source} productLines={contract.product_lines} />}
        contacts={
          <ProjectedContactsSection
            registry={contract.registry}
            referent={contract.referent}
            commercial={contract.commercial}
            reporter={contract.reporter}
          />
        }
        rewards={
          <RewardChipsSection
            title={t('contracts.detail.sections.rewards')}
            rewards={contract.rewards}
            emptyLabel={t('common.noRewards')}
          />
        }
        team={
          <ProjectedTeamSection
            supervisor={contract.supervisor}
            managers={contract.managers}
            managerLabels={contract.manager_labels}
          />
        }
        company={
          <RecordSection title={t('contracts.detail.sections.company')} icon={<Building2 />}>
            <RecordFieldList>
              <RelationField label={t('contracts.detail.company')} relation={contract.company} domain="companies" />
              <RelationField
                label={t('contracts.detail.companySite')}
                relation={contract.company_site}
                domain="company-sites"
              />
              <RecordField label={t('contracts.detail.operationalSite')}>
                {contract.operational_site ? (
                  <RecordLink domain="operational-sites" id={contract.operational_site.id}>
                    {contract.operational_site.label}
                  </RecordLink>
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
        }
        document={
          <ProjectedDocumentSection layout={contract.layout} paymentMethod={contract.payment_method}>
            <RecordInlineField
              field="payment_notes"
              label={t('contracts.detail.paymentNotes')}
              inline={inline}
              canEdit={canEdit}
              editor={<ContractPaymentNotesField control={form.control} />}
            >
              {contract.payment_notes ? (
                <p className="max-h-40 overflow-y-auto break-words whitespace-pre-wrap">{contract.payment_notes}</p>
              ) : (
                <DetailEmpty />
              )}
            </RecordInlineField>
          </ProjectedDocumentSection>
        }
        specific={
          <RecordSection title={t('contracts.detail.sections.lifecycle')} icon={<CalendarClock />}>
            <RecordFieldList>
              <DateField label={t('contracts.detail.acceptedAt')} value={contract.accepted_at} />
              <RecordField label={t('contracts.detail.validatedAt')}>
                <StampedDate date={contract.validated_at} by={contract.validated_by} />
              </RecordField>
              <RecordInlineField
                field="renewal_date"
                label={t('contracts.detail.renewalDate')}
                inline={inline}
                canEdit={canEdit}
                editor={
                  <ContractDateField control={form.control} name="renewal_date" label={t('contracts.detail.renewalDate')} />
                }
              >
                {formatDate(contract.renewal_date) || <DetailEmpty />}
              </RecordInlineField>
              <RecordInlineField
                field="expiry_date"
                label={t('contracts.detail.expiryDate')}
                inline={inline}
                canEdit={canEdit}
                editor={
                  <ContractDateField control={form.control} name="expiry_date" label={t('contracts.detail.expiryDate')} />
                }
              >
                {formatDate(contract.expiry_date) || <DetailEmpty />}
              </RecordInlineField>
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
        }
      />
    </RecordSectionsGrid>
  )
}
