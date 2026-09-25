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
import { FormControl } from '@/components/ui/form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { TASKS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { useTimeEntryWorkOrderStageOptions } from '@/features/time-entries/form/use-time-entry-work-order-stage-options'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import type { TimeEntryWorkOrderStageRef } from '@/features/time-entries/types'

/**
 * Resource segment of the work orders for-select endpoint. Declared locally,
 * same as `features/tasks/task-links-section.tsx`: no shared constant exists
 * for it yet (`features/work-orders/` owns no `for-select-api.ts`), and this
 * module's write surface cannot add one.
 */
const WORK_ORDERS_FOR_SELECT_RESOURCE = 'work-orders'

/** Separator between a commessa's code and title, matching `WorkOrderForSelectResource::LABEL_SEPARATOR`. */
const WORK_ORDER_LABEL_SEPARATOR = ' — '

/** Sentinel Radix `<Select>` value for "Senza fase" (spec 0163 D-1): the control itself only ever emits `null`/a stage id. */
const NO_STAGE_VALUE = '__no_stage__'

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
  /** The entry's own persisted fase (spec 0163 D-1/D-2): the "Fase" select's pin while open, or the read-only value shown once a task is linked. */
  stage: TimeEntryWorkOrderStageRef | null
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
  stage,
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
  const workOrderId = useWatch({ control, name: 'work_order_id' })

  const registrySelected = isTaskLinked ? refOf(taskDetail?.registry) : registry
  const opportunitySelected = isTaskLinked ? refOf(taskDetail?.opportunity) : opportunity
  const workOrderSelected = isTaskLinked ? workOrderRefOf(taskDetail?.work_order) : workOrder
  const linksDisabled = disabled || isTaskLinked

  // D-1: the picker is offered ONLY without a task, once a commessa is chosen.
  // With a task linked, the server decides the fase from it — this form shows
  // the persisted value read-only when the resource carries one, never a
  // picker of its own (AC-008).
  const showStageSelect = !isTaskLinked && workOrderId !== null
  const { options: stageOptions, isLoading: stagesLoading } = useTimeEntryWorkOrderStageOptions(
    showStageSelect ? workOrderId : null,
    stage,
  )

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

      {isTaskLinked && stage ? (
        <MetaField control={control} name="work_order_stage_id" metaKey="work_order_stage_id" label={t('timeEntries.form.workOrderStage')}>
          {() => (
            <FormControl>
              <Input value={stage.name} disabled readOnly />
            </FormControl>
          )}
        </MetaField>
      ) : showStageSelect ? (
        <MetaField control={control} name="work_order_stage_id" metaKey="work_order_stage_id" label={t('timeEntries.form.workOrderStage')}>
          {({ field, disabled: fieldDisabled }) => (
            <Select
              value={field.value === null ? NO_STAGE_VALUE : String(field.value)}
              onValueChange={(next) => field.onChange(next === NO_STAGE_VALUE ? null : Number(next))}
              disabled={fieldDisabled || disabled || stagesLoading}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('timeEntries.form.workOrderStagePlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value={NO_STAGE_VALUE}>{t('timeEntries.form.workOrderStageNoStage')}</SelectItem>
                {stageOptions.map((option) => (
                  <SelectItem key={option.id} value={String(option.id)}>
                    {option.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      ) : null}

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
