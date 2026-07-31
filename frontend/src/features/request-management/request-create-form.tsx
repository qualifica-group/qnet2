import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import { Form, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { RequestCreateAttributionSection } from '@/features/request-management/request-create-attribution-section'
import { RequestCreateCallbackSection } from '@/features/request-management/request-create-callback-section'
import { RequestCreateClientSection } from '@/features/request-management/request-create-client-section'
import { RequestCreateDynamicFields } from '@/features/request-management/request-create-dynamic-fields'
import { RequestCreateGeneralNotes } from '@/features/request-management/request-create-general-notes'
import { RequestCreateHeader } from '@/features/request-management/request-create-header'
import { RequestCreateProductsOfInterest } from '@/features/request-management/request-create-products-of-interest'
import { RequestCreateSummary } from '@/features/request-management/request-create-summary'
import { RequestCreateWorkflowStatusField } from '@/features/request-management/request-create-workflow-status-field'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/features/request-management/request-work-panel'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the work panel does (`REQUEST_WORK_FORM_ID`): the primary action
 * lives in the identity bar, not in a footer, so the two screens put "save" in
 * the same place.
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
 * layout primitives are literally the panel's own, imported from it
 * (`PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/`MAIN_COLUMN_CLASS`,
 * `REQUEST_HEADER_CLASS`, `StatusBadge`, `SummaryRow`, the general-notes
 * callout chrome), so the two screens cannot drift apart with a later edit to
 * one of them.
 *
 * Same skeleton as the panel:
 *  - `@container` + `bg-surface`, sticky identity bar with the live status /
 *    callback pills and the only save action;
 *  - two columns at `@4xl` — the read-only side column FIRST in the DOM
 *    (narrow containers read it before the long form), reordered to the right;
 *  - side column = "Note generali" callout on top, then the summary card;
 *  - main column = the same sections in the same order: working state and next
 *    callback, attribution, dynamic fields, product lines, products of
 *    interest, anagrafica.
 *
 * The two differences are structural, not cosmetic: the panel's collaboration
 * block (note/documenti/storico) needs a record to hang off, and its summary
 * lists a commercial context that does not exist before the first save — this
 * one recaps what is about to be created instead.
 *
 * The status select and the dynamic fields stay hidden until a categoria
 * prodotto is picked: both are resolved FROM it.
 */
export function RequestCreateForm({ onSuccess, onCancel }: RequestCreateFormProps) {
  const { t } = useTranslation()
  const {
    form,
    onSubmit,
    isSubmitting,
    context,
    isContextLoading,
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
  } = useRequestCreateForm({ onSuccess })

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <RequestCreateHeader
        control={form.control}
        statuses={context.workflow_statuses}
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
              <div className="grid min-w-0 items-start gap-4 @2xl:grid-cols-2">
                <RequestCreateWorkflowStatusField
                  control={form.control}
                  statuses={context.workflow_statuses}
                />

                <RequestCreateCallbackSection control={form.control} />
              </div>

              <RequestCreateAttributionSection form={form} rewardsError={rewardsError} />

              <RequestCreateDynamicFields
                control={form.control}
                attributes={context.applicable_attributes}
                layout={context.attribute_layout}
                isLoading={isContextLoading}
              />

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

              {/* Right after the product lines, which scope its options. */}
              <RequestCreateProductsOfInterest control={form.control} />

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
            </form>
          </Form>
        </div>
      </div>
    </div>
  )
}
