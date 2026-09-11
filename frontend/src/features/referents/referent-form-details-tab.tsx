import { Info } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { PlannedField } from '@/components/record-form/field-group'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { useEnumOptions } from '@/features/config/use-config'
import { REFERENT_TYPES_FOR_SELECT_RESOURCE } from '@/features/referent-types/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

interface DetailsTabContentProps {
  control: Control<ReferentFormValues>
  selectedReferentTypeItem: ForSelectItem | null
  /** Pre-known {id, label} for the "Linked user" picker (spec 0090 D-2). */
  selectedUserItem: ForSelectItem | null
}

/**
 * "Dettagli referente": the referent-specific fields, laid out so the block
 * reads as three things instead of one column of five (user directive
 * 2026-09-11).
 *
 * The two CLASSIFICATIONS (tipo, ambito di contatto) share a row on the shared
 * two-column field grid; the linked user keeps a row of its own because it is
 * a different kind of statement — it declares that this referente IS a system
 * user (spec 0090 D-2), which is what lets commission rules resolve them — and
 * because its own field permission can hide it independently; the notes close
 * the block full width, as a textarea should.
 *
 * The "Settori attività" slot a future spec will fill is a `PlannedField`, not
 * a disabled `<Select>`: it is never persisted and never part of the
 * meta/schema, so it must not look — or tab — like a control.
 */
export function DetailsTabContent({
  control,
  selectedReferentTypeItem,
  selectedUserItem,
}: DetailsTabContentProps) {
  const { t } = useTranslation()
  const contactScopeOptions = useEnumOptions('referent_contact_scope')

  return (
    <FormSection
      icon={Info}
      title={t('referents.form.sections.details.title')}
      description={t('referents.form.sections.details.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="referent_type_id"
          metaKey="referent_type_id"
          label={t('referents.form.referentType')}
          resource={REFERENT_TYPES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('referents.form.referentTypeSearch')}
          selected={toRelationFieldRef(selectedReferentTypeItem)}
          placeholder={t('referents.form.referentTypePlaceholder')}
          emptyLabel={t('referents.form.referentTypeEmpty')}
          errorLabel={t('referents.form.referentTypeError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />

        <MetaField
          control={control}
          name="contact_scope"
          metaKey="contact_scope"
          label={t('referents.form.contactScope')}
        >
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {contactScopeOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      </div>

      <RelationSelectField
        control={control}
        name="user_id"
        metaKey="user_id"
        label={t('referents.form.linkedUser')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('referents.form.linkedUserSearch')}
        selected={toRelationFieldRef(selectedUserItem)}
        showAvatar
        placeholder={t('referents.form.linkedUserPlaceholder')}
        emptyLabel={t('referents.form.linkedUserEmpty')}
        errorLabel={t('referents.form.linkedUserError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <MetaField control={control} name="notes" metaKey="notes" label={t('referents.form.notes')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>

      <PlannedField
        label={t('referents.form.activitySectors')}
        note={t('referents.form.activitySectorsComingSoon')}
      />
    </FormSection>
  )
}
