import { useTranslation } from 'react-i18next'
import { Link2 } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { LEADS_FOR_SELECT_RESOURCE } from '@/features/leads/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useTaskWorkOrderStageOptions } from '@/features/tasks/use-task-work-order-stage-options'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskWorkOrderStageRef } from '@/features/tasks/types'

/**
 * Resource segment of the work orders for-select endpoint. The canonical ADR
 * 0011 path for the domain — but note that `routes/api/work-orders.php` does
 * NOT declare it today (only `next-code`, `quote-offer-lines/for-select`,
 * `form-context` and the CRUD). The frozen `data_contract` requires
 * `work_order_id` on the payload, so the field exists; until the endpoint is
 * added backend-side the picker simply renders its own error state and the
 * rest of the form is unaffected. Raised with the backend owner, not assumed
 * away.
 */
const WORK_ORDERS_FOR_SELECT_RESOURCE = 'work-orders'

/** Sentinel Radix `<Select>` value for "Senza fase" (D-2): the control itself only ever emits `null`/a stage id. */
const NO_STAGE_VALUE = '__no_stage__'

interface TaskLinksSectionProps {
  control: Control<TaskFormValues>
  /** Edit-mode hydration of the three optional links. */
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
  lead: RelationFieldRef | null
  /** Edit-mode hydration of the persisted fase, possibly closed (spec 0146 D-3, `useTaskWorkOrderStageOptions` keeps it selectable). */
  workOrderStage: TaskWorkOrderStageRef | null
  /** D-3: the fase belongs to the commessa, so a pick/clear here invalidates whatever fase was selected. */
  onWorkOrderChange: () => void
  /** Spec 0154 D-11: picking a commessa clears the opportunita' and sets the registry from its own `meta.registry_id`. */
  onWorkOrderItemChange: (item: ForSelectItem | null) => void
  /** Spec 0154 D-11: picking an opportunita' clears the commessa (and its fase). */
  onOpportunityChange: (nextOpportunityId: number | null) => void
}

/**
 * "Collegamenti": the optional Opportunita', Commessa and Lead (spec 0154
 * D-4) a task may hang off, plus the Commessa's own "Fase" (spec 0146 D-3) —
 * visible only once a commessa is picked AND the task has no parent (a
 * sub-task's fase is `prohibited` server-side). The fase list is the
 * commessa's OPEN fasi plus the persisted one so an edit form never strands
 * on a value it can no longer resubmit (`useTaskWorkOrderStageOptions`).
 *
 * Spec 0154 D-11: Opportunita', Commessa and Lead are all scoped to the
 * chosen Anagrafica once one is picked (`params.registry_id`) — unfiltered
 * (every option) while none is, since picking a Commessa is itself how an
 * Anagrafica-less task acquires one (`onWorkOrderItemChange`, wired in
 * `useTaskForm`). Commessa and Opportunita' stay mutually exclusive:
 * `onWorkOrderItemChange`/`onOpportunityChange` clear one another on an
 * actual pick, the server 422s if both ever reach it regardless.
 */
export function TaskLinksSection({
  control,
  opportunity,
  workOrder,
  lead,
  workOrderStage,
  onWorkOrderChange,
  onWorkOrderItemChange,
  onOpportunityChange,
}: TaskLinksSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const registryId = useWatch({ control, name: 'registry_id' })
  const workOrderId = useWatch({ control, name: 'work_order_id' })
  const parentTaskId = useWatch({ control, name: 'parent_task_id' })
  const showStageField = workOrderId !== null && parentTaskId === null
  const registryScopedParams = registryId !== null ? { registry_id: registryId } : undefined
  // D-3: a sub-task can never show this field, so there is nothing to fetch
  // options for either — `enabled: false` inside the hook, no wasted request.
  const { options: stageOptions, isLoading: stagesLoading } = useTaskWorkOrderStageOptions(
    showStageField ? workOrderId : null,
    workOrderStage,
  )

  return (
    <FormSection
      icon={Link2}
      title={t('tasks.form.sections.links.title')}
      description={t('tasks.form.sections.links.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="opportunity_id"
          metaKey="opportunity_id"
          label={t('tasks.form.opportunity')}
          resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.opportunitySearch')}
          selected={opportunity}
          onValueChange={onOpportunityChange}
          params={registryScopedParams}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="work_order_id"
          metaKey="work_order_id"
          label={t('tasks.form.workOrder')}
          resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.workOrderSearch')}
          selected={workOrder}
          onValueChange={onWorkOrderChange}
          onItemChange={onWorkOrderItemChange}
          params={registryScopedParams}
          // `work-orders/for-select` is narrowed by `WorkOrderVisibilityScope`
          // and `ids[]` deliberately does NOT bypass it, so the linked
          // commessa may be missing from the options. Pinning the persisted
          // value keeps it selectable after the user changes the pick. The
          // ref comes from `task.work_order`, already in this form's payload
          // (D-9 does not obscure linked-record labels): nothing new is
          // disclosed.
          pinned={workOrder}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="lead_id"
          metaKey="lead_id"
          label={t('tasks.form.lead')}
          hint={t('tasks.form.hints.leadScoped')}
          resource={LEADS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.leadSearch')}
          selected={lead}
          params={registryScopedParams}
          {...selectLabels}
        />

        {showStageField ? (
          <MetaField
            control={control}
            name="work_order_stage_id"
            metaKey="work_order_stage_id"
            label={t('tasks.form.workOrderStage')}
          >
            {({ field, disabled }) => (
              <Select
                value={field.value === null ? NO_STAGE_VALUE : String(field.value)}
                onValueChange={(next) => field.onChange(next === NO_STAGE_VALUE ? null : Number(next))}
                disabled={disabled || stagesLoading}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('tasks.form.workOrderStagePlaceholder')} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value={NO_STAGE_VALUE}>{t('tasks.form.workOrderStageNoStage')}</SelectItem>
                  {stageOptions.map((stage) => (
                    <SelectItem key={stage.id} value={String(stage.id)}>
                      {stage.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        ) : null}
      </div>
    </FormSection>
  )
}
