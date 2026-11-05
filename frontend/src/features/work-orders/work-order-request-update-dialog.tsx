import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import axios from 'axios'
import { apiErrorMessage } from '@/lib/forbidden-message'
import {
  RequestUpdateDialog,
  type RequestUpdateOption,
  type RequestUpdateTexts,
} from '@/features/shared/request-update-dialog'
import { useRequestWorkOrderUpdate } from '@/features/work-orders/use-request-work-order-update'
import type { WorkOrderDetailWithPermissions, WorkOrderRequestUpdateTarget } from '@/features/work-orders/types'

const FORM_ID = 'work-order-request-update-form'
const DEFAULT_TARGET: WorkOrderRequestUpdateTarget = 'participants'

type RecipientWorkOrder = Pick<WorkOrderDetailWithPermissions, 'participants' | 'supervisors'>

/** Count of distinct recipients across both lists (`all`), a user in both counted once. */
function uniqueRecipientCount({ participants, supervisors }: RecipientWorkOrder): number {
  return new Set([...participants, ...supervisors].map((person) => person.id)).size
}

function buildTargetOptions(
  workOrder: RecipientWorkOrder,
  t: TFunction,
): RequestUpdateOption<WorkOrderRequestUpdateTarget>[] {
  return [
    {
      value: 'participants',
      label: t('workOrders.actions.requestUpdate.targetParticipants'),
      count: workOrder.participants.length,
      hint:
        workOrder.supervisors.length > 0 ? t('workOrders.actions.requestUpdate.targetParticipantsHint') : undefined,
    },
    {
      value: 'supervisors',
      label: t('workOrders.actions.requestUpdate.targetSupervisors'),
      count: workOrder.supervisors.length,
    },
    {
      value: 'all',
      label: t('workOrders.actions.requestUpdate.targetAll'),
      count: uniqueRecipientCount(workOrder),
    },
  ]
}

function buildTexts(t: TFunction): RequestUpdateTexts {
  return {
    title: t('workOrders.actions.requestUpdate.title'),
    description: t('workOrders.actions.requestUpdate.description'),
    target: t('workOrders.actions.requestUpdate.target'),
    recipientsEmpty: t('workOrders.actions.requestUpdate.recipientsEmpty'),
    message: t('workOrders.actions.requestUpdate.message'),
    messagePlaceholder: t('workOrders.actions.requestUpdate.messagePlaceholder'),
    messageTooShort: t('workOrders.actions.requestUpdate.messageTooShort'),
    messageTooLong: t('workOrders.actions.requestUpdate.messageTooLong'),
    submit: t('workOrders.actions.requestUpdate.submit'),
    success: t('workOrders.actions.requestUpdate.success'),
  }
}

interface WorkOrderRequestUpdateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  workOrder: WorkOrderDetailWithPermissions
}

/**
 * "Richiedi aggiornamento" of a work order (spec 0352): groups computed from
 * the record's own `participants`/`supervisors`; `participants` also CCs every
 * supervisor server-side. The form lives in the shared `RequestUpdateDialog`.
 */
export function WorkOrderRequestUpdateDialog({ open, onOpenChange, workOrder }: WorkOrderRequestUpdateDialogProps) {
  const { t, i18n } = useTranslation()
  const requestUpdateMutation = useRequestWorkOrderUpdate(workOrder.id)

  // A 422 without field errors (work order already completed/closed) carries the server's own reason.
  const errorMessage = (error: unknown): string => {
    const serverMessage = axios.isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : undefined
    return apiErrorMessage(error, serverMessage || t('workOrders.form.genericError'), i18n)
  }

  return (
    <RequestUpdateDialog
      open={open}
      onOpenChange={onOpenChange}
      formId={FORM_ID}
      options={buildTargetOptions(workOrder, t)}
      defaultTarget={DEFAULT_TARGET}
      texts={buildTexts(t)}
      isPending={requestUpdateMutation.isPending}
      onSubmit={(payload) => requestUpdateMutation.mutateAsync(payload)}
      onError={(error) => toast.error(errorMessage(error))}
    />
  )
}
