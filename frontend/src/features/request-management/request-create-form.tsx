import { useTranslation } from 'react-i18next'
import { Boxes, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Form, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { RequestCreateAttributionSection } from '@/features/request-management/request-create-attribution-section'
import { RequestCreateClientSection } from '@/features/request-management/request-create-client-section'
import { RequestCreateProductsOfInterest } from '@/features/request-management/request-create-products-of-interest'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'

interface RequestCreateFormProps {
  onSuccess: (id: number) => void
  onCancel: () => void
}

/**
 * Create-only form for the request-management module (spec 0057, D-7): the
 * attribution, the mandatory product lines (D-3, the shared
 * `ProductLinesField` also used by the opportunity form) and the anagrafica
 * (existing registry OR a brand-new client card, D-2) — nothing else (D-4).
 * Mounted as this module's `FormScreen` for `mode.type === 'create'` only;
 * edit/duplicate keep the "not applicable" notice
 * (`request-management-screens.tsx`).
 *
 * Section order mirrors the work panel's (user directive 2026-07-31):
 * attribution, then product lines, then anagrafica — the same sequence an
 * operator reads on the record they will later work.
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
  } = useRequestCreateForm({ onSuccess })

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4 p-4" noValidate>
          {/* Right after the product lines, which scope its options (user
              directive 2026-07-31). */}
          <RequestCreateProductsOfInterest control={form.control} />

          <RequestCreateAttributionSection form={form} rewardsError={rewardsError} />

          <FormSection
            icon={Boxes}
            title={t('requestManagement.form.create.productLines.title')}
            description={t('requestManagement.form.create.productLines.description')}
          >
            <FormField
              control={form.control}
              name="product_lines"
              render={({ field }) => (
                <FormItem>
                  <ProductLinesField value={field.value} onChange={field.onChange} />
                  <FormMessage />
                </FormItem>
              )}
            />

            {productLinesError && (
              <div
                role="alert"
                className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive"
              >
                {productLinesError}
              </div>
            )}
          </FormSection>

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

          {serverError && (
            <div
              role="alert"
              className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive"
            >
              {serverError}
            </div>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
              {t('requestManagement.form.create.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('requestManagement.form.create.saving') : t('requestManagement.form.create.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
