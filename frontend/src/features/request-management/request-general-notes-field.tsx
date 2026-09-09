import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'
import type { Control, FieldValues, Path } from 'react-hook-form'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TITLE_CLASS,
} from '@/components/record-form/layout'
import { MetaField } from '@/features/authorization/MetaField'

interface RequestGeneralNotesFieldProps<TFieldValues extends FieldValues> {
  control: Control<TFieldValues>
  /** The `general_notes` path on the host form. */
  name: Path<TFieldValues>
}

/**
 * "Note generali" in the work panel, EDITABLE (direttiva utente 2026-09-09:
 * "se non c'e' una nota generale voglio che ci sia il componente e che possa
 * essere inserita o modificata, come anche in creazione"). Replaces the
 * read-only `RequestGeneralNotesCallout`, which rendered nothing at all when
 * the request carried no note — exactly the case the operator needs to type
 * in.
 *
 * The field is the Opportunity's own `general_notes`, written through THIS
 * module's PATCH since the same directive, so it is gated by the field
 * permission `general_notes` like every other editable field of the panel
 * (a readonly role keeps seeing the note, and the "not editable" hint).
 *
 * Same chrome as the create form's own version and as the opportunity form's:
 * the amber callout, its left rule and its micro-title come from the shared
 * `layout.ts` constants, and the textarea is deliberately unstyled
 * (`border-0 bg-transparent`, no focus ring of its own) because the callout
 * IS the field's surface — a second bordered box inside it would stack two
 * containers on the same plane (ui-design.md §1-bis).
 */
export function RequestGeneralNotesField<TFieldValues extends FieldValues>({
  control,
  name,
}: RequestGeneralNotesFieldProps<TFieldValues>) {
  const { t } = useTranslation()

  return (
    <section className={GENERAL_NOTES_CALLOUT_CLASS}>
      <MetaField
        control={control}
        name={name}
        metaKey="general_notes"
        label={
          <span className={GENERAL_NOTES_TITLE_CLASS}>
            <StickyNote className="size-3.5 shrink-0" aria-hidden="true" />
            {t('requestManagement.workPanel.generalNotes.title')}
          </span>
        }
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea
              className="min-h-0 resize-y border-0 bg-transparent p-0 text-sm leading-relaxed shadow-none focus-visible:ring-0 dark:bg-transparent"
              rows={4}
              placeholder={t('requestManagement.workPanel.generalNotes.placeholder')}
              disabled={disabled}
              readOnly={readOnly}
              value={(field.value as string | null) ?? ''}
              onChange={(event) => field.onChange(event.target.value)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </section>
  )
}
