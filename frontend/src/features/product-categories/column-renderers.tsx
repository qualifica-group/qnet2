/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { TableRow } from '@/features/table/types'

/** A minimal named entity, as carried by a row's `attributes`/`products` arrays. */
interface NamedEntity {
  id: number
  name: string
}

/** Hoisted empty-array default: a stable reference avoids a new array per render. */
const EMPTY_NAMES: NamedEntity[] = []

/** Shared cell wrapper: vertically centered with breathing room from the edges (mirrors the generic table cells). */
const CELL_WRAPPER = 'flex h-full w-full items-center justify-center px-2 py-1 overflow-hidden'

/** Consistent pill height so the count badge aligns with every other badge column. */
const BADGE_BASE = 'h-5 min-h-5'

/** Renders the `parent` column: the parent category's name, em dash for a root category. */
function ParentCell({ value }: ICellRendererParams) {
  const parent = value as { id: number; name: string } | null
  return parent ? <span>{parent.name}</span> : <span className="text-muted-foreground">—</span>
}

/** Narrows a row's raw field to a `NamedEntity[]`, defensively falling back to empty. */
function namedEntitiesOf(value: unknown): NamedEntity[] {
  return Array.isArray(value) ? (value as NamedEntity[]) : EMPTY_NAMES
}

/** Builds the badge's accessible name: the full name list, plus the overflow note when present. */
function accessibleLabel(names: NamedEntity[], overflowLabel: string | undefined): string {
  const joined = names.map((entity) => entity.name).join(', ')
  if (!overflowLabel) {
    return joined
  }
  return joined ? `${joined}, ${overflowLabel}` : overflowLabel
}

interface CountWithNamesCellProps {
  count: number
  names: NamedEntity[]
  /** Extra tooltip line when the names list was capped server-side (e.g. products, capped at 100). */
  overflowLabel?: string
}

/**
 * Shared shape for the `attributes_count`/`products_count` cells: a compact
 * badge with the total, revealing the underlying names in a hover/focus
 * tooltip — mirrors the generic table's `TagsCountCell`/`ContactsCell`
 * count-plus-tooltip pattern instead of inventing a new one. Ternary (not
 * `&&`) guards the zero-count case so a literal `0` is never rendered as the
 * cell's only content.
 */
function CountWithNamesCell({ count, names, overflowLabel }: CountWithNamesCellProps) {
  return count > 0 ? (
    <div className={CELL_WRAPPER}>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant="secondary"
              className={cn(BADGE_BASE, 'cursor-default tabular-nums')}
              tabIndex={0}
              aria-label={accessibleLabel(names, overflowLabel)}
            >
              {count}
            </Badge>
          </TooltipTrigger>
          <TooltipContent side="top" variant="light" className="max-h-64 max-w-64 overflow-y-auto p-0">
            <ul className="flex flex-col divide-y">
              {names.length === 0 ? (
                <li className="px-3 py-1.5 text-sm text-muted-foreground">
                  {i18n.t('productCategories.columns.tooltipEmpty')}
                </li>
              ) : (
                names.map((entity) => (
                  <li key={entity.id} className="px-3 py-1.5 text-sm">
                    {entity.name}
                  </li>
                ))
              )}
              {overflowLabel ? (
                <li className="px-3 py-1.5 text-xs text-muted-foreground">{overflowLabel}</li>
              ) : null}
            </ul>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  ) : (
    <div className={CELL_WRAPPER}>
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders `attributes_count`: the category's own assigned attributes, one name per tooltip line. */
function AttributesCountCell({ value, data }: ICellRendererParams) {
  const row = data as TableRow | undefined
  const count = typeof value === 'number' ? value : 0
  return <CountWithNamesCell count={count} names={namedEntitiesOf(row?.attributes)} />
}

/**
 * Renders `products_count`: the category's products, one name per tooltip
 * line. The backend caps the hydrated `products` list at 100; when the real
 * total exceeds what was hydrated, a final "+N more" line is appended.
 */
function ProductsCountCell({ value, data }: ICellRendererParams) {
  const row = data as TableRow | undefined
  const count = typeof value === 'number' ? value : 0
  const names = namedEntitiesOf(row?.products)
  const overflow = count - names.length
  const overflowLabel =
    overflow > 0 ? i18n.t('productCategories.columns.productsMore', { count: overflow }) : undefined

  return <CountWithNamesCell count={count} names={names} overflowLabel={overflowLabel} />
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`description`
 * fall back to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer (mirrors `productColumnRenderers`).
 *
 * `requires_quote`/`is_selectable` are NATIVE boolean columns: the generic
 * fallbacks in `column-defaults` only format `source:'custom'|'attribute'`
 * ones, so without an explicit renderer AG Grid would stringify the raw
 * boolean ("true"/"false"). Both reuse the shared `BooleanBadgeCell`, like
 * every other module's `is_active`/`is_default`.
 */
export const productCategoryColumnRenderers: TableRendererMap = {
  parent: (params) => <ParentCell {...params} />,
  requires_quote: (params) => <BooleanBadgeCell {...params} />,
  is_selectable: (params) => <BooleanBadgeCell {...params} />,
  attributes_count: (params) => <AttributesCountCell {...params} />,
  products_count: (params) => <ProductsCountCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
