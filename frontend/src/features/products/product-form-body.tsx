import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { collectSelectableIds, flattenCategoryTree } from '@/features/product-categories/flatten-tree'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useProductForm } from '@/features/products/use-product-form'
import { ProductDynamicFields } from '@/features/products/product-dynamic-fields'
import { ProductFormHeader } from '@/features/products/product-form-header'
import {
  ProductFormSummary,
  type ProductSelectedRelations,
} from '@/features/products/product-form-summary'
import { ProductIdentitySection } from '@/features/products/product-identity-section'
import {
  ProductClassificationSection,
  type ProductCategoryPicker,
} from '@/features/products/product-classification-section'
import { ProductPricingSection } from '@/features/products/product-pricing-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { ProductDetail, ProductFormMode } from '@/features/products/types'

/** Hoisted so the create-mode memo keeps a stable reference across renders. */
const EMPTY_CATEGORY_IDS: readonly number[] = []

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the Opportunita' and Gestione Richieste screens do: the same id
 * serves the footer actions, so both copies of the button submit this form
 * without either of them nesting the other.
 */
const PRODUCT_FORM_ID = 'product-form'

interface ProductFormBodyProps {
  mode: ProductFormMode
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
  /** Create-only: the sequential code suggestion prefilled into the `code` field (spec 0065). */
  initialCode?: string
}

/**
 * The product create/edit form UI, built on the SAME record-form skeleton as
 * Opportunita' and Gestione Richieste (`@/components/record-form` —
 * `RECORD_HEADER_CLASS` through `ProductFormHeader`, `PANEL_GRID_CLASS`/
 * `SIDE_COLUMN_CLASS`/`MAIN_COLUMN_CLASS`, `FIELD_GRID_CLASS`, `SummaryRow`,
 * `RecordFormActions`), not a look-alike: the layout primitives are literally
 * the same objects, so the screens cannot drift apart with a later edit to one
 * of them.
 *
 *  - `@container` + `bg-surface`, sticky identity bar carrying the live
 *    code/category pills and the save/cancel actions, repeated at the foot;
 *  - two columns at `@4xl` — the read-only recap FIRST in the DOM (narrow
 *    containers read it before the long form), reordered to the right;
 *  - main column = identity, classification, pricing/supply, then the
 *    category-driven attributes and the universal custom fields.
 *
 * This file only composes: each card owns its own fields and its own
 * visibility gate (spec 0004 `MetaField`), and all non-render logic still
 * lives in `useProductForm`.
 */
export function ProductFormBody({ mode, onSuccess, onCancel, initialCode }: ProductFormBodyProps) {
  const { t } = useTranslation()
  const { canResource } = useResourcePermissions()
  const {
    form,
    serverError,
    onSubmit,
    productAttributes,
    productAttributesLoading,
    productLayout,
    layoutFormMode,
    onCategoryChange,
  } = useProductForm({ mode, onSuccess, initialCode })
  const treeQuery = useProductCategoryTree()
  // Attribute values are authorized at the resource level, not per field
  // (see `useProductFormMeta`'s docblock) — gate the whole dynamic block on
  // the same ability the save button itself requires.
  const attributesEditable = canResource(mode.type === 'edit' ? 'update' : 'create')
  const { isSubmitting } = form.formState

  // Spec 0074: this picker is a DESTINATION served by the structural tree
  // cache, so it filters client-side. The product's saved category is kept
  // even when unselectable (D-3b) — the server accepts it unchanged, and the
  // select must not blank out on an untouched edit.
  const savedCategoryIds = useMemo(
    () => (mode.type === 'edit' ? [mode.product.category_id] : EMPTY_CATEGORY_IDS),
    [mode],
  )
  // An unselectable category is LISTED, disabled (user directive
  // 2026-08-03): it is the branch its selectable children hang from, and
  // dropping it left the list reading as unrelated leaves.
  const categoryOptions = useMemo(
    () =>
      flattenCategoryTree(treeQuery.data ?? [], {
        pickableIds: collectSelectableIds(treeQuery.data ?? []),
        keepIds: savedCategoryIds,
      }),
    [treeQuery.data, savedCategoryIds],
  )
  const categories: ProductCategoryPicker = {
    options: categoryOptions,
    isPending: treeQuery.isPending,
    isError: treeQuery.isError,
    onRetry: () => void treeQuery.refetch(),
  }

  // Edit-mode hydration for the relation pickers below: the loaded product's
  // `{id, name}` projections, already the shape `RelationSelectField` and the
  // side recap expect.
  const selectedRelations: ProductSelectedRelations = {
    vatRate: mode.type === 'edit' ? mode.product.vat_rate : null,
    supplier: mode.type === 'edit' ? mode.product.supplier : null,
    unitOfMeasure: mode.type === 'edit' ? mode.product.unit_of_measure : null,
    productTypology: mode.type === 'edit' ? mode.product.product_typology : null,
  }

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <ProductFormHeader
          control={form.control}
          isEdit={mode.type === 'edit'}
          categoryOptions={categoryOptions}
          formId={PRODUCT_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads it before the form,
              reordered to the right on two columns — the panel's own rule. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <ProductFormSummary
              control={form.control}
              categoryOptions={categoryOptions}
              selected={selectedRelations}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={PRODUCT_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <ProductIdentitySection control={form.control} />

              <ProductClassificationSection
                control={form.control}
                categories={categories}
                selected={selectedRelations}
                onCategoryChange={onCategoryChange}
              />

              <ProductPricingSection control={form.control} selected={selectedRelations} />

              <ProductDynamicFields
                control={form.control}
                attributes={productAttributes}
                layout={productLayout}
                mode={layoutFormMode}
                isLoading={productAttributesLoading}
                disabled={!attributesEditable}
              />

              <CustomFieldsSection resource="products" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={PRODUCT_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('products.form.save')}
                submittingLabel={t('products.form.saving')}
                cancel={{ label: t('products.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
