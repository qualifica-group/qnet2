import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

interface RegistryFormTeamSectionProps {
  control: Control<RegistryFormValues>
  selectedSupervisorItem: ForSelectItem | null
  selectedManagerItems: ForSelectItem[]
}

/**
 * The anagrafica's team relations: Supervisore and the ordered "G.A. n" slots
 * — the same block, in the same order, with the same two controls the
 * Opportunità form carries (user directive 2026-09-11: "come succede per
 * opportunità e offerte, così graficamente è coerente"). Its read-only twin is
 * `RegistryTeamSection` on the record card.
 *
 * These two fields used to sit inside "Relazioni", between the settori and the
 * commerciale, which mixed two different things: WHO works this anagrafica on
 * our side (users, and the only block the other record forms call a team) with
 * WHAT it is related to (fonte, settori, referenti). Commerciale and
 * Segnalatore stay behind in "Relazioni" on purpose — they are REFERENTI, not
 * users (`REFERENTS_FOR_SELECT_RESOURCE`), so they are not part of this team.
 *
 * No `labels` prop: the per-position G.A. relabeling (spec 0080) is driven by
 * a Product Category, which this module has none of — `ManagerSlotsField`
 * falls back to its shared default, the same string the record card shows via
 * `managerPositionLabel(t, n, undefined)`.
 */
export function RegistryFormTeamSection({
  control,
  selectedSupervisorItem,
  selectedManagerItems,
}: RegistryFormTeamSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Users}
      title={t('registries.form.sections.team.title')}
      description={t('registries.form.sections.team.description')}
    >
      <RelationSelectField
        control={control}
        name="supervisor_id"
        metaKey="supervisor_id"
        label={t('registries.form.supervisor')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('registries.form.managersSearch')}
        selected={toRelationFieldRef(selectedSupervisorItem)}
        showAvatar
        placeholder={t('registries.form.supervisorPlaceholder')}
        emptyLabel={t('registries.form.managersEmpty')}
        errorLabel={t('registries.form.managersError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="manager_slots"
        metaKey="manager_slots"
        label={t('registries.form.managers')}
      >
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedManagerItems}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
