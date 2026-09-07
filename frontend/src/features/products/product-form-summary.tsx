import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Info } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { cn } from '@/lib/utils'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { FlatCategoryOption } from '@/features/product-categories/flatten-tree'
import { formatDecimal } from '@/features/products/column-renderers'
import { computeProductMargin } from '@/features/products/product-margin'
import type { ProductFormValues } from '@/features/products/use-product-form'

/**
 * The hydrated `{id, name}` refs the form holds for its relation pickers —
 * the only source of a NAME for the ids in the RHF state.
 */
export interface ProductSelectedRelations {
  vatRate: RelationFieldRef | null
  supplier: RelationFieldRef | null
  unitOfMeasure: RelationFieldRef | null
  productTypology: RelationFieldRef | null
}

interface ProductFormSummaryProps {
  control: Control<ProductFormValues>
  /** The category picker's own option list: the only place a category id has a name here. */
  categoryOptions: FlatCategoryOption[]
  selected: ProductSelectedRelations
}

/** A relation renders its name only while the hydrated ref still matches the chosen id; inventing a label after a change would be a guess. */
function relationName(ref: RelationFieldRef | null, id: number | null): string | null {
  return id !== null && ref?.id === id ? ref.name : null
}

/**
 * The form's side-column recap, in the SAME card and the SAME `label / value`
 * rows as the Opportunita' and Gestione Richieste summaries (`SummaryRow`), so
 * the record screens share one shape.
 *
 * Every row is read LIVE from the form: it recaps what is about to be saved,
 * never the persisted snapshot, so it cannot contradict the fields on the left
 * while they are being edited. The margin is the reason this recap earns its
 * place on a product: costo and prezzo are two fields apart and their
 * difference is the number the operator is actually deciding.
 */
export function ProductFormSummary({ control, categoryOptions, selected }: ProductFormSummaryProps) {
  const { t } = useTranslation()
  const code = useWatch({ control, name: 'code' })
  const categoryId = useWatch({ control, name: 'category_id' })
  const cost = useWatch({ control, name: 'cost' })
  const price = useWatch({ control, name: 'price' })
  const vatRateId = useWatch({ control, name: 'vat_rate_id' })
  const supplierId = useWatch({ control, name: 'supplier_id' })
  const unitOfMeasureId = useWatch({ control, name: 'unit_of_measure_id' })
  const productTypologyId = useWatch({ control, name: 'product_typology_id' })

  const categoryName = categoryOptions.find((option) => option.id === categoryId)?.name ?? null
  const margin = computeProductMargin(cost, price)

  return (
    <FormSection
      icon={Info}
      title={t('products.form.summary.title')}
      description={t('products.form.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('products.form.code')}>{code || EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('products.form.category')}>{categoryName ?? EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('products.form.productTypology')}>
          {relationName(selected.productTypology, productTypologyId) ?? EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('products.form.unitOfMeasure')}>
          {relationName(selected.unitOfMeasure, unitOfMeasureId) ?? EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('products.form.cost')}>{formatDecimal(cost) || EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('products.form.price')}>{formatDecimal(price) || EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('products.margin')}>
          {margin ? (
            <span className={cn('flex items-baseline gap-1.5', margin.amount < 0 && 'text-destructive')}>
              <span className="tabular-nums">{formatDecimal(margin.amount)}</span>
              {margin.percent !== null ? (
                <span className="text-xs font-normal text-muted-foreground">
                  {t('products.marginPercent', { percent: formatDecimal(margin.percent) })}
                </span>
              ) : null}
            </span>
          ) : (
            EMPTY_VALUE
          )}
        </SummaryRow>
        <SummaryRow label={t('products.form.vatRate')}>
          {relationName(selected.vatRate, vatRateId) ?? EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('products.form.supplier')}>
          {relationName(selected.supplier, supplierId) ?? EMPTY_VALUE}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}
