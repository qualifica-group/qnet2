/**
 * "Contesto segnatempo" fieldset (spec 0122 D-3/D-5/MT-F2): Cliente full
 * width, then Opportunita'/Commessa side by side, then the standalone form's
 * own Attivita' (Task) picker (D-3a — q-net keeps it commented out, the
 * document requires it here). With a Task linked, Cliente/Opportunita'/
 * Commessa lock to the task's own values (AC-031): the server imposes them
 * regardless of what this form sends (D-5), so the picker shows them
 * read-only rather than the form's own (irrelevant) state.
 */
/* eslint-disable react-refresh/only-export-components -- `workOrderRefOf` is a
   pure projection reused verbatim by `time-entry-form-body.tsx`, not a component. */

import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { TASKS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'

/**
 * Resource segment of the work orders for-select endpoint. Declared locally,
 * same as `features/tasks/task-links-section.tsx`: no shared constant exists
 * for it yet (`features/work-orders/` owns no `for-select-api.ts`), and this
 * module's write surface cannot add one.
 */
const WORK_ORDERS_FOR_SELECT_RESOURCE = 'work-orders'

/** Separator between a commessa's code and title, matching `WorkOrderForSelectResource::LABEL_SEPARATOR`. */
const WORK_ORDER_LABEL_SEPARATOR = ' — '

/**
 * The `meta` block `GET /api/work-orders/for-select` carries on each item
 * (spec 0122 D-5/AC-031, backend delta 2026-09-14): the Cliente resolved via
 * `quote.opportunity.registry`, `null` when that chain is incomplete. Declared
 * here rather than in a shared `for-select-api.ts` for the same reason as the
 * resource constant above: `features/work-orders/` owns none yet.
 */
export interface WorkOrderForSelectMeta {
  registry: { id: number; name: string } | null
}

export interface WorkOrderForSelectItem extends ForSelectItem {
  meta: WorkOrderForSelectMeta
}

/** Reads the resolved Cliente off a work order for-select item, tolerant of a missing/unknown `meta`. */
export function workOrderRegistryOf(item: ForSelectItem | null): { id: number; name: string } | null {
  return (item as WorkOrderForSelectItem | null)?.meta?.registry ?? null
}

/** Projects a `{id, code, title}` work order ref onto the `{id, name}` shape the picker hydrates from. */
export function workOrderRefOf(
  workOrder: { id: number; code: string; title: string } | null | undefined,
): RelationFieldRef | null {
  if (!workOrder) {
    return null
  }
  const title = workOrder.title.trim()
  const name = title === '' ? workOrder.code : `${workOrder.code}${WORK_ORDER_LABEL_SEPARATOR}${title}`
  return { id: workOrder.id, name }
}

function refOf(value: { id: number; name: string } | null | undefined): RelationFieldRef | null {
  return value ? { id: value.id, name: value.name } : null
}

interface TimeEntryContextFieldsProps {
  control: Control<TimeEntryFormValues>
  disabled?: boolean
  registry: RelationFieldRef | null
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
  task: RelationFieldRef | null
  isTaskLinked: boolean
  taskDetail: TaskDetailWithPermissions | undefined
  onRegistryChange: () => void
  onOpportunityItemChange: (item: ForSelectItem | null) => void
  onWorkOrderItemChange: (item: ForSelectItem | null) => void
}

export function TimeEntryContextFields({
  control,
  disabled = false,
  registry,
  opportunity,
  workOrder,
  task,
  isTaskLinked,
  taskDetail,
  onRegistryChange,
  onOpportunityItemChange,
  onWorkOrderItemChange,
}: TimeEntryContextFieldsProps) {
  const { t } = useTranslation()
  const selectLabels = {
    placeholder: t('timeEntries.form.selectPlaceholder'),
    emptyLabel: t('timeEntries.filters.noResults'),
    errorLabel: t('timeEntries.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
  const registryId = useWatch({ control, name: 'registry_id' })

  const registrySelected = isTaskLinked ? refOf(taskDetail?.registry) : registry
  const opportunitySelected = isTaskLinked ? refOf(taskDetail?.opportunity) : opportunity
  const workOrderSelected = isTaskLinked ? workOrderRefOf(taskDetail?.work_order) : workOrder
  const linksDisabled = disabled || isTaskLinked

  return (
    <fieldset className="grid min-w-0 gap-4 rounded-md border bg-muted/25 p-3">
      <legend className="px-1 text-sm font-semibold text-foreground">
        {t('timeEntries.form.contextTitle')}
      </legend>

      <RelationSelectField
        control={control}
        name="registry_id"
        metaKey="registry_id"
        label={t('timeEntries.form.registry')}
        resource={REGISTRIES_FOR_SELECT_RESOURCE}
        searchPlaceholder={selectLabels.placeholder}
        selected={registrySelected}
        forceDisabled={linksDisabled}
        onValueChange={onRegistryChange}
        {...selectLabels}
      />

      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="opportunity_id"
          metaKey="opportunity_id"
          label={t('timeEntries.form.opportunity')}
          resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={selectLabels.placeholder}
          selected={opportunitySelected}
          forceDisabled={linksDisabled}
          params={registryId !== null ? { registry_id: registryId } : undefined}
          onItemChange={onOpportunityItemChange}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="work_order_id"
          metaKey="work_order_id"
          label={t('timeEntries.form.workOrder')}
          resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={selectLabels.placeholder}
          selected={workOrderSelected}
          forceDisabled={linksDisabled}
          params={registryId !== null ? { registry_id: registryId } : undefined}
          onItemChange={onWorkOrderItemChange}
          {...selectLabels}
        />
      </div>

      <RelationSelectField
        control={control}
        name="task_id"
        metaKey="task_id"
        label={t('timeEntries.form.activity')}
        resource={TASKS_FOR_SELECT_RESOURCE}
        searchPlaceholder={selectLabels.placeholder}
        selected={task}
        forceDisabled={disabled}
        {...selectLabels}
      />
    </fieldset>
  )
}
