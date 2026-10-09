import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Info, ListChecks } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { changeLinesStatus, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { LineStatusOptions } from '@/features/purchase-requests/line-status-options'
import { LineStatusSummaryTable } from '@/features/purchase-requests/line-status-summary-table'
import {
  commonCapabilities,
  dialogOptions,
  sharedRequest,
  type LineTarget,
} from '@/features/purchase-requests/line-status-transitions'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import type { LineStatus, LineStatusChangeResult } from '@/features/purchase-requests/types'

const REASON_MAX = 2000
const STATUS_LABEL_ID = 'purchase-line-status-target'

interface LineStatusDialogProps {
  /**
   * Lines to move (one from a row action, many from the bulk action); empty
   * closes the dialog. Callers key the dialog by the target ids so a reopen
   * starts from the current status, not from the previous choice.
   */
  targets: readonly LineTarget[]
  onOpenChange: (open: boolean) => void
  onChanged: (result: LineStatusChangeResult) => void
}

function Note({ children }: { children: string }) {
  return (
    <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
      <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      {children}
    </p>
  )
}

/**
 * Status change of one or more lines (D-9, D-17). Offers only the transitions
 * every selected line allows; with none in common it says so and cannot
 * confirm. The change is all-or-nothing on the server.
 */
export function LineStatusDialog({ targets, onOpenChange, onChanged }: LineStatusDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [choice, setChoice] = useState<LineStatus | null>(null)
  const [reason, setReason] = useState('')
  const { options, current } = dialogOptions(targets)
  const selected = choice !== null && options.includes(choice) ? choice : current
  const canConfirm = selected !== null && selected !== current
  const count = targets.length
  const isBulk = count > 1
  const request = sharedRequest(targets)
  const capabilities = commonCapabilities(targets)

  const mutation = useMutation({
    mutationFn: (toStatus: LineStatus) =>
      changeLinesStatus({
        line_ids: targets.map((target) => target.id),
        to_status: toStatus,
        reason: reason.trim() === '' ? undefined : reason.trim(),
      }),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      toast.success(t('purchaseRequests.statusDialog.done', { count: result.updated_count }))
      if (result.closed_purchase_request_ids.length > 0) {
        toast.info(t('purchaseRequests.statusDialog.autoClosed', { count: result.closed_purchase_request_ids.length }))
      }
      onChanged(result)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseRequests.statusDialog.error')),
  })

  return (
    <Dialog open={count > 0} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ListChecks className="size-4" aria-hidden="true" />
            {isBulk
              ? t('purchaseRequests.statusDialog.titleBulk', { count })
              : t('purchaseRequests.statusDialog.title')}
          </DialogTitle>
          <DialogDescription>
            {request
              ? t('purchaseRequests.statusDialog.subtitle', { id: request.id, subject: request.subject })
              : t('purchaseRequests.statusDialog.subtitleMixed', { count: new Set(targets.map((target) => target.purchaseRequestId)).size })}
          </DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
          <div className="flex flex-col gap-1.5">
            <p className="text-xs font-medium">{t('purchaseRequests.statusDialog.selectedLines')}</p>
            <LineStatusSummaryTable targets={targets} />
          </div>
          {capabilities.length > 0 ? (
            <div className="flex flex-wrap items-center gap-1.5 text-xs">
              <span className="font-medium">{t('purchaseRequests.statusDialog.operatingAs')}</span>
              {capabilities.map((capability) => (
                <Badge key={capability} variant="secondary" className="text-[11px]">
                  {t(`purchaseRequests.statusDialog.capabilities.${capability}`)}
                </Badge>
              ))}
            </div>
          ) : null}
          {options.length === 0 ? (
            <p role="status" className="rounded-md border bg-surface px-3 py-2 text-xs">
              {t('purchaseRequests.statusDialog.noCommon')}
            </p>
          ) : (
            <>
              <div className="flex flex-col gap-1.5">
                <Label className="text-xs" id={STATUS_LABEL_ID}>
                  {t('purchaseRequests.statusDialog.newStatus')}
                  <span aria-hidden="true" className="text-destructive">*</span>
                </Label>
                <LineStatusOptions
                  labelledBy={STATUS_LABEL_ID}
                  options={options}
                  value={selected}
                  current={current}
                  onValueChange={setChoice}
                />
                {isBulk ? <Note>{t('purchaseRequests.statusDialog.commonStatesNote')}</Note> : null}
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="purchase-line-status-reason" className="text-xs">
                  {t('purchaseRequests.statusDialog.reason')}
                </Label>
                <Textarea
                  id="purchase-line-status-reason"
                  className="min-h-16 text-sm"
                  maxLength={REASON_MAX}
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                />
                {isBulk ? <Note>{t('purchaseRequests.statusDialog.reasonBulkNote', { count })}</Note> : null}
              </div>
            </>
          )}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            disabled={!canConfirm || mutation.isPending}
            onClick={() => canConfirm && selected !== null && mutation.mutate(selected)}
          >
            {isBulk ? t('purchaseRequests.statusDialog.confirmBulk', { count }) : t('purchaseRequests.statusDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
