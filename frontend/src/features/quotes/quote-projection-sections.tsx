import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Briefcase, Contact, CreditCard, Handshake, UserRound, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { UserProfileSummary } from '@/components/user-profile-hover-card'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { ProductLine } from '@/features/product-lines/types'
import { LinkedValue } from '@/features/quotes/quote-record-company'
import type { QuoteManagerRef } from '@/features/quotes/types'
import { ReferentValue } from '@/features/referents/referent-value'
import type { ReferentRef } from '@/features/referents/types'
import { managerPositionLabel } from '@/features/shared/manager-position-label'

/**
 * Read-only sections of data an Offerta owns and other records only PROJECT
 * from it (spec 0331 D-1): the Offerta detail's Contesto, and the Contratto
 * and Commessa details' Contesto, Anagrafica e contatti, Team offerta and
 * Documento e pagamento. They reuse the `quotes.detail.*` labels so the same
 * section reads the same everywhere.
 */

/** Stable empty defaults: a missing key on older payloads reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []
const EMPTY_MANAGERS: QuoteManagerRef[] = []

interface ProjectedContextSectionProps {
  source: RelationFieldRef | null | undefined
  productLines: ProductLine[] | undefined
}

/** Contesto: what the record inherits from the Opportunita' and does not own (Fonte, Linee prodotto). */
export function ProjectedContextSection({ source, productLines }: ProjectedContextSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.detail.sections.context')} icon={<Handshake />}>
      <RecordFieldList>
        <RecordField label={t('quotes.detail.source')}>{source ? source.name : <DetailEmpty />}</RecordField>
        <RecordField label={t('quotes.detail.productLines')}>
          <ProductLinesReadOnlyList lines={productLines ?? EMPTY_PRODUCT_LINES} />
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface ProjectedContactsSectionProps {
  registry: RelationFieldRef | null | undefined
  referent: ReferentRef | null | undefined
  commercial: RelationFieldRef | null | undefined
  reporter: RelationFieldRef | null | undefined
}

/** Anagrafica e contatti: Anagrafica, Referente (with its Tipo), Commerciale and Segnalatore. */
export function ProjectedContactsSection({ registry, referent, commercial, reporter }: ProjectedContactsSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.detail.sections.identity')} icon={<Contact />}>
      <RecordFieldList>
        <RecordField label={t('quotes.detail.registry')}>
          <LinkedValue domain="registries" record={registry ?? null} />
        </RecordField>
        <RecordField label={t('quotes.detail.referent')}>
          <ReferentValue referent={referent ?? null} />
        </RecordField>
        <RecordField label={t('quotes.detail.commercial')} icon={<Briefcase />}>
          <LinkedValue domain="referents" record={commercial ?? null} />
        </RecordField>
        <RecordField label={t('quotes.detail.reporter')} icon={<UserRound />}>
          <LinkedValue domain="referents" record={reporter ?? null} />
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface ProjectedTeamSectionProps {
  supervisor: UserProfileSummary | null | undefined
  managers: QuoteManagerRef[] | undefined
  managerLabels: Record<string, string> | undefined
}

/** Team of the Offerta (Supervisore and Gestori account, each G.A. under its slot label), without editors. */
export function ProjectedTeamSection({ supervisor, managers, managerLabels }: ProjectedTeamSectionProps) {
  const { t } = useTranslation()
  // Ordered by slot, not by how the server returned them: the position IS the role.
  const sortedManagers = [...(managers ?? EMPTY_MANAGERS)].sort((a, b) => a.position - b.position)

  return (
    <RecordSection title={t('quotes.form.sections.team.title')} icon={<Users />}>
      <RecordFieldList>
        <RecordField label={t('quotes.detail.supervisor')} className={RECORD_PERSON_ROW_CLASS}>
          {supervisor ? <RecordPerson user={supervisor} /> : <DetailEmpty />}
        </RecordField>
        <RecordField label={t('quotes.form.managers')}>
          {sortedManagers.length > 0 ? (
            <ul className="flex min-w-0 flex-col gap-1.5">
              {sortedManagers.map((manager) => (
                // `position` is the key, not `id`: the slot is unique, the same person may fill two.
                <li key={manager.position} className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-xs text-muted-foreground">
                    {managerPositionLabel(t, manager.position, managerLabels)}
                  </span>
                  <RecordPerson user={manager} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface ProjectedDocumentSectionProps {
  layout: RelationFieldRef | null | undefined
  paymentMethod: RelationFieldRef | null | undefined
  /** Extra rows of the record itself (e.g. the Contratto's editable payment notes). */
  children?: ReactNode
}

/** Documento e pagamento: the offer's Layout and Modalita' di pagamento, then the record's own rows. */
export function ProjectedDocumentSection({ layout, paymentMethod, children }: ProjectedDocumentSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.detail.sections.document')} icon={<CreditCard />}>
      <RecordFieldList>
        <RecordField label={t('quotes.detail.layout')}>{layout ? layout.name : <DetailEmpty />}</RecordField>
        <RecordField label={t('quotes.detail.paymentMethod')}>
          {paymentMethod ? paymentMethod.name : <DetailEmpty />}
        </RecordField>
        {children}
      </RecordFieldList>
    </RecordSection>
  )
}
