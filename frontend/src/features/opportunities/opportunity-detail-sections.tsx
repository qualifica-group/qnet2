import { useTranslation } from 'react-i18next'
import { Building2, Contact, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import type {
  OpportunityDetailWithPermissions as OpportunityDetailData,
  OpportunityProductOfInterest,
} from '@/features/opportunities/types'
import { managerPositionLabel } from '@/features/shared/manager-position-label'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'


/** Stable empty defaults (spec 0049 D-8): a missing key on older fixtures reads the same as `[]`/`{}`. */
const EMPTY_PRODUCTS_OF_INTEREST: OpportunityProductOfInterest[] = []


/**
 * Read-only list of the opportunity's "prodotti di interesse" (user directive
 * 2026-07-22), each with the category it belongs to — the same pairing the
 * picker shows while selecting them.
 */
function ProductsOfInterestList({ products }: { products: OpportunityProductOfInterest[] }) {
  if (products.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {products.map((product) => (
        <li key={product.id}>
          <span className="font-medium">{product.name}</span>
          {product.product_category ? (
            <span className="text-muted-foreground"> — {product.product_category.name}</span>
          ) : null}
        </li>
      ))}
    </ul>
  )
}

interface OpportunityDetailSectionsProps {
  opportunity: OpportunityDetailData
}

/**
 * The record's `RecordSectionsGrid` body: registry/contacts, classification,
 * team, rewards, general notes and the collected-Attribute values — each an
 * independent `RecordSection`, absent when its own data is empty (spec 0064
 * enterprise-CRM record layout).
 */
export function OpportunityDetailSections({ opportunity }: OpportunityDetailSectionsProps) {
  const { t } = useTranslation()
  const sortedManagers = [...opportunity.managers].sort((a, b) => a.position - b.position)
  const rewards = opportunity.rewards ?? []

  return (
    <RecordSectionsGrid>
      {/*
        Lo STESSO callout del form (user directive 2026-08-06), non una resa
        propria: il componente e il colore sono quelli che il campo indossa
        mentre lo si scrive. Prima riga della griglia, a tutta larghezza — la
        stessa posizione che occupa in cima alla colonna laterale del form e del
        work panel: e' il testo che l'operatore legge PRIMA di scorrere i campi
        strutturati. Il callout porta gia' il proprio micro-titolo, quindi non
        sta dentro una `RecordSection` (sarebbero due intestazioni sullo stesso
        blocco) e si rende da se' nulla quando non ci sono note.
      */}
      <GeneralNotesCallout
        title={t('opportunities.form.sections.generalNotes.title')}
        notes={opportunity.general_notes}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSection title={t('opportunities.form.sections.identity.title')} icon={<Contact />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.registry')}>
            {opportunity.registry?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.referent')}>
            {opportunity.referent?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.commercial')}>
            {opportunity.commercial?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.reporter')}>
            {opportunity.reporter?.name ?? <DetailEmpty />}
          </RecordField>
          {opportunity.lead ? (
            <RecordField label={t('opportunities.detail.sourceLead')}>{opportunity.lead.label}</RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>

      {/*
        Sede operativa is HIDDEN from this section for the same reason it is
        hidden in the form (user directive 2026-08-05): it only carries
        meaning in Gestione Richieste. The value is not removed —
        `operational_site` stays on the payload and survives every save.
      */}
      <RecordSection title={t('opportunities.form.sections.classification.title')} icon={<Building2 />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.source')}>
            {opportunity.source?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.sections.productLines.title')}>
            <ProductLinesReadOnlyList lines={opportunity.product_lines} />
          </RecordField>
          <RecordField label={t('products.ofInterest.sectionTitle')}>
            <ProductsOfInterestList
              products={opportunity.products_of_interest ?? EMPTY_PRODUCTS_OF_INTEREST}
            />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {/*
        Team = "un ruolo, una persona", una riga per ciascuno. Il Supervisore e
        i G.A. sono la stessa cosa (una persona con una denominazione), quindi
        stanno nella STESSA `RecordFieldList` delle altre sezioni: colonna di
        etichette allineata, filetti fra le righe, avatar su ogni riga. Prima
        convivevano due idiomi diversi — il Supervisore come riga spec-sheet e i
        G.A. come lista a se' con un micro-titolo proprio e l'etichetta di ruolo
        troncata a `max-w-28` dietro un `title` (invisibile su touch, la stessa
        ragione per cui spec 0080 la vuole come testo VISIBILE).
      */}
      <RecordSection title={t('opportunities.form.sections.team.title')} icon={<Users />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.supervisor')} className={RECORD_PERSON_ROW_CLASS}>
            {opportunity.supervisor ? <RecordPerson user={opportunity.supervisor} /> : <DetailEmpty />}
          </RecordField>

          {sortedManagers.length > 0 ? (
            sortedManagers.map((manager) => (
              // `position` e' la chiave, non `id`: e' lo slot a essere unico —
              // la stessa persona puo' occupare due G.A. diversi.
              <RecordField
                key={manager.position}
                label={managerPositionLabel(t, manager.position, opportunity.manager_labels)}
                className={RECORD_PERSON_ROW_CLASS}
              >
                <RecordPerson user={manager} />
              </RecordField>
            ))
          ) : (
            <RecordField label={t('opportunities.form.managers')}>
              <DetailEmpty />
            </RecordField>
          )}
        </RecordFieldList>
      </RecordSection>

      {/* La STESSA sezione che rende la scheda Offerta (richiesta utente
          2026-08-31): un solo componente, cosi' i due record non possono
          divergere sul blocco buoni. */}
      <RewardChipsSection title={t('opportunities.detail.rewards')} rewards={rewards} />
    </RecordSectionsGrid>
  )
}
