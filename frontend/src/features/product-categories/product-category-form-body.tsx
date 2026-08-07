import { useMemo } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FolderTree, ListChecks, Users } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormDescription } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import { useEffectiveManagerLabels } from '@/features/product-categories/use-effective-manager-labels'
import {
  ROOT_PARENT_VALUE,
  collectSubtreeIds,
  flattenCategoryTree,
} from '@/features/product-categories/flatten-tree'
import {
  useProductCategoryForm,
  type ProductCategoryFormValues,
} from '@/features/product-categories/use-product-category-form'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import { ManagerLabelEditor, ManagerLabelsInheritanceToggle } from '@/features/product-categories/manager-label-editor'
import type { AttributeCatalogEntry } from '@/features/attributes/use-attribute-catalog'
import { ProductCategoryBusinessFunctionField } from '@/features/product-categories/product-category-business-function-field'
import { ProductCategoryRulesSection } from '@/features/product-categories/product-category-rules-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type {
  AttributeContext,
  EffectiveAttribute,
  ManagerLabels,
  ProductCategoryDetail,
  ProductCategoryFormMode,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface ProductCategoryFormBodyProps {
  mode: ProductCategoryFormMode
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/** Tags each effective-attributes result with the context it was fetched for, into the flat shape `AttributeAssignmentEditor` splits (spec 0061). */
function toInheritedAttributes(
  product: EffectiveAttribute[] | undefined,
  quote: EffectiveAttribute[] | undefined,
): ProductCategoryInheritedAttribute[] {
  const tag = (attributes: EffectiveAttribute[] | undefined, context: AttributeContext) =>
    (attributes ?? []).map((attribute) => ({
      attribute_id: attribute.id,
      code: attribute.code,
      name: attribute.name,
      type: attribute.type,
      is_required: attribute.is_required,
      context,
    }))
  return [...tag(product, 'product'), ...tag(quote, 'quote')]
}

/** Hoisted so an opted-out context feeds a stable reference to `toInheritedAttributes`. */
const EMPTY_ATTRIBUTES: EffectiveAttribute[] = []

/** Hoisted so an opted-out (or root) manager-labels barrier feeds a stable, empty reference. */
const EMPTY_MANAGER_LABELS: ManagerLabels = {}

/** Hoisted for the same reason, on the create path (no category loaded yet). */
const EMPTY_KNOWN_ATTRIBUTES: AttributeCatalogEntry[] = []

/**
 * The name/type the loaded category's own assignments already carry: the
 * editor labels its rows from here, so an attribute sitting outside the
 * picker's search window still shows its name instead of a bare `#id`.
 */
function toKnownAttributes(mode: ProductCategoryFormMode): AttributeCatalogEntry[] {
  if (mode.type !== 'edit') {
    return EMPTY_KNOWN_ATTRIBUTES
  }

  return mode.category.attributes.map((assignment) => ({
    id: assignment.attribute_id,
    code: assignment.code,
    name: assignment.name,
    type: assignment.type,
  }))
}

/** The `inherits_*_attributes` field names, one per usage context — RHF path and authorization metadata key alike. */
const INHERITANCE_FIELD = {
  product: 'inherits_product_attributes',
  quote: 'inherits_quote_attributes',
} as const

interface InheritanceToggleProps {
  control: Control<ProductCategoryFormValues>
  context: AttributeContext
}

/**
 * The per-context "inherit from parent" switch, rendered INSIDE the section it
 * governs (spec 0061 follow-up): each usage context carries its own barrier,
 * so the Product list can ignore the ancestry while the Offerta one keeps
 * inheriting. Defined at module level — never inside the form component.
 */
function InheritanceToggle({ control, context }: InheritanceToggleProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name={INHERITANCE_FIELD[context]}
      metaKey={INHERITANCE_FIELD[context]}
      label={t('productCategories.form.inheritsAttributes')}
      description={<FormDescription>{t('productCategories.form.inheritsAttributesHint')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * The category create/edit form UI: identity fields (name, parent,
 * description) wrapped in `MetaField` (spec 0004), followed by the
 * attribute-assignment editor (own assignments + read-only inherited list).
 * All non-render logic lives in `useProductCategoryForm`.
 */
export function ProductCategoryFormBody({ mode, onSuccess, onCancel }: ProductCategoryFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useProductCategoryForm({ mode, onSuccess })
  const treeQuery = useProductCategoryTree()

  const parentId = form.watch('parent_id')
  const inheritsProductAttributes = form.watch('inherits_product_attributes')
  const inheritsQuoteAttributes = form.watch('inherits_quote_attributes')
  const knownAttributes = useMemo(() => toKnownAttributes(mode), [mode])
  const inheritedProductQuery = useEffectiveAttributes(parentId, 'product')
  const inheritedQuoteQuery = useEffectiveAttributes(parentId, 'quote')
  // Opting out is a barrier: that context inherits nothing, so the read-only
  // inherited list must reflect it immediately (not just after save) — and only
  // for the context whose switch moved, the other side is untouched.
  // Flat, both contexts (spec 0061) — `AttributeAssignmentEditor` splits it.
  const inherited: ProductCategoryInheritedAttribute[] = useMemo(
    () =>
      toInheritedAttributes(
        inheritsProductAttributes ? inheritedProductQuery.data : EMPTY_ATTRIBUTES,
        inheritsQuoteAttributes ? inheritedQuoteQuery.data : EMPTY_ATTRIBUTES,
      ),
    [
      inheritedProductQuery.data,
      inheritedQuoteQuery.data,
      inheritsProductAttributes,
      inheritsQuoteAttributes,
    ],
  )

  const inheritsManagerLabels = form.watch('inherits_manager_labels')
  const inheritedManagerLabelsQuery = useEffectiveManagerLabels(parentId)
  // Same immediate-barrier behavior as the attribute contexts above: turning
  // the switch off drops the inherited preview before the save round-trips.
  const inheritedManagerLabels = inheritsManagerLabels
    ? (inheritedManagerLabelsQuery.data ?? EMPTY_MANAGER_LABELS)
    : EMPTY_MANAGER_LABELS

  const parentOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = mode.type === 'edit' ? collectSubtreeIds(nodes, mode.category.id) : new Set<number>()
    return [
      { id: ROOT_PARENT_VALUE, name: t('productCategories.form.noParent') },
      ...flattenCategoryTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, mode, t])

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('parent_id').visible ||
    fieldPermission('description').visible ||
    fieldPermission('business_function_id').visible
  const attributesVisible = fieldPermission('attributes').visible
  const managerLabelsVisible = fieldPermission('manager_labels').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={FolderTree}
              title={t('productCategories.form.sections.identity.title')}
              description={t('productCategories.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('productCategories.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="parent_id"
                metaKey="parent_id"
                label={t('productCategories.form.parent')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <SearchableSelect
                      value={field.value ?? ROOT_PARENT_VALUE}
                      onChange={(next) =>
                        field.onChange(next === ROOT_PARENT_VALUE ? null : next)
                      }
                      options={parentOptions}
                      isPending={treeQuery.isPending}
                      isError={treeQuery.isError}
                      onRetry={() => void treeQuery.refetch()}
                      disabled={disabled}
                      labels={{
                        placeholder: t('productCategories.form.parentPlaceholder'),
                        searchPlaceholder: t('productCategories.form.parentSearch'),
                        empty: t('productCategories.form.parentEmpty'),
                        noMatch: t('productCategories.form.parentNoMatch'),
                        error: t('productCategories.form.parentError'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('productCategories.form.description')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <ProductCategoryBusinessFunctionField control={form.control} mode={mode} parentId={parentId} />
            </FormSection>
          )}

          <ProductCategoryRulesSection control={form.control} mode={mode} parentId={parentId} />

          {attributesVisible && (
            <FormSection
              icon={ListChecks}
              title={t('productCategories.form.sections.attributes.title')}
              description={t('productCategories.form.sections.attributes.description')}
            >
              <MetaField
                control={form.control}
                name="attributes"
                metaKey="attributes"
                label={t('productCategories.form.attributes')}
              >
                {({ field, disabled }) => (
                  <AttributeAssignmentEditor
                    value={field.value}
                    onChange={field.onChange}
                    known={knownAttributes}
                    inherited={inherited}
                    disabled={disabled}
                    // A root category has no ancestry to inherit from: no switch to show.
                    productInheritToggle={
                      parentId !== null ? <InheritanceToggle control={form.control} context="product" /> : null
                    }
                    quoteInheritToggle={
                      parentId !== null ? <InheritanceToggle control={form.control} context="quote" /> : null
                    }
                  />
                )}
              </MetaField>
            </FormSection>
          )}

          {managerLabelsVisible && (
            <FormSection
              icon={Users}
              title={t('productCategories.form.sections.managerLabels.title')}
              description={t('productCategories.form.sections.managerLabels.description')}
            >
              <MetaField
                control={form.control}
                name="manager_labels"
                metaKey="manager_labels"
                label={t('productCategories.form.sections.managerLabels.title')}
              >
                {({ field, disabled }) => (
                  <ManagerLabelEditor
                    value={field.value}
                    onChange={field.onChange}
                    inherited={inheritedManagerLabels}
                    disabled={disabled}
                    // A root category has no ancestry to inherit from: no switch to show.
                    inheritToggle={
                      parentId !== null ? <ManagerLabelsInheritanceToggle control={form.control} /> : null
                    }
                  />
                )}
              </MetaField>
            </FormSection>
          )}

          <CustomFieldsSection resource="product-categories" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('productCategories.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('productCategories.form.saving')
                : t('productCategories.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
