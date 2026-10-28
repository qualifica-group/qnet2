import { useTranslation } from 'react-i18next'
import { FolderTree, Hash, Loader2, Package, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { ProductStatsStrip } from '@/features/products/product-detail-header'
import { effectiveAmountOfRows } from '@/features/products/product-supplier-rows'
import type { ProductRecordValues } from '@/features/products/product-record'

interface ProductFormHeaderProps {
  /** The draft as the rows read it: name, code and category name the product being created. */
  values: ProductRecordValues
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the product create form, the detail's own
 * (`ProductDetailHeader` + `ProductStatsStrip`, spec 0218): monogram and title
 * follow the name being typed, the pills the code and the category, the strip
 * the amounts; the actions sit where the detail has none. A refused submit is
 * reported right under the actions.
 */
export function ProductFormHeader({ values, formId, isSubmitting, submitError, onCancel }: ProductFormHeaderProps) {
  const { t } = useTranslation()
  const heading = values.name.trim() || t('products.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Package />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={t('products.form.createSubtitle')}
        badges={
          <>
            {values.code ? (
              <Badge variant="outline">
                <Hash aria-hidden="true" />
                {values.code}
              </Badge>
            ) : null}
            {values.category ? (
              <Badge variant="outline">
                <FolderTree aria-hidden="true" />
                {values.category.name}
              </Badge>
            ) : null}
          </>
        }
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('products.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('products.form.saving') : t('products.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <ProductStatsStrip
        cost={effectiveAmountOfRows(values.suppliers, 'cost', values.cost)}
        price={effectiveAmountOfRows(values.suppliers, 'price', values.price)}
      />
    </>
  )
}
