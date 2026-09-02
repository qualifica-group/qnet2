/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Info } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type {
  ProductCategorySummary,
  ProductTypologySummary,
  ProductUnitOfMeasureSummary,
} from '@/features/products/types'

/**
 * Formats a decimal amount using the active UI locale, em dash when null/invalid (spec AC-025).
 * Accepts a numeric string too: Laravel's `decimal:2` cast serializes cost/price as strings
 * (e.g. "10.00"), so a strict `typeof === 'number'` check would blank every value.
 */
export function formatDecimal(value: unknown): string {
  const numeric =
    typeof value === 'number'
      ? value
      : typeof value === 'string' && value.trim() !== ''
        ? Number(value)
        : NaN
  if (!Number.isFinite(numeric)) {
    return ''
  }
  return new Intl.NumberFormat(i18n.language, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric)
}

/** Renders a `cost`/`price` decimal cell, em dash when null. */
function DecimalCell({ value }: ICellRendererParams) {
  const formatted = formatDecimal(value)
  return formatted ? <span>{formatted}</span> : <span className="text-muted-foreground">—</span>
}

/** Renders the `category` column: the derived category name, em dash when unset (spec AC-025). */
function CategoryCell({ value }: ICellRendererParams) {
  const category = value as ProductCategorySummary | null
  return category ? (
    <span>{category.name}</span>
  ) : (
    <span className="text-muted-foreground">—</span>
  )
}

/**
 * Renders the `product_typology` column (spec 0099, AC-033): the derived
 * typology name, em dash when unset. Plain text like `category` — unlike
 * `product_type` right next to it, which is an enum badge (D-1).
 */
function ProductTypologyCell({ value }: ICellRendererParams) {
  const typology = value as ProductTypologySummary | null
  return typology ? (
    <span>{typology.name}</span>
  ) : (
    <span className="text-muted-foreground">—</span>
  )
}

/**
 * Renders the `unit_of_measure` column (spec 0088): the unit's SYMBOL as a
 * badge — the compact form, the same one the offer lines show — with an info
 * trigger revealing the full name on hover/focus. The trigger is a real
 * `button` carrying the name as its accessible label, so the name reaches
 * keyboard and screen-reader users too and is never colour/hover-only. Em
 * dash when the product has no unit.
 */
function UnitOfMeasureCell({ value }: ICellRendererParams) {
  const unit = value as ProductUnitOfMeasureSummary | null

  if (!unit) {
    return <span className="text-muted-foreground">—</span>
  }

  return (
    <div className="flex h-full items-center gap-1 overflow-hidden">
      <Badge variant="secondary" className="h-5 min-h-5">
        {unit.symbol}
      </Badge>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              type="button"
              aria-label={unit.name}
              className="rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
              <Info aria-hidden="true" className="size-3.5" />
            </button>
          </TooltipTrigger>
          <TooltipContent side="top" variant="light">
            {unit.name}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`description`
 * fall back to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer (mirrors `referentTypeColumnRenderers`).
 */
export const productColumnRenderers: TableRendererMap = {
  cost: (params) => <DecimalCell {...params} />,
  price: (params) => <DecimalCell {...params} />,
  category: (params) => <CategoryCell {...params} />,
  unit_of_measure: (params) => <UnitOfMeasureCell {...params} />,
  product_typology: (params) => <ProductTypologyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
