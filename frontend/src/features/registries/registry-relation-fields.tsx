import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { nextPrimaryReferentId } from '@/features/registries/primary-referent'
import { managerSlotLabels } from '@/features/shared/manager-position-label'
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

interface ReferentsFieldProps extends SelectedListFieldProps {
  setValue: UseFormSetValue<RegistryFormValues>
  /** The current main referent, read at change time so the editor never goes stale. */
  getPrimaryReferentId: () => number | null
}

/** "Referenti": the anagrafica's contact people. Keeps the main referent in step (spec 0330 D-9). */
export function RegistryReferentsField({ control, selected, setValue, getPrimaryReferentId }: ReferentsFieldProps) {
  const { t } = useTranslation()
  const syncPrimaryReferent = (referentIds: number[]) =>
    setValue('primary_referent_id', nextPrimaryReferentId(getPrimaryReferentId(), referentIds), { shouldDirty: true })

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
      onValueChange={syncPrimaryReferent}
    />
  )
}

const NO_REFERENT_IDS: number[] = []

/** "Referente principale": one of the referents currently in the Referenti field (spec 0330 D-9). */
export function RegistryPrimaryReferentField({ control, selected }: SelectedListFieldProps) {
  const { t } = useTranslation()
  const referentIds = useWatch({ control, name: 'referent_ids' }) ?? NO_REFERENT_IDS
  const labels = useForSelectLabels({ resource: REFERENTS_FOR_SELECT_RESOURCE, ids: referentIds })
  const options = useMemo(
    () =>
      referentIds.map((id) => ({
        id,
        label: labels.get(id)?.label ?? selected.find((ref) => ref.id === id)?.name ?? `#${id}`,
      })),
    [referentIds, labels, selected],
  )

  return (
    <MetaField
      control={control}
      name="primary_referent_id"
      metaKey="primary_referent_id"
      label={t('registries.form.primaryReferent')}
    >
      {({ field, disabled }) => (
        <Select
          value={field.value === null ? '' : String(field.value)}
          onValueChange={(next) => field.onChange(Number(next))}
          disabled={disabled || options.length === 0}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={t('registries.form.primaryReferentPlaceholder')} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.id} value={String(option.id)}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
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
 * moving a person between slots is one change. Only the default denominations:
 * the relabeling of spec 0080 is driven by a Product Category this module has
 * none of.
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
          labels={managerSlotLabels(t, undefined)}
        />
      )}
    </MetaField>
  )
}
