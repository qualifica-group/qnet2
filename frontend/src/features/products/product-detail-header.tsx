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
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Identity band and KPI strip of the product record card. Kept in one file:
 * both read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

/** Three KPI tiles (price / cost / margin), never the strip's default four. */
const STAT_STRIP_CLASS = '@2xl:grid-cols-3'

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
  /** Effective cost: the default supplier's, else the standard one (spec 0320 D-4). */
  cost: string | number | null
  /** Effective price: the default supplier's, else the standard one (rev.2 D-18). */
  price: string | number | null
}

/**
 * KPI strip: price, cost and their margin, for every product (spec 0320 rev.3
 * D-20). The persisted amounts on the detail, the draft's, live, on create
 * (spec 0218).
 */
export function ProductStatsStrip({ cost, price }: ProductStatsStripProps) {
  const { t } = useTranslation()
  const margin = computeProductMargin(cost, price)

  return (
    <RecordStatStrip className={STAT_STRIP_CLASS}>
      <RecordStat
        label={t('products.columns.price')}
        icon={<TrendingUp aria-hidden="true" />}
        value={formatDecimal(price) || <DetailEmpty />}
      />
      <RecordStat
        label={t('products.columns.cost')}
        icon={<TrendingDown aria-hidden="true" />}
        value={formatDecimal(cost) || <DetailEmpty />}
      />
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
    </RecordStatStrip>
  )
}
