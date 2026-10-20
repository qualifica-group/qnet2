import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Ban, Check, Pencil, Send, Trash2, X } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { AbsenceRejectDialog } from '@/features/absences/requests/absence-reject-dialog'
import type { AbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import type { AbsenceRequest } from '@/features/absences/types'

interface AbsenceRequestActionsBarProps {
  request: AbsenceRequest
  actions: AbsenceRequestActions
  onEdit: (request: AbsenceRequest) => void
}

/**
 * Workflow buttons of the record header, each shown only when the server grants it in
 * `request.can` (the UI hides, the backend authorizes). Nothing at all when none is granted.
 */
export function AbsenceRequestActionsBar({ request, actions, onEdit }: AbsenceRequestActionsBarProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [rejecting, setRejecting] = useState(false)
  const { can } = request
  const hasActions = can.update || can.submit || can.approve || can.reject || can.cancel || can.delete

  const confirmThen = async (kind: 'delete' | 'cancel', run: () => void) => {
    const confirmed = await confirm({
      title: t(kind === 'delete' ? 'absences.requests.actions.deleteTitle' : 'absences.requests.actions.cancelTitle'),
      description: t(
        kind === 'delete'
          ? 'absences.requests.actions.deleteDescription'
          : 'absences.requests.actions.cancelDescription',
      ),
      confirmLabel: t(kind === 'delete' ? 'absences.requests.actions.delete' : 'absences.requests.actions.cancel'),
      cancelLabel: t('absences.requests.actions.cancelBack'),
      tone: 'destructive',
    })
    if (confirmed) {
      run()
    }
  }

  if (!hasActions) {
    return null
  }

  return (
    <div className="flex flex-wrap items-center justify-end gap-1.5">
      {can.update ? (
        <Button type="button" size="sm" variant="outline" onClick={() => onEdit(request)}>
          <Pencil aria-hidden="true" />
          {t('absences.requests.actions.edit')}
        </Button>
      ) : null}
      {can.submit ? (
        <Button type="button" size="sm" disabled={actions.submit.isPending} onClick={() => actions.submit.mutate()}>
          <Send aria-hidden="true" />
          {t('absences.requests.actions.submit')}
        </Button>
      ) : null}
      {can.approve ? (
        <Button
          type="button"
          size="sm"
          variant="success"
          disabled={actions.approve.isPending}
          onClick={() => actions.approve.mutate()}
        >
          <Check aria-hidden="true" />
          {t('absences.requests.actions.approve')}
        </Button>
      ) : null}
      {can.reject ? (
        <Button type="button" size="sm" variant="outline" onClick={() => setRejecting(true)}>
          <X aria-hidden="true" />
          {t('absences.requests.actions.reject')}
        </Button>
      ) : null}
      {can.cancel ? (
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={actions.cancel.isPending}
          onClick={() => void confirmThen('cancel', () => actions.cancel.mutate())}
        >
          <Ban aria-hidden="true" />
          {t('absences.requests.actions.cancel')}
        </Button>
      ) : null}
      {can.delete ? (
        <Button
          type="button"
          size="sm"
          variant="destructive"
          disabled={actions.remove.isPending}
          onClick={() => void confirmThen('delete', () => actions.remove.mutate())}
        >
          <Trash2 aria-hidden="true" />
          {t('absences.requests.actions.delete')}
        </Button>
      ) : null}
      {can.reject ? (
        <AbsenceRejectDialog
          open={rejecting}
          onOpenChange={setRejecting}
          onReject={(reason) => actions.reject.mutateAsync(reason)}
          isPending={actions.reject.isPending}
        />
      ) : null}
    </div>
  )
}
