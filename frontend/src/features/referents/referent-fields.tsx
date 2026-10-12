import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import { REFERENT_TYPES_FOR_SELECT_RESOURCE } from '@/features/referent-types/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/*
 * The referente's own fields, one component each, shared by the detail's
 * in-place rows and the create draft's rows (spec 0216).
 */

interface FieldProps {
  control: Control<ReferentFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

/** "Tipo referente". */
export function ReferentTypeField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="referent_type_id"
      metaKey="referent_type_id"
      label={t('referents.form.referentType')}
      resource={REFERENT_TYPES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('referents.form.referentTypeSearch')}
      selected={selected}
      placeholder={t('referents.form.referentTypePlaceholder')}
      emptyLabel={t('referents.form.referentTypeEmpty')}
      errorLabel={t('referents.form.referentTypeError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Ambito di contatto": internal or external, never empty. */
export function ReferentContactScopeField({ control }: FieldProps) {
  const { t } = useTranslation()
  const options = useEnumOptions('referent_contact_scope')
  return (
    <MetaField control={control} name="contact_scope" metaKey="contact_scope" label={t('referents.form.contactScope')}>
      {({ field, disabled }) => (
        <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

/**
 * "Utente collegato": declares that this referente IS a system user (spec
 * 0090 D-2), which is what lets commission rules resolve them.
 */
export function ReferentUserField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="user_id"
      metaKey="user_id"
      label={t('referents.form.linkedUser')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('referents.form.linkedUserSearch')}
      selected={selected}
      showAvatar
      placeholder={t('referents.form.linkedUserPlaceholder')}
      emptyLabel={t('referents.form.linkedUserEmpty')}
      errorLabel={t('referents.form.linkedUserError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Note". */
export function ReferentNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="notes" metaKey="notes" label={t('referents.form.notes')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}
