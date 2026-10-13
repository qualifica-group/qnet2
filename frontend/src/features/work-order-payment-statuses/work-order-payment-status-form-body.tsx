import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { WorkOrderPaymentStatusRows } from '@/features/work-order-payment-statuses/work-order-payment-status-rows'
import { useWorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'
import type { WorkOrderPaymentStatusDetail } from '@/features/work-order-payment-statuses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const WORK_ORDER_PAYMENT_STATUS_FORM_ID = 'work-order-payment-status-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface WorkOrderPaymentStatusFormBodyProps {
  onSuccess: (workOrderPaymentStatus: WorkOrderPaymentStatusDetail) => void
  onCancel: () => void
}

/**
 * The work order payment status create form as a replica of the detail (spec 0235): the
 * same rows, closed until clicked (Fatto/Ripristina, nothing saved per row);
 * Salva validates and creates everything at once.
 */
export function WorkOrderPaymentStatusFormBody({ onSuccess, onCancel }: WorkOrderPaymentStatusFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useWorkOrderPaymentStatusForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('workOrderPaymentStatuses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={WORK_ORDER_PAYMENT_STATUS_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('workOrderPaymentStatuses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Flag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('workOrderPaymentStatuses.form.save'),
          saving: t('workOrderPaymentStatuses.form.saving'),
          cancel: t('workOrderPaymentStatuses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('workOrderPaymentStatuses.form.sections.identity.title')} full>
            <RecordFieldList>
              <WorkOrderPaymentStatusRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
