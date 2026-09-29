import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useWorkOrderEmailComposer } from '@/features/work-order-emails/use-work-order-email-composer'
import { WorkOrderEmailComposerForm } from '@/features/work-order-emails/work-order-email-composer-form'
import { WorkOrderEmailAttachmentsSection } from '@/features/work-order-emails/work-order-email-attachments-section'

export interface WorkOrderEmailComposerDialogProps {
  workOrderId: number
  emailId: number
  /** True only right after "Nuova email" created this draft (D-2's silent-delete-on-empty-close). */
  justCreated: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Composer dialog shell (AC-020/AC-021): a large, responsive Dialog (the
 * existing `size="lg"` rung already collapses to `w-[calc(100%-2rem)]` at
 * 375px, ui-design.md §3) that owns only layout — every behaviour lives in
 * `useWorkOrderEmailComposer`.
 */
export function WorkOrderEmailComposerDialog({
  workOrderId,
  emailId,
  justCreated,
  open,
  onOpenChange,
}: WorkOrderEmailComposerDialogProps) {
  const { t } = useTranslation()
  const {
    form,
    email,
    isLoading,
    isError,
    composeContext,
    isSaving,
    isSending,
    isDeleting,
    handleSaveDraft,
    handleSend,
    handleDeleteDraft,
    handleOpenChange,
  } = useWorkOrderEmailComposer({ workOrderId, emailId, justCreated, open, onOpenChange })

  const isBusy = isSaving || isSending

  return (
    <Dialog open={open} onOpenChange={(next) => void handleOpenChange(next)}>
      <DialogContent size="lg" className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {justCreated ? t('workOrderEmails.composer.title') : t('workOrderEmails.composer.editTitle')}
          </DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center py-10">
            <Loader2 className="size-5 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : isError || !email ? (
          <p role="alert" className="text-sm text-destructive">
            {t('workOrderEmails.detail.loadError')}
          </p>
        ) : (
          <div className="flex flex-col gap-4">
            <WorkOrderEmailComposerForm
              workOrderId={workOrderId}
              form={form}
              composeContext={composeContext}
              disabled={isBusy}
            />
            <WorkOrderEmailAttachmentsSection
              workOrderId={workOrderId}
              emailId={emailId}
              email={email}
              composeContext={composeContext}
              disabled={isBusy}
            />
          </div>
        )}

        <DialogFooter className="sm:justify-between">
          <div>
            {email?.can.delete ? (
              <Button
                type="button"
                variant="ghost"
                className="text-destructive hover:text-destructive"
                onClick={() => void handleDeleteDraft()}
                disabled={isDeleting || isBusy}
              >
                {t('workOrderEmails.composer.deleteDraft')}
              </Button>
            ) : null}
          </div>
          <div className="flex gap-2">
            <Button type="button" variant="outline" onClick={() => void handleOpenChange(false)}>
              {t('workOrderEmails.composer.cancel')}
            </Button>
            <Button
              type="button"
              variant="secondary"
              onClick={() => void handleSaveDraft()}
              disabled={isBusy || !email?.can.update}
            >
              {isSaving ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
              {isSaving ? t('workOrderEmails.composer.saving') : t('workOrderEmails.composer.saveDraft')}
            </Button>
            <Button type="button" onClick={() => void handleSend()} disabled={isBusy || !email?.can.send}>
              {isSending ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
              {isSending ? t('workOrderEmails.composer.sending') : t('workOrderEmails.composer.send')}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
