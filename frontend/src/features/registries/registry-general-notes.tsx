import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { GENERAL_NOTES_TEXTAREA_CLASS } from '@/components/record-form/layout'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { MetaField } from '@/features/authorization/MetaField'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

interface RegistryGeneralNotesRowProps {
  notes: string | null
  control: Control<RegistryFormValues>
  inline: InlineEdit
}

function RegistryGeneralNotesField({ control }: { control: Control<RegistryFormValues> }) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="general_notes" metaKey="general_notes" label={t('registries.form.generalNotes')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea
            className={GENERAL_NOTES_TEXTAREA_CLASS}
            rows={4}
            placeholder={t('registries.form.generalNotesPlaceholder')}
            disabled={disabled}
            readOnly={readOnly}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * "Note generali" of the anagrafica (spec 0207) at the top of the record's
 * side column, in the request work panel's amber callout (`NotesCalloutRow`):
 * the first thing the operator reads, edited in place inside the callout on
 * the detail and kept in the draft on create.
 */
export function RegistryGeneralNotesRow({ notes, control, inline }: RegistryGeneralNotesRowProps) {
  const { t } = useTranslation()

  return (
    <NotesCalloutRow
      field="general_notes"
      title={t('registries.form.generalNotes')}
      notes={notes}
      placeholder={t('registries.form.generalNotesPlaceholder')}
      inline={inline}
      editor={<RegistryGeneralNotesField control={control} />}
    />
  )
}
