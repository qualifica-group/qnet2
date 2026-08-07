import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Boxes } from 'lucide-react'
import { Form, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'
import { QuoteDynamicFieldsSection } from '@/features/quotes/quote-dynamic-fields-section'
import { RequestCreateAttributionSection } from '@/features/request-management/request-create-attribution-section'
import { RequestCreateCallbackSection } from '@/features/request-management/request-create-callback-section'
import { RequestCreateClientSection } from '@/features/request-management/request-create-client-section'
import { RequestCreateGeneralNotes } from '@/features/request-management/request-create-general-notes'
import { RequestCreateHeader } from '@/features/request-management/request-create-header'
import { RequestCreateProductsOfInterest } from '@/features/request-management/request-create-products-of-interest'
import { RequestCreateSummary } from '@/features/request-management/request-create-summary'
import { useProductsOfInterestCoherence } from '@/features/products/use-products-of-interest-coherence'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the work panel does (`REQUEST_WORK_FORM_ID`). The same id serves
 * the footer actions (user directive 2026-08-03), so both copies of the button
 * submit this form without either of them nesting the other.
 */
const REQUEST_CREATE_FORM_ID = 'request-create-form'

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
 *  - two columns at `@4xl` — the read-only side column FIRST in the DOM
 *    (narrow containers read it before the long form), reordered to the right;
 *  - side column = "Note generali" callout on top, then the summary card;
 *  - main column = the same sections in the same order: product lines and
 *    products of interest FIRST (user directive 2026-08-03 — they are the
 *    record's headline information), then the next callback, attribution,
 *    anagrafica.
 *
 * The two differences are structural, not cosmetic: the panel's collaboration
 * block (note/documenti/storico) needs a record to hang off, and its summary
 * lists a commercial context that does not exist before the first save — this
 * one recaps what is about to be created instead.
 */
export function RequestCreateForm({ onSuccess, onCancel }: RequestCreateFormProps) {
  const { t } = useTranslation()
  const {
    form,
    onSubmit,
    isSubmitting,
    usingExistingRegistry,
    identityDraft,
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
  } = useRequestCreateForm({ onSuccess })

  // Spec 0075, D-5: the same rule the work panel applies — a product line
  // removed (or re-pointed) drops the products of interest it was covering,
  // so the form can never submit a classification the server would refuse.
  const productsOfInterest = useWatch({ control: form.control, name: 'products_of_interest' })
  const keepCoveredProducts = useProductsOfInterestCoherence(productsOfInterest)

  const pruneProductsOfInterest = (rows: ProductLineRow[]) => {
    const kept = keepCoveredProducts(rows)

    if (kept.length !== productsOfInterest.length) {
      form.setValue('products_of_interest', kept, { shouldDirty: true })
    }
  }

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <RequestCreateHeader
        control={form.control}
        formId={REQUEST_CREATE_FORM_ID}
        isSubmitting={isSubmitting}
        submitError={serverError}
        onCancel={onCancel}
      />

      <div className={PANEL_GRID_CLASS}>
        {/* First in the DOM so a narrow container reads it before the form,
            reordered to the right on two columns — the panel's own rule. */}
        <aside className={SIDE_COLUMN_CLASS}>
          <RequestCreateGeneralNotes control={form.control} />
          <RequestCreateSummary
            control={form.control}
            identity={identityDraft}
            usingExistingRegistry={usingExistingRegistry}
          />
        </aside>

        <div className={MAIN_COLUMN_CLASS}>
          <Form {...form}>
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
                      <ProductLinesField
                        value={field.value}
                        onChange={(rows) => {
                          field.onChange(rows)
                          pruneProductsOfInterest(rows)
                        }}
                      />
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

              {/* Right after the product lines, which scope its options. */}
              <RequestCreateProductsOfInterest control={form.control} />

              <RequestCreateCallbackSection control={form.control} />

              <RequestCreateAttributionSection form={form} rewardsError={rewardsError} />

              {/* "Informazioni aggiuntive" (user directive 2026-08-07): the
                  work panel's own section — literally the Offerte form's
                  component in both places — fed the set the chosen categories
                  resolve to. Not mounted until a complete product line exists:
                  with no category there is nothing to resolve, and an empty
                  card would read as a defect. */}
              {(isContextLoading || context.applicable_attributes.length > 0) && (
                <QuoteDynamicFieldsSection
                  control={form.control}
                  attributes={context.applicable_attributes}
                  layout={context.attribute_layout}
                  isLoading={isContextLoading}
                />
              )}

              <RequestCreateClientSection
                control={form.control}
                identity={identityDraft}
                onIdentityChange={setIdentityDraft}
                contacts={contactsDraft}
                onContactsChange={setContactsDraft}
                address={addressDraft}
                onAddressChange={setAddressDraft}
                usingExistingRegistry={usingExistingRegistry}
                errorMessage={clientBlockError}
              />

              <RecordFormActions
                formId={REQUEST_CREATE_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('requestManagement.form.create.save')}
                submittingLabel={t('requestManagement.form.create.saving')}
                cancel={{ label: t('requestManagement.form.create.cancel'), onCancel }}
              />
            </form>
          </Form>
        </div>
      </div>
    </div>
  )
}
