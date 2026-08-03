import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Package } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useEnumOptions } from '@/features/config/use-config'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { flattenCategoryTree } from '@/features/product-categories/flatten-tree'
import { useProductForm } from '@/features/products/use-product-form'
import { ProductDynamicFields } from '@/features/products/product-dynamic-fields'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'
import type { ProductDetail, ProductFormMode, ProductType } from '@/features/products/types'

/** Hoisted so the create-mode memo keeps a stable reference across renders. */
const EMPTY_CATEGORY_IDS: readonly number[] = []

/** Filters the supplier picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }

interface ProductFormBodyProps {
  mode: ProductFormMode
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
  /** Create-only: the sequential code suggestion prefilled into the `code` field (spec 0065). */
  initialCode?: string
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/** Placeholder shown for the manual `code` field in create, declaring the server-generation fallback (spec 0065, mirrors `ProjectFormBody`). */
const CODE_PLACEHOLDER_KEY = 'products.form.codePlaceholder'

/**
 * The product create/edit form UI: generic fields (name, description, cost,
 * price, category) wrapped in `MetaField` (spec 0004), followed by the
 * universal custom fields section (spec 0021). All non-render logic lives in
 * `useProductForm`.
 */
export function ProductFormBody({ mode, onSuccess, onCancel, initialCode }: ProductFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission, canResource } = useResourcePermissions()
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
  const productTypeOptions = useEnumOptions('product_type')

  // Spec 0074: this picker is a DESTINATION served by the structural tree
  // cache, so it filters client-side. The product's saved category is kept
  // even when unselectable (D-3b) — the server accepts it unchanged, and the
  // select must not blank out on an untouched edit.
  const savedCategoryIds = useMemo(
    () => (mode.type === 'edit' ? [mode.product.category_id] : EMPTY_CATEGORY_IDS),
    [mode],
  )
  const categoryOptions = useMemo(
    () =>
      flattenCategoryTree(treeQuery.data ?? [], {
        selectableOnly: true,
        keepIds: savedCategoryIds,
      }),
    [treeQuery.data, savedCategoryIds],
  )

  // Edit-mode hydration for the relation pickers below: the loaded product's
  // `{id, name}` projections, already the shape `RelationSelectField` expects.
  const selectedVatRate = mode.type === 'edit' ? mode.product.vat_rate : null
  const selectedSupplier = mode.type === 'edit' ? mode.product.supplier : null
  const selectedState = mode.type === 'edit' ? mode.product.state : null

  const identityVisible =
    fieldPermission('code').visible ||
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('cost').visible ||
    fieldPermission('price').visible ||
    fieldPermission('category_id').visible ||
    fieldPermission('product_type').visible ||
    fieldPermission('vat_rate_id').visible ||
    fieldPermission('supplier_id').visible ||
    fieldPermission('state_id').visible

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
              icon={Package}
              title={t('products.form.sections.identity.title')}
              description={t('products.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="code"
                metaKey="code"
                label={t('products.form.code')}
                hint={t('products.form.hints.code')}
                hintLabel={t('products.form.code')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="off"
                      disabled={disabled}
                      readOnly={readOnly}
                      placeholder={t(CODE_PLACEHOLDER_KEY)}
                      {...field}
                      value={field.value ?? ''}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('products.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('products.form.description')}
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

              <MetaField
                control={form.control}
                name="cost"
                metaKey="cost"
                label={t('products.form.cost')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? null : Number(event.target.value))
                      }
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="price"
                metaKey="price"
                label={t('products.form.price')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(event.target.value === '' ? null : Number(event.target.value))
                      }
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="category_id"
                metaKey="category_id"
                label={t('products.form.category')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <SearchableSelect
                      value={field.value}
                      onChange={(next) => {
                        field.onChange(next)
                        onCategoryChange(next)
                      }}
                      options={categoryOptions}
                      isPending={treeQuery.isPending}
                      isError={treeQuery.isError}
                      onRetry={() => void treeQuery.refetch()}
                      disabled={disabled}
                      labels={{
                        placeholder: t('products.form.categoryPlaceholder'),
                        searchPlaceholder: t('products.form.categorySearch'),
                        empty: t('products.form.categoryEmpty'),
                        noMatch: t('products.form.categoryNoMatch'),
                        error: t('products.form.categoryError'),
                        retry: t('common.retry'),
                      }}
                    />
                  </FormControl>
                )}
              </MetaField>

              <RelationSelectField
                control={form.control}
                name="vat_rate_id"
                metaKey="vat_rate_id"
                label={t('products.form.vatRate')}
                resource={VAT_RATES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('products.form.vatRateSearch')}
                selected={selectedVatRate}
                placeholder={t('products.form.vatRatePlaceholder')}
                emptyLabel={t('products.form.vatRateEmpty')}
                errorLabel={t('products.form.vatRateError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />

              <RelationSelectField
                control={form.control}
                name="supplier_id"
                metaKey="supplier_id"
                label={t('products.form.supplier')}
                resource={REGISTRIES_FOR_SELECT_RESOURCE}
                params={SUPPLIER_PARAMS}
                searchPlaceholder={t('products.form.supplierSearch')}
                selected={selectedSupplier}
                placeholder={t('products.form.supplierPlaceholder')}
                emptyLabel={t('products.form.supplierEmpty')}
                errorLabel={t('products.form.supplierError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />

              <RelationSelectField
                control={form.control}
                name="state_id"
                metaKey="state_id"
                label={t('products.form.state')}
                resource={STATES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('products.form.stateSearch')}
                selected={selectedState}
                placeholder={t('products.form.statePlaceholder')}
                emptyLabel={t('products.form.stateEmpty')}
                errorLabel={t('products.form.stateError')}
                clearLabel={t('common.clear')}
                retryLabel={t('common.retry')}
              />

              <MetaField
                control={form.control}
                name="product_type"
                metaKey="product_type"
                label={t('products.form.productType')}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value}
                    onValueChange={(next) => field.onChange(next as ProductType)}
                    disabled={disabled}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {productTypeOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>
            </FormSection>
          )}

          <ProductDynamicFields
            control={form.control}
            attributes={productAttributes}
            layout={productLayout}
            mode={layoutFormMode}
            isLoading={productAttributesLoading}
            disabled={!attributesEditable}
          />

          <CustomFieldsSection resource="products" control={form.control} />

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
              {t('products.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('products.form.saving') : t('products.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
