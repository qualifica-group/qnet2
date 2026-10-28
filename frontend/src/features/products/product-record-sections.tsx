import { useTranslation } from 'react-i18next'
import { FolderTree, Package, Truck, Wallet } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { formatDecimal } from '@/features/products/column-renderers'
import {
  ProductCategoryField,
  ProductCodeField,
  ProductCostTypeField,
  ProductDescriptionField,
  ProductNameField,
  ProductTypeField,
  ProductTypologyField,
  ProductUnitField,
} from '@/features/products/product-fields'
import { ProductAmountField, ProductVatRateField } from '@/features/products/product-pricing-fields'
import { ProductSuppliersField } from '@/features/products/product-suppliers-editor'
import { ProductUsageField } from '@/features/products/product-usage-field'
import { ProductSuppliersTable } from '@/features/products/product-suppliers-table'
import type { ProductRecordSectionProps, ProductRecordValues } from '@/features/products/product-record'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? <span className="whitespace-pre-line">{value}</span> : <DetailEmpty />
}

/** "Anagrafica": code, name, description. The code edits on create only (spec 0218 D-3). */
export function ProductIdentityRecordSection({ values, form, inline }: ProductRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('products.form.sections.identity.title')} icon={<Package />}>
      <RecordFieldList>
        <RecordInlineField
          field="code"
          label={t('products.form.code')}
          inline={inline}
          editor={<ProductCodeField control={control} />}
        >
          <TextValue value={values.code} />
        </RecordInlineField>
        <RecordInlineField
          field="name"
          label={t('products.form.name')}
          inline={inline}
          editor={<ProductNameField control={control} />}
        >
          <TextValue value={values.name} />
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('products.form.description')}
          inline={inline}
          editor={<ProductDescriptionField control={control} />}
        >
          <TextValue value={values.description} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The typology as the record shows it: its coloured badge once the color is known, the bare name on a draft. */
function TypologyValue({ typology }: { typology: ProductRecordValues['product_typology'] }) {
  if (!typology) {
    return <DetailEmpty />
  }
  return 'color' in typology ? <ProductTypologyBadge name={typology.name} color={typology.color} /> : typology.name
}

/**
 * "Classificazione": where the product sits in the catalogue and where an
 * Offerta may use it. The business function is derived from the category
 * (spec 0023), read-only. A category change brings its new attributes into the
 * same editor (spec 0218 D-4).
 */
export function ProductClassificationRecordSection(props: ProductRecordSectionProps) {
  const { values, form, inline, categories, onCategoryChange, newAttributes } = props
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('products.form.sections.classification.title')} icon={<FolderTree />}>
      <RecordFieldList>
        <RecordInlineField
          field="category_id"
          label={t('products.form.category')}
          inline={inline}
          editor={
            <ProductCategoryField
              control={control}
              categories={categories}
              onCategoryChange={onCategoryChange}
              newAttributes={newAttributes}
            />
          }
        >
          {values.category?.name ?? <DetailEmpty />}
        </RecordInlineField>
        {values.business_function ? (
          <RecordField label={t('products.columns.business_function')}>{values.business_function.name}</RecordField>
        ) : null}
        <RecordInlineField
          field="product_typology_id"
          label={t('products.form.productTypology')}
          inline={inline}
          editor={<ProductTypologyField control={control} selected={values.product_typology} />}
        >
          <TypologyValue typology={values.product_typology} />
        </RecordInlineField>
        <RecordInlineField
          field="unit_of_measure_id"
          label={t('products.form.unitOfMeasure')}
          inline={inline}
          editor={<ProductUnitField control={control} selected={values.unit_of_measure} />}
        >
          {values.unit_of_measure?.name ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="cost_type_id"
          label={t('products.form.costType')}
          inline={inline}
          editor={<ProductCostTypeField control={control} selected={values.cost_type} />}
        >
          {values.cost_type ? <CostTypeBadge name={values.cost_type.name} color={values.cost_type.color} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="product_type"
          label={t('products.form.productType')}
          inline={inline}
          editor={<ProductTypeField control={control} />}
        >
          <Badge variant="secondary">{enumLabelOf('product_type', values.product_type)}</Badge>
        </RecordInlineField>
        <RecordInlineField
          field="usages"
          label={t('products.form.usages')}
          inline={inline}
          editor={<ProductUsageField control={control} />}
        >
          {values.usages.length > 0 ? (
            <span className="flex flex-wrap gap-1">
              {values.usages.map((usage) => (
                <Badge key={usage} variant="outline">
                  {enumLabelOf('product_usage', usage)}
                </Badge>
              ))}
            </span>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Prezzi e fornitura": the Standard cost and price, shown only for a product
 * WITHOUT suppliers and for every usage (spec 0320 rev.3 D-20), and the VAT rate.
 * The suppliers have their own full-width section below.
 */
export function ProductPricingRecordSection({ values, form, inline }: ProductRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('products.form.sections.pricing.title')} icon={<Wallet />}>
      <RecordFieldList>
        {values.suppliers.length === 0 ? (
          <RecordInlineField
            field="cost"
            label={t('products.form.cost')}
            inline={inline}
            editor={<ProductAmountField control={control} name="cost" />}
          >
            {formatDecimal(values.cost) || <DetailEmpty />}
          </RecordInlineField>
        ) : null}
        {values.suppliers.length === 0 ? (
          <RecordInlineField
            field="price"
            label={t('products.form.price')}
            inline={inline}
            editor={<ProductAmountField control={control} name="price" />}
          >
            {formatDecimal(values.price) || <DetailEmpty />}
          </RecordInlineField>
        ) : null}
        <RecordInlineField
          field="vat_rate_id"
          label={t('products.form.vatRate')}
          inline={inline}
          editor={<ProductVatRateField control={control} selected={values.vat_rate} />}
        >
          {values.vat_rate?.name ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Fornitori" (spec 0320): the product's price list per supplier across the full grid width — identity, price,
 * cost (or the commission replacing it) and margin — with the supplier count next to the title. Edited in place.
 */
export function ProductSuppliersRecordSection({ values, form, inline, productId }: ProductRecordSectionProps) {
  const { t } = useTranslation()
  const count = values.suppliers.length

  return (
    <RecordSection
      full
      title={t('products.form.suppliers.title')}
      icon={<Truck />}
      action={count > 0 ? <Badge variant="secondary" className="px-1.5 py-0 text-[11px] tabular-nums">{count}</Badge> : null}
    >
      <RecordInlineField
        field="suppliers"
        label={t('products.form.suppliers.title')}
        layout="block"
        inline={inline}
        editor={<ProductSuppliersField control={form.control} productId={productId} />}
      >
        <ProductSuppliersTable suppliers={values.suppliers} />
      </RecordInlineField>
    </RecordSection>
  )
}
