import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { ProductCreateSections } from '@/features/products/product-create-sections'
import { ProductFormHeader } from '@/features/products/product-form-header'
import { useProductCategoryPicker } from '@/features/products/use-product-category-picker'
import { useProductDraftValues } from '@/features/products/use-product-draft-values'
import { useProductForm } from '@/features/products/use-product-form'
import { useProductFormSubmit } from '@/features/products/use-product-form-submit'
import type { ProductDetail } from '@/features/products/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PRODUCT_FORM_ID = 'product-form'

/** Stable create mode: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ProductFormBodyProps {
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
  /** The sequential code suggestion prefilled into the `code` row (spec 0065). */
  initialCode: string
}

/**
 * The product create form UI, a replica of the product detail (spec 0218,
 * spec 0195 D-8 applied to Prodotti): the same `RecordCanvas`, the record card
 * with its identity band, KPI strip and sections, every row closed until
 * clicked (`ProductCreateSections`). There is no edit form: the detail edits a
 * persisted product in place.
 */
export function ProductFormBody({ onSuccess, onCancel, initialCode }: ProductFormBodyProps) {
  const { t } = useTranslation()
  const state = useProductForm({ mode: CREATE_MODE, initialCode })
  const { form } = state
  const { serverError, onSubmit } = useProductFormSubmit({ state, mode: CREATE_MODE, onSuccess })
  const categories = useProductCategoryPicker(null)
  const values = useProductDraftValues(form.control, categories)
  const draftRows = useDraftInlineEdit(form)
  const { isSubmitting } = form.formState

  // "Ripristina" on the category row brings the attribute set back with it.
  const draft: InlineEdit = {
    ...draftRows,
    cancel: () => {
      draftRows.cancel()
      state.syncCategory()
    },
  }

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={PRODUCT_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <ProductFormHeader
                values={values}
                formId={PRODUCT_FORM_ID}
                isSubmitting={isSubmitting}
                submitError={serverError}
                onCancel={onCancel}
              />
              <ProductCreateSections
                values={values}
                form={form}
                draft={draft}
                categories={categories}
                onCategoryChange={state.onCategoryChange}
                attributes={state.productAttributes}
                layout={state.createLayout}
                attributesLoading={state.productAttributesLoading}
              />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={PRODUCT_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('products.form.save')}
              submittingLabel={t('products.form.saving')}
              cancel={{ label: t('products.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
