import { useTranslation } from 'react-i18next'
import { Hash, Package, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { enumLabelOf } from '@/features/config/enum-label'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { formatDecimal } from '@/features/products/column-renderers'
import { computeProductMargin } from '@/features/products/product-margin'
import { productPricingVisibility } from '@/features/products/product-pricing-visibility'
import type { ProductDetailWithPermissions, ProductUsage } from '@/features/products/types'

/**
 * Identity band and KPI strip of the product record card. Kept in one file:
 * both read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

/** One column per visible KPI tile (price / cost / margin, spec 0191), never the strip's default four. */
const STAT_STRIP_CLASS: Record<number, string> = {
  1: '@2xl:grid-cols-1',
  2: '@2xl:grid-cols-2',
  3: '@2xl:grid-cols-3',
}

/**
 * Identity band: monogram, name, the category as subtitle, the code, type and
 * typology as pills. No edit action: the record edits in place (spec 0218).
 */
export function ProductDetailHeader({ product }: { product: ProductDetailWithPermissions }) {
  return (
    <RecordCardHeader
      media={<DetailMonogram name={product.name} icon={<Package />} className="size-10 text-base [&>svg]:size-5" />}
      title={product.name}
      legacyId={product.old_id}
      subtitle={product.category?.name}
      badges={
        <>
          <Badge variant="outline">
            <Hash aria-hidden="true" />
            {product.code}
          </Badge>
          <Badge variant="secondary">{enumLabelOf('product_type', product.product_type)}</Badge>
          {product.product_typology ? (
            <ProductTypologyBadge name={product.product_typology.name} color={product.product_typology.color} />
          ) : null}
        </>
      }
    />
  )
}

interface ProductStatsStripProps {
  cost: string | number | null
  price: string | number | null
  usages: readonly ProductUsage[]
}

/**
 * KPI strip: price, cost and their margin — each only where the usages make
 * it relevant (spec 0191). The persisted amounts on the detail, the draft's,
 * live, on create (spec 0218). Renders nothing when no tile applies.
 */
export function ProductStatsStrip({ cost, price, usages }: ProductStatsStripProps) {
  const { t } = useTranslation()
  const pricing = productPricingVisibility(usages)
  const margin = computeProductMargin(cost, price)
  const statCount = Number(pricing.price) + Number(pricing.cost) + Number(pricing.margin)

  if (statCount === 0) {
    return null
  }

  return (
    <RecordStatStrip className={STAT_STRIP_CLASS[statCount]}>
      {pricing.price ? (
        <RecordStat
          label={t('products.columns.price')}
          icon={<TrendingUp aria-hidden="true" />}
          value={formatDecimal(price) || <DetailEmpty />}
        />
      ) : null}
      {pricing.cost ? (
        <RecordStat
          label={t('products.columns.cost')}
          icon={<TrendingDown aria-hidden="true" />}
          value={formatDecimal(cost) || <DetailEmpty />}
        />
      ) : null}
      {pricing.margin ? (
        <RecordStat
          label={t('products.margin')}
          icon={<Wallet aria-hidden="true" />}
          value={
            margin ? (
              <span className={cn(margin.amount < 0 && 'text-destructive')}>{formatDecimal(margin.amount)}</span>
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
      ) : null}
    </RecordStatStrip>
  )
}
