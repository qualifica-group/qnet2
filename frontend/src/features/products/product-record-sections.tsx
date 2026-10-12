import { useTranslation } from 'react-i18next'
import { FolderTree, Package, Wallet } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { formatDecimal } from '@/features/products/column-renderers'
import {
  ProductCategoryField,
  ProductCodeField,
  ProductDescriptionField,
  ProductNameField,
  ProductTypeField,
  ProductTypologyField,
  ProductUnitField,
} from '@/features/products/product-fields'
import {
  ProductAmountField,
  ProductSupplierField,
  ProductUsagesEditor,
  ProductVatRateField,
} from '@/features/products/product-pricing-fields'
import { productPricingVisibility } from '@/features/products/product-pricing-visibility'
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
 * same editor, a newly enabled usage its amount (spec 0218 D-4).
 */
export function ProductClassificationRecordSection(props: ProductRecordSectionProps) {
  const { values, form, inline, categories, onCategoryChange, newAttributes, persistedUsages } = props
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
          editor={<ProductUsagesEditor control={control} persistedUsages={persistedUsages} />}
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
 * "Prezzi e fornitura": the amounts the usages make relevant (spec 0191: the
 * cost only for a usable-as-cost product, the price only for a sellable one),
 * the VAT rate and the supplier.
 */
export function ProductPricingRecordSection({ values, form, inline }: ProductRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const pricing = productPricingVisibility(values.usages)

  return (
    <RecordSection title={t('products.form.sections.pricing.title')} icon={<Wallet />}>
      <RecordFieldList>
        {pricing.cost ? (
          <RecordInlineField
            field="cost"
            label={t('products.form.cost')}
            inline={inline}
            editor={<ProductAmountField control={control} name="cost" />}
          >
            {formatDecimal(values.cost) || <DetailEmpty />}
          </RecordInlineField>
        ) : null}
        {pricing.price ? (
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
        <RecordInlineField
          field="supplier_id"
          label={t('products.form.supplier')}
          inline={inline}
          editor={<ProductSupplierField control={control} selected={values.supplier} />}
        >
          {values.supplier ? (
            <RecordLink domain="registries" id={values.supplier.id}>
              {values.supplier.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
