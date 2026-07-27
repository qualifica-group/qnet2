import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { FolderTree, LayoutGrid, ListChecks } from 'lucide-react'
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
import {
  ROOT_PARENT_VALUE,
  collectSubtreeIds,
  flattenCategoryTree,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryForm } from '@/features/product-categories/use-product-category-form'
import { AttributeAssignmentEditor } from '@/features/product-categories/attribute-assignment-editor'
import { ProductCategoryBusinessFunctionField } from '@/features/product-categories/product-category-business-function-field'
import { ProductCategoryAttributeLayoutEditor } from '@/features/product-categories/product-category-attribute-layout-editor'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type {
  AttributeContext,
  EffectiveAttribute,
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
  opportunity: EffectiveAttribute[] | undefined,
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
  return [...tag(product, 'product'), ...tag(opportunity, 'opportunity')]
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
  const inheritsAttributes = form.watch('inherits_attributes')
  const inheritedProductQuery = useEffectiveAttributes(parentId, 'product')
  const inheritedOpportunityQuery = useEffectiveAttributes(parentId, 'opportunity')
  // Opting out is a barrier: the category inherits nothing, so the read-only
  // inherited list must reflect that immediately (not just after save).
  // Flat, both contexts (spec 0061) — `AttributeAssignmentEditor` splits it.
  const inherited: ProductCategoryInheritedAttribute[] = useMemo(
    () =>
      inheritsAttributes ? toInheritedAttributes(inheritedProductQuery.data, inheritedOpportunityQuery.data) : [],
    [inheritedProductQuery.data, inheritedOpportunityQuery.data, inheritsAttributes],
  )

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

          {attributesVisible && (
            <FormSection
              icon={ListChecks}
              title={t('productCategories.form.sections.attributes.title')}
              description={t('productCategories.form.sections.attributes.description')}
            >
              {parentId !== null && (
                <MetaField
                  control={form.control}
                  name="inherits_attributes"
                  metaKey="inherits_attributes"
                  label={t('productCategories.form.inheritsAttributes')}
                  description={
                    <FormDescription>
                      {t('productCategories.form.inheritsAttributesHint')}
                    </FormDescription>
                  }
                >
                  {({ field, disabled }) => (
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={disabled}
                      />
                    </FormControl>
                  )}
                </MetaField>
              )}

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
                    inherited={inherited}
                    disabled={disabled}
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

      {/* Independent Save (its own PUT), mounted OUTSIDE the RHF <form> above
          so it never doubles as a category-form submit trigger. Edit only —
          the layout is authored per-category, so it needs a saved category
          to attach to (spec 0062). */}
      <div className="px-4 pb-4">
        {mode.type === 'edit' ? (
          <ProductCategoryAttributeLayoutEditor
            categoryId={mode.category.id}
            canEdit={mode.category.permissions.resource.update}
          />
        ) : (
          <FormSection
            icon={LayoutGrid}
            title={t('attributeLayout:section.title')}
            description={t('attributeLayout:section.description')}
          >
            <p className="text-xs text-muted-foreground italic">{t('attributeLayout:section.createHint')}</p>
          </FormSection>
        )}
      </div>
    </div>
  )
}
