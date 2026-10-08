import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'
import type { Control, FieldValues, Path } from 'react-hook-form'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import {
  GENERAL_NOTES_CALLOUT_CLASS,
  GENERAL_NOTES_TEXTAREA_CLASS,
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
 * Same chrome as the create form's own version and as the record details'
 * `NotesCalloutRow`: the amber callout, its left rule, its micro-title and the
 * unstyled textarea all come from the shared `layout.ts` constants.
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
              className={GENERAL_NOTES_TEXTAREA_CLASS}
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
