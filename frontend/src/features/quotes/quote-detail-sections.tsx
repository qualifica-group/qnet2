import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Briefcase, Building2, Contact, CreditCard, Handshake, UserRound, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard, type UserProfileSummary } from '@/components/user-profile-hover-card'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { QuoteDetailAttributesSection } from '@/features/quotes/quote-detail-attributes'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { managerPositionLabel } from '@/features/shared/manager-position-label'
import type { ProductLine } from '@/features/product-lines/types'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** A `RecordField` whose value carries an avatar: centers on the label instead of sitting on its baseline. */
const PERSON_ROW_CLASS = '@md:items-center'

/** Stable empty default: a missing key on older fixtures reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []

/**
 * A team member's row: avatar + name behind the app's shared
 * `UserProfileHoverCard`, exactly as the Opportunity record renders its own
 * team. Only for the roles backed by a USER (`supervisor`, `managers[]`) —
 * `commercial`/`reporter` are `referents` FKs (D-3) with no profile to open.
 */
function TeamPerson({ user }: { user: UserProfileSummary }) {
  return (
    <UserProfileHoverCard user={user} triggerClassName="rounded-md">
      <UserAvatar name={user.name} src={user.avatar_url ?? null} className="shrink-0" />
      <span className="truncate text-sm text-foreground">{user.name}</span>
    </UserProfileHoverCard>
  )
}

interface QuoteDetailSectionsProps {
  quote: QuoteDetailWithPermissions
}

/**
 * The offer record's `RecordSectionsGrid` body: internal notes callout,
 * context, team, company/sites, document & payment, and the collected
 * Attribute values — each an independent `RecordSection`, mirroring
 * `OpportunityDetailSections` so the two records read as one product.
 */
export function QuoteDetailSections({ quote }: QuoteDetailSectionsProps) {
  const { t } = useTranslation()
  // Ordinati per slot, non per come il server li ha restituiti: la posizione
  // E' il ruolo, quindi l'ordine di lettura deve seguirla.
  const sortedManagers = [...(quote.managers ?? [])].sort((a, b) => a.position - b.position)
  const rewards = quote.rewards ?? []

  return (
    <RecordSectionsGrid>
      {/* Lo STESSO callout che il campo indossa nel form (user directive
          2026-08-06): stesso componente, stesso colore, prima riga della
          griglia — e' il testo che l'operatore legge PRIMA dei campi
          strutturati. Si rende da se' nullo quando non c'e' nessuna nota. */}
      <GeneralNotesCallout
        title={t('quotes.form.internalNotes')}
        notes={quote.internal_notes}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSection title={t('quotes.detail.sections.context')} icon={<Handshake />}>
        <RecordFieldList>
          <RecordField label={t('quotes.detail.opportunity')}>
            {/* Il record padre e' raggiungibile da qui: risalire all'Opportunita'
                e' il movimento piu' frequente da un'Offerta. */}
            <Link
              to={`/opportunities/${quote.opportunity_id}`}
              className="rounded-sm font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
            >
              {quote.opportunity.name}
            </Link>
          </RecordField>
          {/* Lo stato NON si ripete qui: e' gia' la pill dell'header. Sotto,
              il contesto che l'Offerta EREDITA dall'Opportunita' e non possiede
              (richiesta utente 2026-08-31), proiezione read-only. */}
          <RecordField label={t('quotes.detail.source')}>
            {quote.source ? quote.source.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.productLines')}>
            <ProductLinesReadOnlyList lines={quote.product_lines ?? EMPTY_PRODUCT_LINES} />
          </RecordField>
          <RecordField label={t('quotes.detail.opportunityGeneralNotes')}>
            {quote.general_notes ? (
              // `whitespace-pre-wrap`: gli operatori incollano note su piu'
              // righe; capped e scrollabile cosi' una nota lunga non spinge via
              // il resto della sezione.
              <p className="max-h-40 overflow-y-auto break-words whitespace-pre-wrap">
                {quote.general_notes}
              </p>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {/* Richiesta utente 2026-08-31: la stessa sezione che porta la scheda
          Opportunita' — anagrafica, referente e i due ruoli commerciali. I
          primi due sono una proiezione READ-ONLY del record padre
          (`QuoteResource.registry`/`referent`), gli altri due sono lo snapshot
          dell'Offerta (D-3). */}
      <RecordSection title={t('quotes.detail.sections.identity')} icon={<Contact />}>
        <RecordFieldList>
          <RecordField label={t('quotes.detail.registry')}>
            {quote.registry ? quote.registry.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.referent')}>
            {quote.referent ? quote.referent.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.commercial')} icon={<Briefcase />}>
            {quote.commercial ? quote.commercial.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.reporter')} icon={<UserRound />}>
            {quote.reporter ? quote.reporter.name : <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {/* Spec 0059 D-3, origine Offerta: i buoni assegnati al Segnalatore di
          QUESTA offerta. Sezione propria (richiesta utente 2026-08-31), la
          STESSA che rende la scheda Opportunita'. */}
      <RewardChipsSection title={t('quotes.detail.rewards')} rewards={rewards} />

      {/* Il Team e' SOLO chi gestisce l'offerta: Supervisore e Gestori Account
          (richiesta utente 2026-08-31). Commerciale e Segnalatore stanno con
          l'anagrafica, come sulla scheda Opportunita'. */}
      <RecordSection title={t('quotes.form.sections.team.title')} icon={<Users />}>
        <RecordFieldList>
          <RecordField label={t('quotes.detail.supervisor')} className={PERSON_ROW_CLASS}>
            {quote.supervisor ? <TeamPerson user={quote.supervisor} /> : <DetailEmpty />}
          </RecordField>
          {/* Spec 0087: i Gestori Account dell'Offerta, subito dopo il
              Supervisore. L'etichetta di ogni riga e' quella risolta dalla
              categoria prodotto (spec 0080), non un "G.A. n" generico.
              `position` e' la chiave, non `id`: e' lo slot a essere unico, la
              stessa persona puo' occuparne due. */}
          {sortedManagers.length > 0 ? (
            sortedManagers.map((manager) => (
              <RecordField
                key={manager.position}
                label={managerPositionLabel(t, manager.position, quote.manager_labels)}
                className={PERSON_ROW_CLASS}
              >
                <TeamPerson user={manager} />
              </RecordField>
            ))
          ) : (
            <RecordField label={t('quotes.form.managers')}>
              <DetailEmpty />
            </RecordField>
          )}
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('quotes.form.sections.sites.title')} icon={<Building2 />}>
        <RecordFieldList>
          <RecordField label={t('quotes.detail.company')}>
            {quote.company ? quote.company.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.companySite')}>
            {quote.company_site ? quote.company_site.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.operationalSite')}>
            {quote.operational_site ? quote.operational_site.label : <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('quotes.detail.sections.document')} icon={<CreditCard />}>
        <RecordFieldList>
          <RecordField label={t('quotes.detail.layout')}>
            {quote.layout ? quote.layout.name : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('quotes.detail.paymentMethod')}>
            {quote.payment_method ? quote.payment_method.name : <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <QuoteDetailAttributesSection
        attributes={quote.applicable_attributes}
        values={quote.attribute_values}
        className={FULL_WIDTH_SECTION_CLASS}
      />
    </RecordSectionsGrid>
  )
}
