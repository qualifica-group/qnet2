import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { AlertCircle, Clock, Download, Loader2, Mail, Paperclip, RotateCw } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { UserAvatar } from '@/components/user-avatar'
import { formatDateTime } from '@/lib/formatting/date-display'
import { saveBlob } from '@/lib/download'
import { downloadOutboundEmailAttachment } from '@/features/outbound-emails/api'
import { useSendOutboundEmail, useOutboundEmail } from '@/features/outbound-emails/use-outbound-email-draft'
import { OutboundEmailStatusBadge } from '@/features/outbound-emails/outbound-email-status-badge'
import { OutboundEmailAttachmentTile } from '@/features/outbound-emails/outbound-email-attachment-tile'
import { OutboundEmailDialogFooter, OutboundEmailDialogHeader } from '@/features/outbound-emails/outbound-email-dialog-chrome'
import type { EmailOwnerRef, OutboundEmailAttachment } from '@/features/outbound-emails/types'

export interface OutboundEmailDetailDialogProps {
  owner: EmailOwnerRef
  emailId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Read-only view of a queued/sent/failed email (AC-019): every field shown
 * as text/rich content, attachments downloadable through the nested,
 * commessa-scoped endpoint (D-8). A `failed` email with `can.send` offers
 * "Reinvia" straight from here — no need to reopen the composer.
 */
export function OutboundEmailDetailDialog({ owner, emailId, open, onOpenChange }: OutboundEmailDetailDialogProps) {
  const { t } = useTranslation()
  const { data: email, isLoading, isError, refetch } = useOutboundEmail(owner, emailId)
  const resend = useSendOutboundEmail(owner, emailId)
  const attachmentsTitleId = useId()

  const handleResend = async () => {
    try {
      await resend.mutateAsync()
      toast.success(t('outboundEmails.composer.sent'))
    } catch {
      toast.error(t('outboundEmails.composer.genericError'))
    }
  }

  const handleDownload = (attachment: OutboundEmailAttachment) => {
    downloadOutboundEmailAttachment(owner, emailId, attachment.id)
      .then((blob) => saveBlob(blob, attachment.original_name))
      .catch(() => toast.error(t('outboundEmails.detail.downloadError')))
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="lg" className="grid-cols-1 gap-0 p-0">
        <OutboundEmailDialogHeader
          icon={Mail}
          title={t('outboundEmails.detail.title')}
          description={t('outboundEmails.detail.subtitle')}
          trailing={email ? <OutboundEmailStatusBadge status={email.status} /> : null}
        />

        <div className="max-h-[70vh] overflow-y-auto px-4 py-4 sm:px-5">
          {isLoading ? (
            <DetailSkeleton />
          ) : isError || !email ? (
            <div className="flex flex-col items-start gap-2">
              <p role="alert" className="flex items-center gap-1.5 text-sm text-destructive">
                <AlertCircle className="size-4 shrink-0" aria-hidden="true" />
                {t('outboundEmails.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : (
            <div className="flex flex-col gap-4 text-sm">
              <h3 className="text-base font-semibold break-words text-foreground">
                {email.subject || t('outboundEmails.detail.noSubject')}
              </h3>

              <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 shadow-xs">
                <div className="flex items-start gap-3">
                  <UserAvatar name={email.sender.name} />
                  <div className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate font-medium">{email.sender.name}</span>
                    {email.from_address ? (
                      <span className="truncate text-xs text-muted-foreground">{email.from_address}</span>
                    ) : null}
                  </div>
                  <span className="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                    <Clock className="size-3.5" aria-hidden="true" />
                    {formatDateTime(email.sent_at ?? email.failed_at ?? email.updated_at)}
                  </span>
                </div>

                <dl className="flex flex-col gap-1.5 border-t pt-3">
                  <RecipientsRow label={t('outboundEmails.detail.to')} addresses={email.to} />
                  <RecipientsRow label={t('outboundEmails.detail.cc')} addresses={email.cc} />
                  <RecipientsRow label={t('outboundEmails.detail.bcc')} addresses={email.bcc} />
                </dl>
              </div>

              {email.status === 'failed' && email.error_message ? (
                <div
                  role="alert"
                  className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive"
                >
                  <AlertCircle className="mt-px size-4 shrink-0" aria-hidden="true" />
                  <p className="min-w-0 break-words">
                    <span className="font-semibold">{t('outboundEmails.detail.errorMessage')}: </span>
                    {email.error_message}
                  </p>
                </div>
              ) : null}

              <section aria-label={t('outboundEmails.detail.body')} className="rounded-lg border bg-card p-4 shadow-xs">
                <RichTextContent html={email.body} />
              </section>

              {email.attachments.length > 0 ? (
                <section aria-labelledby={attachmentsTitleId} className="flex flex-col gap-2">
                  <h4 id={attachmentsTitleId} className="flex items-center gap-1.5 text-xs font-semibold">
                    <Paperclip className="size-3.5 text-muted-foreground" aria-hidden="true" />
                    {t('outboundEmails.detail.attachments')}
                    <Badge variant="secondary" className="px-1.5 py-0 text-[11px]">
                      {email.attachments.length}
                    </Badge>
                  </h4>
                  <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {email.attachments.map((attachment) => (
                      <OutboundEmailAttachmentTile
                        key={attachment.id}
                        attachment={attachment}
                        action={
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={t('attachments.download')}
                            onClick={() => handleDownload(attachment)}
                          >
                            <Download aria-hidden="true" />
                          </Button>
                        }
                      />
                    ))}
                  </ul>
                </section>
              ) : null}
            </div>
          )}
        </div>

        <OutboundEmailDialogFooter>
          <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>
            {t('common.close')}
          </Button>
          {email?.status === 'failed' && email.can.send ? (
            <Button type="button" size="sm" onClick={() => void handleResend()} disabled={resend.isPending}>
              {resend.isPending ? (
                <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
              ) : (
                <RotateCw className="size-3.5" aria-hidden="true" />
              )}
              {t('outboundEmails.detail.resend')}
            </Button>
          ) : null}
        </OutboundEmailDialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** One "A / CC / CCN" line with an address chip each; an empty field renders nothing. */
function RecipientsRow({ label, addresses }: { label: string; addresses: string[] }) {
  if (addresses.length === 0) {
    return null
  }
  return (
    <div className="grid grid-cols-[2.5rem_minmax(0,1fr)] items-start gap-2">
      <dt className="pt-0.5 text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="flex min-w-0 flex-wrap gap-1">
        {addresses.map((address) => (
          <Badge key={address} variant="secondary" className="max-w-full truncate font-normal">
            {address}
          </Badge>
        ))}
      </dd>
    </div>
  )
}

/** Placeholder shaped like the reading pane (subject, sender card, body). */
function DetailSkeleton() {
  return (
    <div className="flex flex-col gap-4" aria-hidden="true">
      <Skeleton className="h-6 w-2/3" />
      <Skeleton className="h-28 w-full" />
      <Skeleton className="h-40 w-full" />
    </div>
  )
}
