import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Slider } from '@/components/ui/slider'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { cn } from '@/lib/utils'
import {
  GENERAL_NOTES_MAX_LENGTH,
  SUCCESS_PROBABILITY_MAX,
  SUCCESS_PROBABILITY_MIN,
} from '@/features/opportunities/opportunity-schema'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/*
 * The opportunity's own scalar fields, one component each (spec 0198): the
 * SAME control is the editor of a detail row and of a create-draft row. Every
 * one sits in `MetaField`: hidden means absent, non-editable means disabled,
 * `required` comes from the field permission.
 */

interface FieldProps {
  control: Control<OpportunityFormValues>
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * "Titolo" (spec 0171): blank always means "automatic" — the server derives
 * it from the quoted products and keeps it in sync until the user types their own.
 */
export function OpportunityNameField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="name"
      metaKey="name"
      label={t('opportunities.form.name')}
      hint={t('opportunities.form.nameHint')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            placeholder={t('opportunities.form.namePlaceholder')}
            disabled={disabled}
            readOnly={readOnly}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface DateFieldProps extends FieldProps {
  name: 'start_date' | 'expected_close_date'
  label: string
}

/** "Data inizio" / "Data chiusura prevista": independent estimates (BR-5), an emptied date is `null`. */
export function OpportunityDateField({ control, name, label }: DateFieldProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
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
  )
}

/** "Valore stimato": mirrors the backend `decimal(15,2)`. */
export function OpportunityEstimatedValueField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
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
            onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Probabilita' di successo" (A-6): a 0..100 slider that always holds a value. */
export function OpportunitySuccessProbabilityField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="success_probability"
      metaKey="success_probability"
      label={t('opportunities.form.successProbability')}
    >
      {({ field, disabled }) => {
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
            <span className="w-10 shrink-0 text-right text-sm font-medium tabular-nums">{current}%</span>
          </div>
        )
      }}
    </MetaField>
  )
}

/**
 * "Note generali" (user directive 2026-07-27): free text prefilled from the
 * originating lead's notes, always editable/clearable, with its length
 * counter against the backend ceiling.
 */
export function OpportunityGeneralNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  const notesLength = useWatch({ control, name: 'general_notes' })?.length ?? 0

  return (
    <MetaField
      control={control}
      name="general_notes"
      metaKey="general_notes"
      label={t('opportunities.form.sections.generalNotes.title')}
    >
      {({ field, disabled, readOnly }) => (
        <div className="flex flex-col gap-1">
          <FormControl>
            <Textarea
              rows={4}
              placeholder={t('opportunities.form.generalNotesPlaceholder')}
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
          <span
            aria-hidden="true"
            className={cn(
              'self-end text-xs tabular-nums',
              notesLength > GENERAL_NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
            )}
          >
            {notesLength}/{GENERAL_NOTES_MAX_LENGTH}
          </span>
        </div>
      )}
    </MetaField>
  )
}
