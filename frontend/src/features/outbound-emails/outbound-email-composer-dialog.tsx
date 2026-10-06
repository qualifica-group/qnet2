import { useTranslation } from 'react-i18next'
import { AlertCircle, Loader2, MailPlus, PenLine, Save, Send, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useOutboundEmailComposer } from '@/features/outbound-emails/use-outbound-email-composer'
import { OutboundEmailComposerForm } from '@/features/outbound-emails/outbound-email-composer-form'
import { OutboundEmailAttachmentsSection } from '@/features/outbound-emails/outbound-email-attachments-section'
import { OutboundEmailDialogFooter, OutboundEmailDialogHeader } from '@/features/outbound-emails/outbound-email-dialog-chrome'
import { OutboundEmailStatusBadge } from '@/features/outbound-emails/outbound-email-status-badge'
import type { EmailOwnerRef } from '@/features/outbound-emails/types'

export interface OutboundEmailComposerDialogProps {
  owner: EmailOwnerRef
  emailId: number
  /** True only right after "Nuova email" created this draft (D-2's silent-delete-on-empty-close). */
  justCreated: boolean
  /** See `UseOutboundEmailComposerOptions.prefillDefaultTo`. */
  prefillDefaultTo?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Composer dialog shell (AC-020/AC-021): a large, responsive Dialog (the
 * existing `size="lg"` rung already collapses to `w-[calc(100%-2rem)]` at
 * 375px, ui-design.md §3) that owns only layout — every behaviour lives in
 * `useOutboundEmailComposer`. Header and footer are fixed bands and only the
 * body scrolls, so the send actions never leave the viewport; the scroll sits
 * on an inner wrapper because `DialogContent` must never clip (the template
 * picker's popup is portaled into it).
 */
export function OutboundEmailComposerDialog({
  owner,
  emailId,
  justCreated,
  prefillDefaultTo,
  open,
  onOpenChange,
}: OutboundEmailComposerDialogProps) {
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
  } = useOutboundEmailComposer({ owner, emailId, justCreated, prefillDefaultTo, open, onOpenChange })

  const isBusy = isSaving || isSending

  return (
    <Dialog open={open} onOpenChange={(next) => void handleOpenChange(next)}>
      <DialogContent size="lg" className="grid-cols-1 gap-0 p-0">
        <OutboundEmailDialogHeader
          icon={justCreated ? MailPlus : PenLine}
          title={justCreated ? t('outboundEmails.composer.title') : t('outboundEmails.composer.editTitle')}
          description={t('outboundEmails.composer.subtitle')}
          trailing={email ? <OutboundEmailStatusBadge status={email.status} /> : null}
        />

        <div className="max-h-[70vh] overflow-y-auto px-4 py-4 sm:px-5">
          {isLoading ? (
            <ComposerSkeleton />
          ) : isError || !email ? (
            <p role="alert" className="flex items-center gap-1.5 text-sm text-destructive">
              <AlertCircle className="size-4 shrink-0" aria-hidden="true" />
              {t('outboundEmails.detail.loadError')}
            </p>
          ) : (
            <div className="flex flex-col gap-4">
              <OutboundEmailComposerForm
                owner={owner}
                form={form}
                composeContext={composeContext}
                disabled={isBusy}
              />
              <OutboundEmailAttachmentsSection
                owner={owner}
                emailId={emailId}
                email={email}
                composeContext={composeContext}
                disabled={isBusy}
              />
            </div>
          )}
        </div>

        <OutboundEmailDialogFooter className="justify-between">
          <div>
            {email?.can.delete ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                onClick={() => void handleDeleteDraft()}
                disabled={isDeleting || isBusy}
              >
                <Trash2 className="size-3.5" aria-hidden="true" />
                {t('outboundEmails.composer.deleteDraft')}
              </Button>
            ) : null}
          </div>
          <div className="ml-auto flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => void handleOpenChange(false)}>
              {t('outboundEmails.composer.cancel')}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => void handleSaveDraft()}
              disabled={isBusy || !email?.can.update}
            >
              {isSaving ? (
                <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
              ) : (
                <Save className="size-3.5" aria-hidden="true" />
              )}
              {isSaving ? t('outboundEmails.composer.saving') : t('outboundEmails.composer.saveDraft')}
            </Button>
            <Button type="button" size="sm" onClick={() => void handleSend()} disabled={isBusy || !email?.can.send}>
              {isSending ? (
                <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
              ) : (
                <Send className="size-3.5" aria-hidden="true" />
              )}
              {isSending ? t('outboundEmails.composer.sending') : t('outboundEmails.composer.send')}
            </Button>
          </div>
        </OutboundEmailDialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** Placeholder shaped like the real composer (envelope, editor, attachments), not a spinner on blank. */
function ComposerSkeleton() {
  return (
    <div className="flex flex-col gap-4" aria-hidden="true">
      <Skeleton className="h-9 w-full sm:w-72" />
      <Skeleton className="h-36 w-full" />
      <Skeleton className="h-52 w-full" />
      <Skeleton className="h-24 w-full" />
    </div>
  )
}
