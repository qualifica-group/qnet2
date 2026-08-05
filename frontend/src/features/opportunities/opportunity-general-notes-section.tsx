import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { StickyNote } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TITLE_CLASS,
} from '@/components/record-form/layout'
import { MetaField } from '@/features/authorization/MetaField'
import { cn } from '@/lib/utils'
import { GENERAL_NOTES_MAX_LENGTH } from '@/features/opportunities/opportunity-schema'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityGeneralNotesSectionProps {
  control: Control<OpportunityFormValues>
  className?: string
}

/**
 * The opportunity's "Note generali" (user directive 2026-07-27): a single
 * free-text field, prefilled from the originating lead's own notes at
 * conversion and always editable afterwards (never BR-2-locked).
 *
 * Written INSIDE the very callout Gestione Richieste reads them from (user
 * directive 2026-08-05): same amber box, same left rule, same micro-title,
 * same first position in the side column — the operator working the request
 * reads there exactly what is typed here. The field's own label IS that
 * micro-title, so the block carries one heading and the textarea keeps an
 * accessible name.
 *
 * The textarea is deliberately unstyled (`border-0 bg-transparent`, no focus
 * ring of its own): the callout IS the field's surface, and a second bordered
 * box inside it would stack two containers on the same plane (ui-design.md
 * §1-bis).
 */
export function OpportunityGeneralNotesSection({
  control,
  className,
}: OpportunityGeneralNotesSectionProps) {
  const { t } = useTranslation()
  const titleId = useId()
  const notesValue = useWatch({ control, name: 'general_notes' })
  const notesLength = notesValue?.length ?? 0

  return (
    <section aria-labelledby={titleId} className={cn(GENERAL_NOTES_CALLOUT_CLASS, className)}>
      <MetaField
        control={control}
        name="general_notes"
        metaKey="general_notes"
        label={
          <span id={titleId} className={GENERAL_NOTES_TITLE_CLASS}>
            <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
            {t('opportunities.form.sections.generalNotes.title')}
          </span>
        }
      >
        {({ field, disabled, readOnly }) => (
          <>
            <FormControl>
              <Textarea
                className="min-h-0 resize-y border-0 bg-transparent p-0 text-sm leading-relaxed shadow-none focus-visible:ring-0 dark:bg-transparent"
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
                'justify-self-end text-xs tabular-nums',
                notesLength > GENERAL_NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
              )}
            >
              {notesLength}/{GENERAL_NOTES_MAX_LENGTH}
            </span>
          </>
        )}
      </MetaField>
    </section>
  )
}
