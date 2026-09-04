import { useTranslation } from 'react-i18next'
import { Link2 } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'

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

interface TaskLinksSectionProps {
  control: Control<TaskFormValues>
  /** Edit-mode hydration of the two optional links. */
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
}

/**
 * "Collegamenti": the optional Opportunita' and Commessa a task may hang off.
 * Both are plain, unscoped pickers: nothing in the contract ties one to the
 * other or to the anagrafica, and inventing such a dependency would be a
 * business rule the backend does not enforce.
 */
export function TaskLinksSection({ control, opportunity, workOrder }: TaskLinksSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

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
      </div>
    </FormSection>
  )
}
