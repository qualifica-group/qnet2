import { useTranslation } from 'react-i18next'
import { Check } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { defaultProductSupplierOf, productCostFor, productPriceFor } from '@/features/products/product-suppliers'
import { formatEuro } from '@/features/invoices/invoice-format'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import type { QuoteLineVariant } from '@/features/quotes/use-quote-lines-field'
import type { QuoteCatalogProduct } from './types'

interface QuoteCatalogItemProps {
  item: QuoteCatalogProduct
  /** Which of the product's prices the row will take (D-6 of spec 0065): shown on the item. */
  variant: QuoteLineVariant
  /** "Radice > Categoria", resolved by the caller off the cached category tree. */
  categoryPath: string | null
  /** `single` mode: a plain pick, no checkbox. */
  singleSelect: boolean
  selected: boolean
  /** Already on a row where the channel forbids repeats: shown, never pickable. */
  alreadyAdded: boolean
  /** Not pickable for another reason: the selection already fills every free row. */
  disabled: boolean
  onActivate: (item: QuoteCatalogProduct) => void
}

/**
 * One product of the catalogue (spec 0268 AC-003, 0269), CRM-style: the name and
 * the commercial facts that decide the pick (typology, price per unit) on the
 * first line, code and category path on the second, the description last.
 * The whole row is the option: the checkbox is its visual state, not a second
 * focus stop.
 */
export function QuoteCatalogItem({
  item,
  variant,
  categoryPath,
  singleSelect,
  selected,
  alreadyAdded,
  disabled,
  onActivate,
}: QuoteCatalogItemProps) {
  const { t } = useTranslation()
  const blocked = alreadyAdded || disabled
  const { meta } = item
  const defaultSupplierId = defaultProductSupplierOf(meta)?.supplier_id ?? null
  const price = formatEuro(variant === 'revenue' ? productPriceFor(meta, defaultSupplierId) : productCostFor(meta, defaultSupplierId))
  const unit = meta.unit_of_measure?.symbol

  const activate = () => {
    if (!blocked) {
      onActivate(item)
    }
  }

  return (
    <div
      role="option"
      aria-selected={selected}
      aria-disabled={blocked}
      tabIndex={0}
      onClick={activate}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          activate()
        }
      }}
      className={cn(
        'flex gap-2.5 rounded-md border border-transparent px-2.5 py-2 text-left outline-none',
        blocked
          ? 'cursor-not-allowed opacity-60'
          : 'cursor-pointer hover:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50',
        selected && 'border-primary/40 bg-primary/5',
      )}
    >
      {singleSelect ? null : (
        <span
          aria-hidden="true"
          className={cn(
            'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-field-border bg-field',
            (selected || alreadyAdded) && 'border-primary bg-primary text-primary-foreground',
          )}
        >
          {selected || alreadyAdded ? <Check className="size-3.5" /> : null}
        </span>
      )}

      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <div className="flex min-w-0 items-start gap-2">
          <span className="min-w-0 flex-1 text-sm font-medium leading-5 break-words">{item.label}</span>
          {meta.product_typology ? (
            <ProductTypologyBadge name={meta.product_typology.name} color={meta.product_typology.color} />
          ) : null}
          <span className="shrink-0 text-xs leading-5 font-medium tabular-nums">
            {price === '' ? (
              <span className="font-normal text-muted-foreground">{t('quotes.lineEditor.catalog.noPrice')}</span>
            ) : (
              <>
                {price}
                {unit ? <span className="font-normal text-muted-foreground"> / {unit}</span> : null}
              </>
            )}
          </span>
        </div>

        <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
          {meta.code ? <span className="shrink-0 font-mono">{meta.code}</span> : null}
          {meta.code && categoryPath ? <span aria-hidden="true">·</span> : null}
          {categoryPath ? <span className="truncate" title={categoryPath}>{categoryPath}</span> : null}
          {alreadyAdded ? (
            <Badge variant="secondary" className="ml-auto px-1.5 py-0 text-[11px]">
              {t('quotes.lineEditor.catalog.alreadyAdded')}
            </Badge>
          ) : null}
        </div>

        {meta.description ? (
          <p className="line-clamp-2 text-xs text-muted-foreground" title={meta.description}>
            {meta.description}
          </p>
        ) : null}
      </div>
    </div>
  )
}
