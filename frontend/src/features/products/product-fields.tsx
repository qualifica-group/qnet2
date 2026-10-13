import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE } from '@/features/product-typologies/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import type { ProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type { ProductCostTypeSummary, ProductType } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/*
 * The product's identity and classification fields, one component each,
 * shared by the detail's in-place rows and the create draft's rows (spec 0218).
 */

interface FieldProps {
  control: Control<ProductFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

/** "Codice" (spec 0065): editable on create only — the field permission makes it read-only afterwards. */
export function ProductCodeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
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
            placeholder={t('products.form.codePlaceholder')}
            {...field}
            value={field.value ?? ''}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function ProductNameField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="name" metaKey="name" label={t('products.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function ProductDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="description" metaKey="description" label={t('products.form.description')}>
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
  )
}

interface ProductCategoryFieldProps extends FieldProps {
  categories: ProductCategoryPicker
  /** Kept alongside `field.onChange`: the category drives the dynamic attribute set (spec 0061). */
  onCategoryChange: (next: number) => void
  /** The attributes the picked category adds (detail): filled right here, or a required one refuses the save. */
  newAttributes: EffectiveAttribute[]
}

/** "Categoria": the field the attributes depend on. */
export function ProductCategoryField({ control, categories, onCategoryChange, newAttributes }: ProductCategoryFieldProps) {
  const { t } = useTranslation()
  return (
    <div className="flex min-w-0 flex-col gap-3">
      <MetaField control={control} name="category_id" metaKey="category_id" label={t('products.form.category')}>
        {({ field, disabled }) => (
          <FormControl>
            <SearchableSelect
              value={field.value}
              onChange={(next) => {
                field.onChange(next)
                onCategoryChange(next)
              }}
              options={categories.options}
              isPending={categories.isPending}
              isError={categories.isError}
              onRetry={categories.onRetry}
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
      {newAttributes.length > 0 ? (
        <div className="flex flex-col gap-3 rounded-md border border-border/60 bg-card p-3">
          <p className="text-xs font-semibold text-muted-foreground">{t('products.detail.newAttributes')}</p>
          {newAttributes.map((attribute) => (
            <AttributeLayoutField key={attribute.code} control={control} attribute={attribute} disabled={false} readOnly={false} />
          ))}
        </div>
      ) : null}
    </div>
  )
}

/** "Tipologia" (spec 0099). */
export function ProductTypologyField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="product_typology_id"
      metaKey="product_typology_id"
      label={t('products.form.productTypology')}
      resource={PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('products.form.productTypologySearch')}
      selected={selected}
      placeholder={t('products.form.productTypologyPlaceholder')}
      emptyLabel={t('products.form.productTypologyEmpty')}
      errorLabel={t('products.form.productTypologyError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

interface CostTypeFieldProps extends FieldProps {
  selected: ProductCostTypeSummary | null
}

/** "Tipo di costo" (spec 0251): the default classification of the product's costs, with the coloured badge. */
export function ProductCostTypeField({ control, selected }: CostTypeFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="cost_type_id"
      metaKey="cost_type_id"
      label={t('products.form.costType')}
      resource={COST_TYPES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('products.form.costTypeSearch')}
      selected={selected ? { id: selected.id, name: selected.name, meta: { color: selected.color } } : null}
      placeholder={t('products.form.costTypePlaceholder')}
      emptyLabel={t('products.form.costTypeEmpty')}
      errorLabel={t('products.form.costTypeError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
      renderItem={costTypeOptionRenderer}
    />
  )
}

/** "Unita' di misura" (spec 0088). */
export function ProductUnitField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="unit_of_measure_id"
      metaKey="unit_of_measure_id"
      label={t('products.form.unitOfMeasure')}
      resource={UNITS_OF_MEASURE_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('products.form.unitOfMeasureSearch')}
      selected={selected}
      placeholder={t('products.form.unitOfMeasurePlaceholder')}
      emptyLabel={t('products.form.unitOfMeasureEmpty')}
      errorLabel={t('products.form.unitOfMeasureError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Tipo prodotto": the `product_type` enum. */
export function ProductTypeField({ control }: FieldProps) {
  const { t } = useTranslation()
  const options = useEnumOptions('product_type')
  return (
    <MetaField control={control} name="product_type" metaKey="product_type" label={t('products.form.productType')}>
      {({ field, disabled }) => (
        <Select value={field.value} onValueChange={(next) => field.onChange(next as ProductType)} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}
