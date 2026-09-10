import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Form, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { QuoteDynamicFieldsSection } from '@/features/quotes/quote-dynamic-fields-section'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteLine } from '@/features/quotes/types'
import { RequestOfferLinesSection } from '@/features/request-management/request-offer-lines-section'
import { RequestCreateAttributionSection } from '@/features/request-management/request-create-attribution-section'
import { RequestCreateTeamSection } from '@/features/request-management/request-create-team-section'
import { RequestCreateCallbackSection } from '@/features/request-management/request-create-callback-section'
import { RequestCreateClientSection } from '@/features/request-management/request-create-client-section'
import { RequestCreateSiteSection } from '@/features/request-management/request-create-site-section'
import { RequestCreateGeneralNotes } from '@/features/request-management/request-create-general-notes'
import { RequestCreateHeader } from '@/features/request-management/request-create-header'
import { RequestCreateSummary } from '@/features/request-management/request-create-summary'
import { useAbilities } from '@/features/auth/use-abilities'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'
import {
  ASSIGN_OPERATOR_PERMISSION,
  OPERATIONAL_SITES_VIEW_ANY_PERMISSION,
} from '@/features/request-management/use-request-actor-defaults'
import { useRequestSiteOperatorLink } from '@/features/request-management/use-request-site-operator-link'

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the work panel does (`REQUEST_WORK_FORM_ID`). The same id serves
 * the footer actions (user directive 2026-08-03), so both copies of the button
 * submit this form without either of them nesting the other.
 */
const REQUEST_CREATE_FORM_ID = 'request-create-form'

/** Hoisted: nothing is persisted on a create, and an inline `[]` would be a fresh reference per render. */
const NO_PERSISTED_LINES: QuoteLine[] = []

/**
 * The side column is no longer read-only chrome: it now carries two EDITABLE
 * sections (user directive 2026-09-10). `@container` makes their field grids
 * measure THIS column instead of the whole panel — without it `FIELD_GRID_CLASS`
 * would resolve `@2xl` against the panel and split it in two.
 *
 * The track is widened to 24rem for THIS screen only (user directive
 * 2026-09-10, "stringi un po il form body principale e allarga un po la side"):
 * the main column is the grid's `1fr`, so the four rem come off it. The
 * override is local, not a change to the shared primitive, because the work
 * panel's side column carries read-only chrome and does not need them. Still
 * under the 24rem the team's slot rows fold at (`ManagerSlotsField`'s own
 * `@container`): 24rem MINUS the card padding is what that field measures.
 */
const CREATE_SIDE_COLUMN_CLASS = cn(SIDE_COLUMN_CLASS, '@container')
const CREATE_PANEL_GRID_CLASS = cn(PANEL_GRID_CLASS, '@4xl:grid-cols-[minmax(0,1fr)_24rem]')

const ERROR_BANNER_CLASS =
  'flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive'

interface RequestCreateFormProps {
  onSuccess: (id: number) => void
  onCancel: () => void
}

/**
 * Create-only form for the request-management module (spec 0057, D-7), built
 * as the work panel's TWIN (user directive 2026-07-31, "la scheda di creazione
 * il piu' simile possibile a quella di gestione"). Not a resemblance: the
 * layout primitives are literally the same objects
 * (`@/components/record-form`: `PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/
 * `MAIN_COLUMN_CLASS`, `RECORD_HEADER_CLASS`, `SummaryRow`,
 * `RecordFormActions`, the general-notes callout chrome), so the screens
 * sharing them cannot drift apart with a later edit to one of them.
 *
 * Same skeleton as the panel:
 *  - `@container` + `bg-surface`, sticky identity bar with the live
 *    callback pill and the save/cancel actions, repeated at the foot of the
 *    form (user directive 2026-08-03) as the panel repeats its own;
 *  - two columns at `@4xl` — the side column FIRST in the DOM (narrow
 *    containers read it before the long form), reordered to the right.
 *
 * Section order (user directive 2026-09-10). The MAIN column is the filling
 * flow, in the order the operator works it: linee di prodotto, offerta,
 * anagrafica cliente, attribuzione, then the dynamic sets the chosen
 * categories resolve to ("Dati Lavorazione Contatto", "Dati corso", "Dati
 * Aula" — their own order lives in the layout blob, `QualificaQuoteLayoutSeeder`).
 * The SIDE column takes what is NOT part of that flow: the general-notes
 * callout always on top (user directive 2026-09-10), then prossimo richiamo,
 * sede operativa, team and the summary — the Sede immediately above the slots
 * it scopes (user directive 2026-09-10), which is why it left "Attribuzione".
 *
 * The RHF provider therefore wraps BOTH columns while the native `<form>`
 * element still scopes the main one alone: the side sections are ordinary
 * fields of the same form — `Form` is what `FormItem`/`FormMessage` read their
 * state from — and submission has never depended on DOM nesting (the two save
 * buttons already reach the form by id).
 *
 * The two differences from the panel are structural, not cosmetic: the panel's
 * collaboration block (note/documenti/storico) needs a record to hang off, and
 * its summary lists a commercial context that does not exist before the first
 * save — this one recaps what is about to be created instead.
 */
