/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, X } from 'lucide-react'
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
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type {
  BusinessFunctionOperationalSite,
  BusinessFunctionParent,
} from '@/features/business-functions/types'

/** Hoisted empty-array default for the operational-sites cell. */
const EMPTY_SITES: BusinessFunctionOperationalSite[] = []

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full items-center justify-center">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders the `parent` column: the parent function's name, em dash for a top-level function. */
function ParentCell({ value }: ICellRendererParams) {
  const parent = value as BusinessFunctionParent | null

  if (!parent) {
    return <EmptyCell />
  }

  return (
    <div className="flex h-full items-center">
      <span className="truncate">{parent.name}</span>
    </div>
  )
}

/** Shared cell wrapper for the operational-sites count badge (mirrors `productCategoryColumnRenderers`). */
const SITES_CELL_WRAPPER = 'flex h-full w-full items-center justify-center px-2 py-1 overflow-hidden'

/** Consistent pill height so the count badge aligns with every other badge column. */
const SITES_BADGE_BASE = 'h-5 min-h-5'

/**
 * Renders the `operational_sites` and `companies` columns (`{id, label}` lists): a compact badge with the total,
 * revealing every site's label (`"line1 - city"`) in a hover/focus tooltip —
 * mirrors the count-plus-tooltip pattern shared by `TagsCountCell` and
 * `productCategoryColumnRenderers`'s `CountWithNamesCell`.
 */
function LabelCountCell({ value }: ICellRendererParams) {
  const sites = Array.isArray(value) ? (value as BusinessFunctionOperationalSite[]) : EMPTY_SITES

  if (sites.length === 0) {
    return (
      <div className={SITES_CELL_WRAPPER}>
        <span className="text-muted-foreground">—</span>
      </div>
    )
  }

  const allLabels = sites.map((site) => site.label).join(', ')

  return (
    <div className={SITES_CELL_WRAPPER}>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant="secondary"
              className={cn(SITES_BADGE_BASE, 'cursor-default tabular-nums')}
              tabIndex={0}
              aria-label={allLabels}
            >
              {sites.length}
            </Badge>
          </TooltipTrigger>
          <TooltipContent side="top" variant="light" className="max-h-64 max-w-64 overflow-y-auto p-0">
            <ul className="flex flex-col divide-y">
              {sites.map((site) => (
                <li key={site.id} className="px-3 py-1.5 text-sm">
                  {site.label}
                </li>
              ))}
            </ul>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  )
}

/**
 * Colored tone classes for the boolean badge (green = yes, red = no), reusing
 * the same palette as the generic table BadgeCell so tones stay consistent
 * across the app and adapt to dark mode.
 */
const BOOLEAN_BADGE_YES = 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO = 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

/**
 * Renders a boolean column (`is_business_unit`/`is_business_service`/`is_active`) as a
 * colored Sì/No badge with a leading icon (check for yes, cross for no). Color
 * is not the only signal — icon plus text keep it accessible.
 */
function BooleanCell({ value }: ICellRendererParams) {
  const isTrue = value === true

  return (
    <div className="flex h-full items-center justify-center">
      <Badge className={isTrue ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO}>
        {isTrue ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {isTrue ? i18n.t('common.yes') : i18n.t('common.no')}
      </Badge>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that
 * need special rendering appear here; `name` falls back to the AG Grid
 * default text cell. `created_at` reuses the shared domain-agnostic renderer
 * so the datetime formatting is not re-implemented per domain.
 */
export const businessFunctionColumnRenderers: TableRendererMap = {
  is_business_unit: (params) => <BooleanCell {...params} />,
  is_business_service: (params) => <BooleanCell {...params} />,
  is_active: (params) => <BooleanCell {...params} />,
  manager: (params) => <UserCell {...params} />,
  users: (params) => <UserStackCell {...params} />,
  parent: (params) => <ParentCell {...params} />,
  operational_sites: (params) => <LabelCountCell {...params} />,
  // Spec 0271: `companies` is an `{id, label}` list.
  companies: (params) => <LabelCountCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
