/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Briefcase, Building2, MapPin, Radio, UserRound } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Progress } from '@/components/ui/progress'
import { BADGE_COLOR_CLASSES, DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import {
  CurrencyCell,
  DateCell,
  RefNamesCell,
  RelationCell,
} from '@/features/table/rich-cells'
import { OpportunityStatusCell } from '@/features/opportunities/opportunity-status-cell'
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Backend color token -> badge classes, re-exported from the shared single
 * source (`features/table/cell-renderers`) under the name
 * `opportunity-detail.tsx` imports, so the opportunity detail sheet renders
 * the same colored status pill as the grid, without a second copy of the map
 * (mirrors `leadColumnRenderers`).
 */
export const OPPORTUNITY_STATUS_BADGE_CLASSES = BADGE_COLOR_CLASSES

/**
 * Renders an AGGREGATED to-many column (`product_category`/`business_function`,
 * amendment rev.3): the backend maps these to a comma-joined string of the
 * opportunity's product-line names (or null), not a single `{id, name}` ref.
 * Truncated with a native tooltip when it overflows.
 */
function NamesCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={value}>
        {value}
      </span>
    </div>
  )
}

/** Fixed bar width so the probability column stays compact and digit-aligned. */
const PROBABILITY_BAR_WIDTH = 'w-16'

/**
 * Semantic tone for the probability bar by band: low (<34%) reads as at-risk,
 * mid (34-66%) as in-progress, high (>=67%) as likely. Color is a reinforcement
 * — the percentage text is always shown, so it is never the only signal.
 */
export function probabilityToneClass(percent: number): string {
  if (percent >= 67) {
    return 'bg-green-600 dark:bg-green-500'
  }
  if (percent >= 34) {
    return 'bg-amber-500 dark:bg-amber-400'
  }
  return 'bg-red-500 dark:bg-red-400'
}

/**
 * Renders the `success_probability` 0..100 integer as a compact progress bar
 * plus its percentage. The bar is decorative (`aria-hidden`); the visible
 * percentage carries the value for assistive tech. Em dash when null.
 */
function ProbabilityCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell align="left" />
  }
  const percent = Math.min(Math.max(Math.round(value), 0), 100)
  return (
    <div className="flex h-full items-center gap-2 overflow-hidden">
      <Progress
        value={percent}
        size="xs"
        aria-hidden
        className={cn(PROBABILITY_BAR_WIDTH, 'shrink-0')}
        indicatorClassName={probabilityToneClass(percent)}
      />
      <span className="shrink-0 tabular-nums text-xs text-muted-foreground">{percent}%</span>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0040, 0082),
 * built from the shared cross-module cell library so relations, the status
 * pill, people and money match the leads/campaigns/projects grids. `status`
 * (spec 0082) is the COMPUTED summary, not a relation: it has its own cell. Relations
 * carry a leading kind icon; `supervisor` is one person (avatar + name),
 * `managers` an avatar stack; `success_probability` a progress bar. `name`
 * falls back to the AG Grid default cell; `created_at` reuses the shared
 * datetime renderer.
 */
export const opportunityColumnRenderers: TableRendererMap = {
  registry: (params) => <RelationCell {...params} icon={Building2} />,
  status: (params) => <OpportunityStatusCell {...params} />,
  referent: (params) => <RelationCell {...params} icon={UserRound} />,
  commercial: (params) => <RelationCell {...params} icon={Briefcase} />,
  supervisor: (params) => <UserCell {...params} />,
  managers: (params) => <UserStackCell {...params} />,
  source: (params) => <RelationCell {...params} icon={Radio} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  product_category: (params) => <NamesCell {...params} />,
  business_function: (params) => <NamesCell {...params} />,
  products_of_interest: (params) => <RefNamesCell {...params} />,
  estimated_value: (params) => <CurrencyCell {...params} />,
  success_probability: (params) => <ProbabilityCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  expected_close_date: (params) => <DateCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
