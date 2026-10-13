import { useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Loader2, TriangleAlert } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import {
  ProductCategoryAttributesRecordSection,
  ProductCategoryManagerLabelsRecordSection,
} from '@/features/product-categories/product-category-configuration-sections'
import { ProductCategoryIdentityBand } from '@/features/product-categories/product-category-identity-band'
import { ProductCategoryIdentityRecordSection } from '@/features/product-categories/product-category-identity-record-section'
import { ProductCategoryRulesRecordSection } from '@/features/product-categories/product-category-rules-record-section'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import { useProductCategoryDraftValues } from '@/features/product-categories/use-product-category-draft-values'
import { useProductCategoryForm } from '@/features/product-categories/use-product-category-form'
import { useProductCategoryFormSubmit } from '@/features/product-categories/use-product-category-form-submit'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PRODUCT_CATEGORY_FORM_ID = 'product-category-form'

interface ProductCategoryFormBodyProps {
  /** Create or duplicate: there is no edit form, the detail edits in place (spec 0229). */
  mode: Exclude<ProductCategoryFormMode, { type: 'edit' }>
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * The category create form UI — a bare create or a duplicate, both through the
 * create path — as a replica of the category detail (spec 0229 D-4, spec 0195
 * D-8): the same `RecordCanvas`, the record card with its identity band
 * (monogram, title and pills follow the draft, Annulla/Salva) and sections, the
 * rows closed until clicked (Fatto/Ripristina, nothing saved per row). The
 * attributes and G.A. denominations editors stay OPEN: they are editors, not
 * fields. All non-render logic lives in `useProductCategoryForm` and
 * `useProductCategoryFormSubmit`.
 */
export function ProductCategoryFormBody({ mode, onSuccess, onCancel }: ProductCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { form, customFieldErrorPaths } = useProductCategoryForm({ mode })
  const { serverError, onSubmit } = useProductCategoryFormSubmit({ form, mode, customFieldErrorPaths, onSuccess })
  const draft = useDraftInlineEdit(form)
  const values = useProductCategoryDraftValues(form.control)
  const customFieldValues = useWatch({ control: form.control, name: 'custom_fields' })
  const { isSubmitting } = form.formState
  const sectionProps = { values, form, inline: draft, mode, canEdit: true }

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={PRODUCT_CATEGORY_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <ProductCategoryIdentityBand
                values={values}
                fallbackTitle={t('productCategories.form.createTitle')}
                subtitle={values.parent?.name ?? t('productCategories.form.createSubtitle')}
                actions={
                  <>
                    <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                      {t('productCategories.form.cancel')}
                    </Button>
                    <Button type="submit" form={PRODUCT_CATEGORY_FORM_ID} disabled={isSubmitting}>
                      {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      {isSubmitting ? t('productCategories.form.saving') : t('productCategories.form.save')}
                    </Button>
                  </>
                }
              />
              {serverError ? (
                <div
                  role="alert"
                  className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
                >
                  <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                  {serverError}
                </div>
              ) : null}
              <RecordSectionsGrid>
                <ProductCategoryIdentityRecordSection {...sectionProps} />
                <ProductCategoryManagerLabelsRecordSection {...sectionProps} />
                <ProductCategoryRulesRecordSection {...sectionProps} />
                <ProductCategoryAttributesRecordSection {...sectionProps} />
                <CustomFieldRecordSections
                  resource="product-categories"
                  values={customFieldValues}
                  inline={draft}
                  control={form.control}
                />
              </RecordSectionsGrid>
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={PRODUCT_CATEGORY_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('productCategories.form.save')}
              submittingLabel={t('productCategories.form.saving')}
              cancel={{ label: t('productCategories.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
