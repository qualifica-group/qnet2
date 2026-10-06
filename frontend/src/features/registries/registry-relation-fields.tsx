import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SECTORS_FOR_SELECT_RESOURCE } from '@/features/sectors/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/*
 * The anagrafica's relation fields, one component each, shared by the
 * detail's in-place rows and the create draft's rows (spec 0200).
 */

interface FieldProps {
  control: Control<RegistryFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

interface SelectedListFieldProps extends FieldProps {
  selected: RelationFieldRef[]
}

/** "Fonte". */
export function RegistrySourceField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="source_id"
      metaKey="source_id"
      label={t('registries.form.source')}
      resource={SOURCES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('registries.form.sourceSearch')}
      selected={selected}
      placeholder={t('registries.form.sourcePlaceholder')}
      emptyLabel={t('registries.form.sourceEmpty')}
      errorLabel={t('registries.form.sourceError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Settori". */
export function RegistrySectorsField({ control, selected }: SelectedListFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="sector_ids"
      metaKey="sector_ids"
      label={t('registries.form.sectors')}
      resource={SECTORS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('registries.form.sectorsSearch')}
      selected={selected}
      placeholder={t('registries.form.sectorsPlaceholder')}
      emptyLabel={t('registries.form.sectorsEmpty')}
      errorLabel={t('registries.form.sectorsError')}
      removeLabel={t('registries.form.sectorsRemove')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Referenti": the anagrafica's contact people. */
export function RegistryReferentsField({ control, selected }: SelectedListFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="referent_ids"
      metaKey="referent_ids"
      label={t('registries.form.referents')}
      resource={REFERENTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('registries.form.referentsSearch')}
      selected={selected}
      placeholder={t('registries.form.referentsPlaceholder')}
      emptyLabel={t('registries.form.referentsEmpty')}
      errorLabel={t('registries.form.referentsError')}
      removeLabel={t('registries.form.referentsRemove')}
      retryLabel={t('common.retry')}
    />
  )
}

interface ReferentRoleFieldProps extends SelectedFieldProps {
  name: 'commercial_id' | 'reporter_id'
  label: string
  placeholder: string
}

/** "Commerciale" / "Segnalatore": both a single referente from the whole list. */
export function RegistryReferentRoleField({ control, selected, name, label, placeholder }: ReferentRoleFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name={name}
      metaKey={name}
      label={label}
      resource={REFERENTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('registries.form.referentsSearch')}
      selected={selected}
      placeholder={placeholder}
      emptyLabel={t('registries.form.referentsEmpty')}
      errorLabel={t('registries.form.referentsError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Supervisore": a platform user. */
export function RegistrySupervisorField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="supervisor_id"
      metaKey="supervisor_id"
      label={t('registries.form.supervisor')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('registries.form.managersSearch')}
      selected={selected}
      showAvatar
      placeholder={t('registries.form.supervisorPlaceholder')}
      emptyLabel={t('registries.form.managersEmpty')}
      errorLabel={t('registries.form.managersError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/**
 * "Gestori account": the ordered, gap-aware "G.A. n" slots in ONE editor —
 * moving a person between slots is one change. No per-position labels: the
 * relabeling of spec 0080 is driven by a Product Category this module has none of.
 */
export function RegistryManagersField({ control, selected }: SelectedListFieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="manager_slots" metaKey="manager_slots" label={t('registries.form.managers')}>
      {({ field, disabled }) => (
        <ManagerSlotsField
          value={field.value}
          onChange={field.onChange}
          selectedItems={selected.map((ref) => ({ id: ref.id, label: ref.name }))}
          disabled={disabled}
        />
      )}
    </MetaField>
  )
}
