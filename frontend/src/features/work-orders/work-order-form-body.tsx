import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { useWorkOrderForm } from '@/features/work-orders/use-work-order-form'
import { WorkOrderCreateSections } from '@/features/work-orders/work-order-create-sections'
import { WorkOrderFormHeader } from '@/features/work-orders/work-order-form-header'
import type { WorkOrderDetail, WorkOrderFormMode } from '@/features/work-orders/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const WORK_ORDER_FORM_ID = 'work-order-form'

/** Hoisted: the form hook memoizes its defaults on the mode's identity. */
const CREATE_MODE: WorkOrderFormMode = { type: 'create' }

interface WorkOrderFormBodyProps {
  onSuccess: (workOrder: WorkOrderDetail) => void
  onCancel: () => void
  /** Sequential code suggestion prefilled into the `code` default (D-1). */
  initialCode?: string
}

/**
 * The work order create form UI, a replica of the work order detail (spec
 * 0195 D-8 applied to Commesse, user directive 2026-10-06): the same
 * `RecordCanvas`, the record card with its identity band, KPI strip and
 * sections, every row closed until clicked (`WorkOrderCreateSections`). There
 * is no edit form: the detail edits a persisted work order in place.
 *
 * Every field sits in `MetaField` (spec 0004): hidden means absent,
 * non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions`. Pure composition: every non-render concern lives in
 * `useWorkOrderForm`.
 */
export function WorkOrderFormBody({ onSuccess, onCancel, initialCode }: WorkOrderFormBodyProps) {
  const { t } = useTranslation()
  const workOrderForm = useWorkOrderForm({ mode: CREATE_MODE, onSuccess, initialCode })
  const { form, serverError, onSubmit } = workOrderForm
  const draft = useDraftInlineEdit(form)
  const { isSubmitting } = form.formState

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={WORK_ORDER_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordBody side={null}>
            <RecordCard>
              <WorkOrderFormHeader
                control={form.control}
                formId={WORK_ORDER_FORM_ID}
                isSubmitting={isSubmitting}
                submitError={serverError}
                onCancel={onCancel}
              />
              <WorkOrderCreateSections workOrderForm={workOrderForm} draft={draft} />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={WORK_ORDER_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('workOrders.form.save')}
              submittingLabel={t('workOrders.form.saving')}
              cancel={{ label: t('workOrders.form.cancel'), onCancel }}
            />
          </RecordBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
