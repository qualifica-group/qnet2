import { useTranslation } from 'react-i18next'
import type { Control, UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { useRegistryRoleInheritance } from '@/features/opportunities/use-registry-role-inheritance'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityRegistryFieldProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  getValues: UseFormGetValues<OpportunityFormValues>
  /** The loaded opportunity's linked registry, if any (edit mode hydration). */
  selected: RelationFieldRef | null
  /** BR-2: forced read-only when derived from a linked Lead (spec 0040 MT-6). */
  forceDisabled?: boolean
}

function toForSelectItem(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * The opportunity's required anagrafica (`registry_id`, D-4). Picking a
 * registry resets `referent_id` (BR-4: still anagrafica-scoped) and inherits
 * `manager_slots` from its account managers (`meta.managers`, spec 0040 A-5)
 * plus Commerciale, Segnalatore and Supervisore from the anagrafica's own
 * three roles (directive 2026-07-29) — all still freely editable afterwards,
 * and never overwritten silently once the user entered them
 * (`useRegistryRoleInheritance`). The picker itself stays the whole platform
 * list (A-3), only the initial value is derived.
 *
 * A single one-shot fetch, run as a direct consequence of the user's
 * selection, never a render-time effect, so it never re-overwrites a later
 * edit.
 */
export function OpportunityRegistryField({
  control,
  setValue,
  getValues,
  selected,
  forceDisabled = false,
}: OpportunityRegistryFieldProps) {
  const { t } = useTranslation()
  const applyRegistrySelection = useRegistryRoleInheritance(setValue, getValues)
  const { quickCreated, renderAction } = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE)

  const selectRegistry = (field: { onChange: (value: number | null) => void }, registryId: number | null) => {
    field.onChange(registryId)
    void applyRegistrySelection(registryId)
  }

  return (
    <MetaField control={control} name="registry_id" metaKey="registry_id" label={t('opportunities.form.registry')}>
      {({ field, disabled }) => {
        const isDisabled = disabled || forceDisabled
        const quickCreatedMatch = quickCreated.find((ref) => ref.id === field.value) ?? null
        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(registryId) => selectRegistry(field, registryId)}
              selectedItem={toForSelectItem(quickCreatedMatch) ?? toForSelectItem(selected)}
              disabled={isDisabled}
              labels={{
                placeholder: t('opportunities.form.selectPlaceholder'),
                searchPlaceholder: t('opportunities.form.registrySearch'),
                empty: t('opportunities.form.selectEmpty'),
                error: t('opportunities.form.selectError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('opportunities.form.registry'),
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => selectRegistry(field, ref.id), isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}
