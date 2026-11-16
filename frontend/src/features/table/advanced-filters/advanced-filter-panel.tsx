import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { ADVANCED_FILTER_FIELD_REGISTRY } from '@/features/table/advanced-filters/field-registry'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type {
  AdvancedFilterDescriptor,
  AdvancedFilterWidth,
} from '@/features/table/advanced-filters/types'

/**
 * Height transition applied to the `CollapsibleContent` that wraps the panel at
 * every call site, so opening and closing animate (spec 0032). Mirrors the
 * `ModuleStatsPanel` pattern; `motion-reduce` disables it for reduced-motion.
 */
export const ADVANCED_FILTER_PANEL_ANIMATION =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

/** Compact `max-w` token per declared width (ui-design.md §2: small elements by default). */
const WIDTH_CLASS: Record<AdvancedFilterWidth, string> = {
  sm: 'w-full sm:max-w-40',
  md: 'w-full sm:max-w-64',
  lg: 'w-full sm:max-w-80',
  full: 'w-full',
}

interface AdvancedFilterPanelProps {
  /** The domain's advanced filter catalog, already sorted by `order` (backend contract). */
  descriptors: AdvancedFilterDescriptor[]
  /** Draft/applied state and actions owned by `useAdvancedFilters`. */
  filters: UseAdvancedFiltersResult
  /** Frame adjustments of the host (e.g. which edge carries the hairline). */
  className?: string
}

/**
 * The generic, backend-driven advanced-filters panel (spec 0032): renders
 * every visible descriptor via the type -> field registry, ordered by
 * `order`, with an accessible required-error triad and a Reset/Applica
 * footer. Domain-agnostic: the same component serves all 21 domains, driven
 * entirely by the descriptor catalog `TableView` passes in.
 */
export function AdvancedFilterPanel({ descriptors, filters, className }: AdvancedFilterPanelProps) {
  const { t } = useTranslation()
  const panelId = useId()

  const visible = useMemo(
    () => descriptors.filter((descriptor) => descriptor.visible),
    [descriptors],
  )

  return (
    // Opaque card surface, not a translucent tint: the panel reads as a sheet
    // laid over the page, and the fields' own `--field` fill (94%) only reads as
    // "editable" against it — on the previous `bg-muted/30` the two tones nearly
    // matched and the inputs lost their edge.
    <div className={cn('border-b border-border bg-card', className)}>
      <div className="flex flex-wrap gap-3 p-3">
        {visible.map((descriptor) => {
          const fieldId = `${panelId}-${descriptor.name}`
          const errorId = `${fieldId}-error`
          const invalid = filters.isFieldInvalid(descriptor)
          const FieldComponent = ADVANCED_FILTER_FIELD_REGISTRY[descriptor.type]

          return (
            <div
              key={descriptor.name}
              className={cn('flex flex-col gap-1', WIDTH_CLASS[descriptor.width])}
            >
              <Label
                htmlFor={fieldId}
                className="flex items-center gap-1 text-xs font-medium text-muted-foreground"
              >
                <span className="truncate">{t(descriptor.label)}</span>
                {descriptor.required ? (
                  <span aria-hidden="true" className="text-destructive">
                    *
                  </span>
                ) : null}
              </Label>
              <FieldComponent
                descriptor={descriptor}
                value={filters.draft[descriptor.name] ?? null}
                onChange={(value) => filters.setFieldValue(descriptor.name, value)}
                disabled={filters.isFieldDisabled(descriptor) || filters.isSaving}
                id={fieldId}
                describedBy={invalid ? errorId : undefined}
                invalid={invalid}
                dependencyParams={filters.dependencyParamsFor(descriptor)}
              />
              {invalid ? (
                <span id={errorId} role="alert" className="text-xs text-destructive">
                  {t('table.advancedFilters.requiredError')}
                </span>
              ) : null}
            </div>
          )
        })}
      </div>

      {/* Action bar on `--surface`, the scale's intermediate rung: apart from
          both the white panel and the page body around the filter block.
          Not `--muted`: that tint sits beyond the end of the scale, next to the
          body (79 vs 81 in light), so the bar read as the page showing through.
          Solid, never an alpha: a tint would recomposite against the panel. */}
      <div className="flex items-center justify-end gap-2 border-t border-border bg-surface px-3 py-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={filters.reset}
          disabled={filters.isSaving}
        >
          <RotateCcw aria-hidden="true" />
          {t('table.advancedFilters.reset')}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={filters.apply}
          disabled={!filters.canApply || filters.isSaving}
        >
          <Check aria-hidden="true" />
          {t('table.advancedFilters.apply')}
        </Button>
      </div>
    </div>
  )
}
