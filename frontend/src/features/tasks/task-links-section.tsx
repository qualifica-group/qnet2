import { useTranslation } from 'react-i18next'
import { Link2 } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useTaskWorkOrderStageOptions } from '@/features/tasks/use-task-work-order-stage-options'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
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
  /** Edit-mode hydration of the two optional links. */
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
  /** Edit-mode hydration of the persisted fase, possibly closed (spec 0146 D-3, `useTaskWorkOrderStageOptions` keeps it selectable). */
  workOrderStage: TaskWorkOrderStageRef | null
  /** D-3: the fase belongs to the commessa, so a pick/clear here invalidates whatever fase was selected. */
  onWorkOrderChange: () => void
}

/**
 * "Collegamenti": the optional Opportunita' and Commessa a task may hang off,
 * plus the Commessa's own "Fase" (spec 0146 D-3) — visible only once a
 * commessa is picked AND the task has no parent (a sub-task's fase is
 * `prohibited` server-side). The fase list is the commessa's OPEN fasi plus
 * the persisted one so an edit form never strands on a value it can no
 * longer resubmit (`useTaskWorkOrderStageOptions`).
 */
export function TaskLinksSection({
  control,
  opportunity,
  workOrder,
  workOrderStage,
  onWorkOrderChange,
}: TaskLinksSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const workOrderId = useWatch({ control, name: 'work_order_id' })
  const parentTaskId = useWatch({ control, name: 'parent_task_id' })
  const showStageField = workOrderId !== null && parentTaskId === null
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
