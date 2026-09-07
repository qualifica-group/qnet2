import { useTranslation } from 'react-i18next'
import { FolderTree, Hash, History, Package, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
  RecordStat,
  RecordStatStrip,
} from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { formatDecimal } from '@/features/products/column-renderers'
import { computeProductMargin } from '@/features/products/product-margin'
import { ProductAttributeValuesSection } from '@/features/products/product-attribute-values-section'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Three KPI tiles, not the strip's default four: a product prices on exactly these numbers. */
const STAT_STRIP_CLASS = '@2xl:grid-cols-3'

interface ProductDetailViewProps {
  product: ProductDetailWithPermissions
}

/**
 * Read-only detail of a single product, rendered as the same enterprise-CRM
 * record as Opportunita' (`@/components/detail/record-panel` + the shared
 * `record-layout` grid): identity band, the price/cost/margin KPI strip, the
 * spec-sheet sections, then the category-driven attributes; the activity log
 * takes the side column when the actor may read it, and the record keeps the
 * full width when they may not.
 *
 * Purely presentational: the caller (the dedicated page, or the module Sheet)
 * fetches the fresh, re-authorized detail and passes it down.
 */
export function ProductDetailView({ product }: ProductDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(product.created_at)
  const canViewActivity = product.permissions.actions.view_activity === true
  const margin = computeProductMargin(product.cost, product.price)
  const productTypeLabel = enumLabelOf('product_type', product.product_type)

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, canViewActivity && RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <RecordCardHeader
              media={
                <DetailMonogram
                  name={product.name}
                  icon={<Package />}
                  className="size-10 text-base [&>svg]:size-5"
                />
              }
              title={product.name}
              subtitle={product.category?.name}
              badges={
                <>
                  <Badge variant="outline">
                    <Hash aria-hidden="true" />
                    {product.code}
                  </Badge>
                  <Badge variant="secondary">{productTypeLabel}</Badge>
                  {product.product_typology ? (
                    <Badge variant="outline">{product.product_typology.name}</Badge>
                  ) : null}
                </>
              }
            />

            <RecordStatStrip className={STAT_STRIP_CLASS}>
              <RecordStat
                label={t('products.columns.price')}
                icon={<TrendingUp aria-hidden="true" />}
                value={formatDecimal(product.price) || <DetailEmpty />}
              />
              <RecordStat
                label={t('products.columns.cost')}
                icon={<TrendingDown aria-hidden="true" />}
                value={formatDecimal(product.cost) || <DetailEmpty />}
              />
              <RecordStat
                label={t('products.margin')}
                icon={<Wallet aria-hidden="true" />}
                value={
                  margin ? (
                    <span className={cn(margin.amount < 0 && 'text-destructive')}>
                      {formatDecimal(margin.amount)}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )
                }
                hint={
                  margin && margin.percent !== null
                    ? t('products.marginPercent', { percent: formatDecimal(margin.percent) })
                    : undefined
                }
              />
            </RecordStatStrip>

            <RecordSectionsGrid>
              <RecordSection
                title={t('products.form.sections.identity.title')}
                icon={<Package />}
              >
                <RecordFieldList>
                  <RecordField label={t('products.form.code')}>{product.code}</RecordField>
                  <RecordField label={t('products.columns.product_type')}>
                    <Badge variant="secondary">{productTypeLabel}</Badge>
                  </RecordField>
                  <RecordField label={t('products.columns.description')}>
                    {product.description ?? <DetailEmpty />}
                  </RecordField>
                </RecordFieldList>
              </RecordSection>

              <RecordSection
                title={t('products.form.sections.classification.title')}
                icon={<FolderTree />}
              >
                <RecordFieldList>
                  <RecordField label={t('products.columns.category')}>
                    {product.category?.name ?? <DetailEmpty />}
                  </RecordField>
                  {product.business_function ? (
                    <RecordField label={t('products.columns.business_function')}>
                      {product.business_function.name}
                    </RecordField>
                  ) : null}
                  {product.product_typology ? (
                    <RecordField label={t('products.form.productTypology')}>
                      {product.product_typology.name}
                    </RecordField>
                  ) : null}
                  {product.unit_of_measure ? (
                    <RecordField label={t('products.form.unitOfMeasure')}>
                      {product.unit_of_measure.name}
                    </RecordField>
                  ) : null}
                </RecordFieldList>
              </RecordSection>

              {/* Absent as a whole when neither is assigned: an empty
                  "Prezzi e fornitura" card would read as data we failed to
                  load rather than as data nobody filled in. */}
              {product.vat_rate || product.supplier ? (
                <RecordSection
                  title={t('products.form.sections.pricing.title')}
                  icon={<Wallet />}
                  full
                >
                  <RecordFieldList>
                    {product.vat_rate ? (
                      <RecordField label={t('products.form.vatRate')}>
                        {product.vat_rate.name}
                      </RecordField>
                    ) : null}
                    {product.supplier ? (
                      <RecordField label={t('products.form.supplier')}>
                        {product.supplier.name}
                      </RecordField>
                    ) : null}
                  </RecordFieldList>
                </RecordSection>
              ) : null}
            </RecordSectionsGrid>
          </RecordCard>

          {/* Its own card(s) on the canvas, never nested in the record card:
              a configured layout already renders section cards of its own. */}
          <ProductAttributeValuesSection
            layout={product.attribute_layout ?? null}
            attributes={product.applicable_attributes ?? []}
            values={product.attribute_values ?? {}}
          />
        </div>

        {canViewActivity ? (
          <div className={RECORD_COLUMN_CLASS}>
            <RecordCard>
              <div className="flex items-center gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase [&>svg]:size-3.5">
                  <History aria-hidden="true" />
                  {t('activityLog.title')}
                </h2>
              </div>
              <div className="min-w-0 p-4">
                <ActivityLogSection resource="products" id={product.id} />
              </div>
            </RecordCard>
          </div>
        ) : null}
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('products.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
