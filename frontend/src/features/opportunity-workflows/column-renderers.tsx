/* eslint-disable react-refresh/only-export-components -- renderer map module: cells are AG Grid render functions, not route/page components (mirrors `features/table/cell-renderers.tsx`) */
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
  BADGE_BASE,
  CELL_WRAPPER,
  CountCell,
  DateTimeCell,
  EmptyCell,
  TagsCountCell,
} from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Prefix marking a `criteria_fields` entry as a native field's i18n key
 * (spec 0047 amendment 2026-07-27, AC-038). Only entries starting with it are
 * translated; a custom field's literal label never carries this prefix, so it
 * always passes through as-is — even on the (constructed) coincidence of a
 * custom label matching some unrelated real i18n key. Relying on i18next's
 * missing-key fallback instead would silently translate that coincidence.
 */
const NATIVE_CRITERION_FIELD_KEY_PREFIX = 'opportunityWorkflows.criterionFields.'

/**
 * Renders `criteria_fields` — a MIXED array of native i18n label keys (e.g.
 * "opportunityWorkflows.criterionFields.state_id") and literal custom-field
 * labels (D9: custom labels are free text, never a key) — as a compact count
 * badge with a tooltip listing every resolved field name. Mirrors
 * `TagsCountCell`'s shape; only entries carrying
 * `NATIVE_CRITERION_FIELD_KEY_PREFIX` go through `t()`, the rest render as-is.
 */
function CriteriaFieldsCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (!Array.isArray(value) || value.length === 0) {
    return <EmptyCell />
  }
  const labels = (value as string[]).map((entry) =>
    entry.startsWith(NATIVE_CRITERION_FIELD_KEY_PREFIX) ? t(entry) : entry,
  )

  return (
    <div className={CELL_WRAPPER}>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant="secondary"
              className={cn(BADGE_BASE, 'cursor-default tabular-nums')}
              tabIndex={0}
              aria-label={labels.join(', ')}
            >
              {labels.length}
            </Badge>
          </TooltipTrigger>
          <TooltipContent side="top" variant="light" className="max-w-64 p-0">
            <ul className="flex flex-col divide-y">
              {labels.map((label, index) => (
                <li key={index} className="px-3 py-1.5 text-sm">
                  {label}
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
 * Custom cell renderers keyed by the backend column `id` (spec 0047 Lane C).
 * `criteria_fields` needs frontend localization (see above); `criteria_values`
 * are already resolved display strings, reusing the shared count+tooltip
 * cell; `statuses_count`/`is_active`/`updated_at` reuse the cross-module cell
 * library so this grid reads like every other configurator.
 */
export const opportunityWorkflowColumnRenderers: TableRendererMap = {
  criteria_fields: (params) => <CriteriaFieldsCell {...params} />,
  criteria_values: (params) => <TagsCountCell {...params} />,
  statuses_count: (params) => <CountCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
