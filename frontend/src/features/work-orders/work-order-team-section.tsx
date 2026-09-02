import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'

interface WorkOrderTeamSectionProps {
  control: Control<WorkOrderFormValues>
  /** Edit-mode hydration for the Responsabili picker (`{id, name}` projections). */
  supervisors: RelationFieldRef[]
  /** Edit-mode hydration for the filled Partecipanti slots. */
  participants: ForSelectItem[]
}

/**
 * "Responsabili e partecipanti" (spec 0096): the commessa's start date, its
 * accountable users (Responsabili — several, at least one) and its team
 * (Partecipanti — the ordered, gap-aware slots).
 *
 * Its own file rather than more rows inside `work-order-form-body.tsx`, which
 * was already at 232 lines (engineering.md §6, 300 soft limit).
 *
 * The slot editor is the SHARED `ManagerSlotsField` — the same component
 * Registries, Opportunita' and Offerte use, so the team here looks and behaves
 * exactly like theirs. Only the per-row denomination differs, through the
 * `labels` prop that component already exposes (spec 0080): "Partecipante n"
 * instead of "Gestore account n". No fork, no copy.
 */
export function WorkOrderTeamSection({
  control,
  supervisors,
  participants,
}: WorkOrderTeamSectionProps) {
  const { t } = useTranslation()

  // Built here rather than hoisted to module scope: every label is a
  // translated string, so the map has to be rebuilt when the language changes.
  const slotLabels = Object.fromEntries(
    Array.from({ length: MAX_MANAGER_SLOTS }, (_, index) => [
      index + 1,
      t('workOrders.form.participantSlotLabel', { n: index + 1 }),
    ]),
  )

  return (
    <FormSection
      icon={Users}
      title={t('workOrders.form.sections.team.title')}
      description={t('workOrders.form.sections.team.description')}
    >
      <MetaField
        control={control}
        name="start_date"
        metaKey="start_date"
        label={t('workOrders.form.startDate')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="date"
              disabled={disabled}
              readOnly={readOnly}
              value={field.value}
              onChange={(event) => field.onChange(event.target.value)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>

      <RelationMultiSelectField
        control={control}
        name="supervisor_ids"
        metaKey="supervisor_ids"
        label={t('workOrders.form.supervisors')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('workOrders.form.supervisorsSearch')}
        selected={supervisors}
        showAvatar
        placeholder={t('workOrders.form.supervisorsPlaceholder')}
        emptyLabel={t('workOrders.form.supervisorsEmpty')}
        errorLabel={t('workOrders.form.supervisorsError')}
        removeLabel={t('common.remove')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="participant_slots"
        metaKey="participant_slots"
        label={t('workOrders.form.participants')}
      >
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={participants}
            disabled={disabled}
            labels={slotLabels}
            // The shared editor defaults to "gestore account" wording, which
            // is wrong here: these slots are Partecipanti (spec 0096).
            strings={{
              search: t('workOrders.form.participantsSearch'),
              empty: t('workOrders.form.participantsEmpty'),
              error: t('workOrders.form.participantsError'),
              addSlot: t('workOrders.form.participantsAddSlot'),
              hint: t('workOrders.form.participantsHint'),
            }}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
