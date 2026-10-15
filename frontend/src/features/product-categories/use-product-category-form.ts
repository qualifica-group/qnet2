import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import {
  buildCreateProductCategorySchema,
  buildUpdateProductCategorySchema,
  MANAGER_LABEL_MIN_ROWS,
  padManagerLabelPositions,
  type CreateProductCategoryFormValues,
} from '@/features/product-categories/product-category-schema'
import type {
  ManagerLabels,
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Empty form value: the reasonable-minimum blank rows a brand-new category opens with (spec 0080 A1). */
const EMPTY_MANAGER_LABELS_FORM: ManagerLabels = Object.fromEntries(
  padManagerLabelPositions([], MANAGER_LABEL_MIN_ROWS).map((position) => [String(position), '']),
)

/**
 * Seeds the dynamic rows the section opens with: the category's own
 * positions, padded with the smallest free ones up to the reasonable minimum
 * (spec 0080 A1) — never trimmed, so a category with more own positions than
 * the minimum still shows all of them. The payload builder strips blanks back
 * out before sending (AC-042).
 */
function toManagerLabelsFormValue(labels: ManagerLabels): ManagerLabels {
  const ownPositions = Object.keys(labels).map(Number)
  const rows = padManagerLabelPositions(ownPositions, MANAGER_LABEL_MIN_ROWS)
  return Object.fromEntries(rows.map((position) => [String(position), labels[String(position)] ?? '']))
}

export type ProductCategoryFormValues = CreateProductCategoryFormValues

/**
 * Maps every form field shared by edit and duplicate (row action "duplicate")
 * from a loaded category — `name` excluded, since duplicate appends the copy
 * suffix — so the two `defaultValues` branches below never drift apart.
 */
function mapCategoryToFormValues(
  category: ProductCategoryDetail,
  customFieldsDefaultValues: ProductCategoryFormValues['custom_fields'],
): Omit<ProductCategoryFormValues, 'name'> {
  return {
    parent_id: category.parent_id,
    inherits_product_attributes: category.inherits_product_attributes,
    inherits_quote_attributes: category.inherits_quote_attributes,
    inherits_work_order_attributes: category.inherits_work_order_attributes,
    description: category.description,
    attributes: category.attributes.map((assignment) => ({
      attribute_id: assignment.attribute_id,
      context: assignment.context,
      is_required: assignment.is_required,
      sort_order: assignment.sort_order,
    })),
    business_function_id: category.business_function_id,
    requires_quote: category.requires_quote,
    is_selectable: category.is_selectable,
    is_active: category.is_active,
    is_reportable: category.is_reportable,
    report_columns: category.report_columns,
    management_mode: category.management_mode,
    single_quote_per_opportunity: category.single_quote_per_opportunity,
    generates_contract: category.generates_contract,
    multi_quote_work_order: category.multi_quote_work_order,
    simplified_offer_line: category.simplified_offer_line,
    simplified_offer_line_override: category.simplified_offer_line_override,
    manager_labels: toManagerLabelsFormValue(category.manager_labels),
    inherits_manager_labels: category.inherits_manager_labels,
    custom_fields: customFieldsDefaultValues,
  }
}

/** The persisted category as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(
  category: ProductCategoryDetail,
  customFieldsDefaultValues: ProductCategoryFormValues['custom_fields'],
): ProductCategoryFormValues {
  return { name: category.name, ...mapCategoryToFormValues(category, customFieldsDefaultValues) }
}

interface UseProductCategoryFormArgs {
  mode: ProductCategoryFormMode
}

/**
 * Owns the RHF/Zod wiring of the category create/duplicate form and of the
 * in-place detail (`useProductCategoryInlineEdit`, edit mode): schema, default
 * values and the custom fields' 422 paths. Submission lives in
 * `useProductCategoryFormSubmit`.
 *
 * Edit mode IS the category detail (spec 0229): the persisted record can change
 * under the form, so `values` re-syncs it while `keepDirtyValues` preserves
 * the row still being edited.
 */
export function useProductCategoryForm({ mode }: UseProductCategoryFormArgs) {
  const { t } = useTranslation()

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  // Duplicate seeds its values from the source, exactly like edit, even
  // though it submits through the create payload builder.
  const customFields = useCustomFieldsForm(
    'product-categories',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.category.custom_fields }
      : mode.type === 'duplicate'
        ? { type: 'edit', customFields: mode.source.custom_fields }
        : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProductCategorySchema(t, customFields.schema)
        : buildCreateProductCategorySchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProductCategoryFormValues>(() => {
    if (mode.type === 'edit') {
      return editDefaults(mode.category, customFields.defaultValues)
    }
    if (mode.type === 'duplicate') {
      return {
        name: mode.source.name + t('common.copySuffix'),
        ...mapCategoryToFormValues(mode.source, customFields.defaultValues),
      }
    }
    return {
      name: '',
      parent_id: mode.parentId,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: false,
      // Spec 0074: a new category is a usable destination unless the
      // operator explicitly turns it into a container.
      is_selectable: true,
      // A new category is active unless the operator switches it off (spec 0208).
      is_active: true,
      // A new category inherits its parent's report flag (a root: not
      // reportable) until the operator forces it — mirrors the backend default.
      is_reportable: null,
      // A new category inherits its ancestry's report columns (spec 0141)
      // until the operator picks its own.
      report_columns: null,
      // Spec 0077 D-8: `multiple` is the behavior every existing root already
      // has; a new root starts from the same default.
      management_mode: 'multiple',
      // User decision 2026-10-15: a new root allows one offer per opportunity.
      single_quote_per_opportunity: true,
      // Spec 0091: a new root is sold under a contract — the behaviour every
      // existing category had before the rule existed.
      generates_contract: true,
      // Spec 0267: a new root keeps one work order per offer until the operator opts in.
      multi_quote_work_order: false,
      // Spec 0114: a new root opens with the manual row editor — the
      // behaviour every existing category had before the rule existed.
      simplified_offer_line: false,
      // A new child inherits its ancestry's value until the operator forces it (spec 0188).
      simplified_offer_line_override: null,
      manager_labels: EMPTY_MANAGER_LABELS_FORM,
      inherits_manager_labels: true,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, t])

  const form = useForm<ProductCategoryFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
