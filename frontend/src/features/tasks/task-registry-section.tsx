import { useTranslation } from 'react-i18next'
import { Contact } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

interface TaskRegistrySectionProps {
  control: Control<TaskFormValues>
  /** Edit-mode hydration of the two persisted relations. */
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
  /** Resets `referent_id` — owned by `useTaskForm`, invoked in this handler, never in an effect (AC-081). */
  onRegistryChange: () => void
}

/**
 * "Anagrafica e referente". The referent picker is SCOPED to the chosen
 * anagrafica through the dependent endpoint that already exists
 * (`GET /api/referents/for-select?registry_id={id}`) and stays DISABLED until
 * one is picked (AC-080) — the same wiring Opportunita' uses, reused rather
 * than reinvented.
 *
 * AC-081: changing the anagrafica clears the referent in the SAME handler
 * (`onRegistryChange`, fired by `RelationSelectField.onValueChange` on an
 * actual user pick) instead of in a `useEffect` that would race the user's
 * next edit and could re-clear a value they just set.
 */
export function TaskRegistrySection({
  control,
  registry,
  referent,
  onRegistryChange,
}: TaskRegistrySectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const registryId = useWatch({ control, name: 'registry_id' })

  return (
    <FormSection
      icon={Contact}
      title={t('tasks.form.sections.registry.title')}
      description={t('tasks.form.sections.registry.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="registry_id"
          metaKey="registry_id"
          label={t('tasks.form.registry')}
          resource={REGISTRIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.registrySearch')}
          selected={registry}
          onValueChange={onRegistryChange}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="referent_id"
          metaKey="referent_id"
          label={t('tasks.form.referent')}
          hint={t('tasks.form.hints.referentScoped')}
          resource={REFERENTS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.referentSearch')}
          selected={referent}
          params={registryId !== null ? { registry_id: registryId } : undefined}
          forceDisabled={registryId === null}
          {...selectLabels}
        />
      </div>
    </FormSection>
  )
}
