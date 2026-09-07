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
import {
  ROOT_PARENT_VALUE,
  collectSubtreeIds,
  flattenCategoryTree,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryForm } from '@/features/product-categories/use-product-category-form'
import { ProductCategoryFormHeader } from '@/features/product-categories/product-category-form-header'
import { ProductCategoryFormSummary } from '@/features/product-categories/product-category-form-summary'
import {
  ProductCategoryIdentitySection,
  type ProductCategoryParentPicker,
} from '@/features/product-categories/product-category-identity-section'
import { ProductCategoryRulesSection } from '@/features/product-categories/product-category-rules-section'
import { ProductCategoryAttributesSection } from '@/features/product-categories/product-category-attributes-section'
import { ProductCategoryManagerLabelsSection } from '@/features/product-categories/product-category-manager-labels-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'

interface ProductCategoryFormBodyProps {
  mode: ProductCategoryFormMode
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as the other record forms do: the same id serves the footer
 * actions, so both copies of the button submit this form without either of
 * them nesting the other.
 */
const PRODUCT_CATEGORY_FORM_ID = 'product-category-form'

/**
 * The category create/edit form UI, built on the SAME record-form skeleton as
 * Opportunita', Gestione Richieste and Prodotti (`@/components/record-form` —
 * `RECORD_HEADER_CLASS` through `ProductCategoryFormHeader`,
 * `PANEL_GRID_CLASS`/`SIDE_COLUMN_CLASS`/`MAIN_COLUMN_CLASS`,
 * `FIELD_GRID_CLASS`, `SummaryRow`, `RecordFormActions`): the same objects, so
 * the screens cannot drift apart with a later edit to one of them.
 *
 * Main column = identity, the behavioural rules, the attribute assignments,
 * the G.A. denominations, then the universal custom fields; side column = the
 * live recap of what the category will impose downstream.
 *
 * This file only composes: each card owns its own fields, its own derived
 * data and its own visibility gate (spec 0004 `MetaField`); all non-render
 * logic lives in `useProductCategoryForm`. The parent option list is the one
 * thing resolved here, because three consumers read it (identity bar, side
 * recap, picker) and it must be the same list in all three.
 */
export function ProductCategoryFormBody({ mode, onSuccess, onCancel }: ProductCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useProductCategoryForm({ mode, onSuccess })
  const treeQuery = useProductCategoryTree()

  const parentId = form.watch('parent_id')
  const { isSubmitting } = form.formState

  // A category may never be reparented under its own subtree: the picker drops
  // it client-side as an affordance, the server enforces it.
  const parentOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = mode.type === 'edit' ? collectSubtreeIds(nodes, mode.category.id) : new Set<number>()
    return [
      { id: ROOT_PARENT_VALUE, name: t('productCategories.form.noParent') },
      ...flattenCategoryTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, mode, t])
  const parents: ProductCategoryParentPicker = {
    options: parentOptions,
    isPending: treeQuery.isPending,
    isError: treeQuery.isError,
    onRetry: () => void treeQuery.refetch(),
  }

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <ProductCategoryFormHeader
          control={form.control}
          isEdit={mode.type === 'edit'}
          parentOptions={parentOptions}
          formId={PRODUCT_CATEGORY_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          {/* First in the DOM so a narrow container reads it before the form,
              reordered to the right on two columns — the panel's own rule. */}
          <aside className={SIDE_COLUMN_CLASS}>
            <ProductCategoryFormSummary control={form.control} parentOptions={parentOptions} />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={PRODUCT_CATEGORY_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <ProductCategoryIdentitySection
                control={form.control}
                mode={mode}
                parents={parents}
                parentId={parentId}
              />

              <ProductCategoryRulesSection control={form.control} mode={mode} parentId={parentId} />

              <ProductCategoryAttributesSection
                control={form.control}
                mode={mode}
                parentId={parentId}
              />

              <ProductCategoryManagerLabelsSection control={form.control} parentId={parentId} />

              <CustomFieldsSection resource="product-categories" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: it is long enough that the operator finishes typing
                  far from the sticky bar. */}
              <RecordFormActions
                formId={PRODUCT_CATEGORY_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('productCategories.form.save')}
                submittingLabel={t('productCategories.form.saving')}
                cancel={{ label: t('productCategories.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
