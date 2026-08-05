import { useTranslation } from 'react-i18next'
import { CalendarRange } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import { Slider } from '@/components/ui/slider'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import {
  SUCCESS_PROBABILITY_MAX,
  SUCCESS_PROBABILITY_MIN,
} from '@/features/opportunities/opportunity-schema'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityPlanningSectionProps {
  control: Control<OpportunityFormValues>
  collapsible?: boolean
  open?: boolean
  onOpenChange?: (open: boolean) => void
  className?: string
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * The opportunity's planning/estimate fields (BR-5): `start_date`/
 * `expected_close_date` are independent estimates with no order constraint
 * between them; `estimated_value` mirrors `projects.total_budget`'s
 * decimal(15,2); `success_probability` is an integer 0..100.
 *
 * Its own `@container`: the card shares a row with the status card on a wide
 * form (user directive 2026-08-05), so the field pairs must split on the
 * CARD's width, not the column's — at half width they stack instead of
 * squeezing four controls into two cramped cells.
 */
export function OpportunityPlanningSection({
  control,
  collapsible,
  open,
  onOpenChange,
  className,
}: OpportunityPlanningSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={CalendarRange}
      title={t('opportunities.form.sections.planning.title')}
      description={t('opportunities.form.sections.planning.description')}
      collapsible={collapsible}
      open={open}
      onOpenChange={onOpenChange}
      className={cn('@container', className)}
    >
      <div className="grid grid-cols-1 gap-3 @sm:grid-cols-2">
        <MetaField control={control} name="start_date" metaKey="start_date" label={t('opportunities.form.startDate')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="date"
                disabled={disabled}
                readOnly={readOnly}
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value || null)}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="expected_close_date"
          metaKey="expected_close_date"
          label={t('opportunities.form.expectedCloseDate')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="date"
                disabled={disabled}
                readOnly={readOnly}
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value || null)}
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>
      </div>

      <div className="grid grid-cols-1 gap-3 @sm:grid-cols-2">
        <MetaField
          control={control}
          name="estimated_value"
          metaKey="estimated_value"
          label={t('opportunities.form.estimatedValue')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="0.01"
                min={0}
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="success_probability"
          metaKey="success_probability"
          label={t('opportunities.form.successProbability')}
        >
          {({ field, disabled }) => {
            // A-6: rendered as a 0..100 slider that always holds a value.
            const current = field.value ?? 0
            return (
              <div className="flex items-center gap-3">
                <FormControl>
                  <Slider
                    min={SUCCESS_PROBABILITY_MIN}
                    max={SUCCESS_PROBABILITY_MAX}
                    step={1}
                    value={[current]}
                    onValueChange={([next]) => field.onChange(next)}
                    disabled={disabled}
                    aria-label={t('opportunities.form.successProbability')}
                    className="flex-1"
                  />
                </FormControl>
                <span className="w-10 shrink-0 text-right text-sm font-medium tabular-nums">
                  {current}%
                </span>
              </div>
            )
          }}
        </MetaField>
      </div>
    </FormSection>
  )
}