export function RequestCreateForm({ onSuccess, onCancel }: RequestCreateFormProps) {
  const { t } = useTranslation()
  const {
    form,
    onSubmit,
    isSubmitting,
    usingExistingRegistry,
    identityDraft,
    revalidateSignal,
    setIdentityDraft,
    contactsDraft,
    setContactsDraft,
    addressDraft,
    setAddressDraft,
    serverError,
    clientBlockError,
    productLinesError,
    rewardsError,
    context,
    isContextLoading,
    vatRatePercentFor,
    rememberVatRatePercent,
  } = useRequestCreateForm({ onSuccess })

  // The two supervisory blocks of the attribution/team pair are rendered only
  // for the actor holding their own ability (user directive 2026-08-03), the
  // same two the store endpoint enforces server-side. Resolved here, where the
  // form is, so the two sections cannot answer the question differently.
  const { can } = useAbilities()
  const canPickSite = can(OPERATIONAL_SITES_VIEW_ANY_PERMISSION)
  const canAssignOperator = can(ASSIGN_OPERATOR_PERMISSION)

  // Spec 0097 rev-2 D-7/AC-011: the Sede and the operator slot are two
  // sections apart — adjacent ones in the side column since the 2026-09-10
  // directive — so their reciprocal link is cabled here, where the form they
  // both write actually lives. `canPickSite` suppresses the auto-fill for an
  // actor who may not set the Sede at all: the key would come back 403 from
  // the endpoint.
  const siteLink = useRequestSiteOperatorLink(form, { canPickSite })

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <RequestCreateHeader
        control={form.control}
        formId={REQUEST_CREATE_FORM_ID}
        isSubmitting={isSubmitting}
        submitError={serverError}
        onCancel={onCancel}
      />

      <Form {...form}>
        <div className={CREATE_PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads it before the form,
              reordered to the right on two columns — the panel's own rule. */}
          <aside className={CREATE_SIDE_COLUMN_CLASS}>
            {/* Always first (user directive 2026-09-10), as in the work panel. */}
            <RequestCreateGeneralNotes control={form.control} />

            <RequestCreateCallbackSection control={form.control} />

            {/* Directly above the team it scopes (user directive 2026-09-10),
                and only for the actor allowed to set it. */}
            {canPickSite && (
              <RequestCreateSiteSection
                control={form.control}
                autoFilledSite={siteLink.autoFilledSite}
                onSiteItemChange={siteLink.onSiteItemChange}
              />
            )}

            <RequestCreateTeamSection
              control={form.control}
              canAssignOperator={canAssignOperator}
              canPickSite={canPickSite}
              siteId={siteLink.siteId}
              slotParamsFor={siteLink.slotParamsFor}
              onSlotItemChange={siteLink.onSlotItemChange}
            />

            <RequestCreateSummary
              control={form.control}
              identity={identityDraft}
              usingExistingRegistry={usingExistingRegistry}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form id={REQUEST_CREATE_FORM_ID} onSubmit={onSubmit} className="contents" noValidate>
              <FormSection
                icon={Boxes}
                title={t('requestManagement.workPanel.productLines.title')}
                description={t('requestManagement.workPanel.productLines.description')}
              >
                <FormField
                  control={form.control}
                  name="product_lines"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>
                        {t('requestManagement.workPanel.productLines.fieldLabel')}
                      </FormLabel>
                      <ProductLinesField value={field.value} onChange={field.onChange} />
                      <p className="text-xs text-muted-foreground">
                        {t('requestManagement.workPanel.productLines.hint')}
                      </p>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {productLinesError && (
                  <div role="alert" className={ERROR_BANNER_CLASS}>
                    {productLinesError}
                  </div>
                )}
              </FormSection>

              {/* "Linee dell'offerta" (user directive 2026-08-07): the work
                  panel's own section — literally the Offerte form's row
                  editor in both places — so a request can be opened with its
                  offer already filled in. `knownLines` is empty: nothing is
                  persisted yet, every label comes from what is picked here. */}
              <RequestOfferLinesSection
                control={form.control}
                knownLines={NO_PERSISTED_LINES}
                errors={
                  // Same cast `QuoteFormBody` applies to its own tab: RHF types
                  // an array field's errors as one node, the row editor reads
                  // them per index.
                  form.formState.errors.offer_lines as unknown as (QuoteLineRowErrors | undefined)[] | undefined
                }
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
              />

              <RequestCreateClientSection
                control={form.control}
                identity={identityDraft}
                onIdentityChange={setIdentityDraft}
                contacts={contactsDraft}
                onContactsChange={setContactsDraft}
                address={addressDraft}
                onAddressChange={setAddressDraft}
                usingExistingRegistry={usingExistingRegistry}
                revalidateSignal={revalidateSignal}
                errorMessage={clientBlockError}
              />

              <RequestCreateAttributionSection form={form} rewardsError={rewardsError} />

              {/* "Informazioni aggiuntive" (user directive 2026-08-07): the
                  work panel's own section — literally the Offerte form's
                  component in both places — fed the set the chosen categories
                  resolve to. Last in the flow (user directive 2026-09-10), so
                  its sections close the column. Not mounted until a complete
                  product line exists: with no category there is nothing to
                  resolve, and an empty card would read as a defect. */}
              {(isContextLoading || context.applicable_attributes.length > 0) && (
                <QuoteDynamicFieldsSection
                  control={form.control}
                  attributes={context.applicable_attributes}
                  layout={context.attribute_layout}
                  isLoading={isContextLoading}
                />
              )}

              <RecordFormActions
                formId={REQUEST_CREATE_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('requestManagement.form.create.save')}
                submittingLabel={t('requestManagement.form.create.saving')}
                cancel={{ label: t('requestManagement.form.create.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
