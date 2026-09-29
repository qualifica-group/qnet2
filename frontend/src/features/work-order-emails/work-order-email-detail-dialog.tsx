import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Download, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { formatDateTime } from '@/lib/formatting/date-display'
import { saveBlob } from '@/lib/download'
import { formatBytes } from '@/features/attachments/format-bytes'
import { downloadWorkOrderEmailAttachment } from '@/features/work-order-emails/api'
import { useSendWorkOrderEmail, useWorkOrderEmail } from '@/features/work-order-emails/use-work-order-email-draft'
import { WorkOrderEmailStatusBadge } from '@/features/work-order-emails/work-order-email-status-badge'
import type { OutboundEmailAttachment } from '@/features/work-order-emails/types'

export interface WorkOrderEmailDetailDialogProps {
  workOrderId: number
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
export function WorkOrderEmailDetailDialog({ workOrderId, emailId, open, onOpenChange }: WorkOrderEmailDetailDialogProps) {
  const { t } = useTranslation()
  const { data: email, isLoading, isError, refetch } = useWorkOrderEmail(workOrderId, emailId)
  const resend = useSendWorkOrderEmail(workOrderId, emailId)

  const handleResend = async () => {
    try {
      await resend.mutateAsync()
      toast.success(t('workOrderEmails.composer.sent'))
    } catch {
      toast.error(t('workOrderEmails.composer.genericError'))
    }
  }

  const handleDownload = (attachment: OutboundEmailAttachment) => {
    downloadWorkOrderEmailAttachment(workOrderId, emailId, attachment.id)
      .then((blob) => saveBlob(blob, attachment.original_name))
      .catch(() => toast.error(t('workOrderEmails.detail.downloadError')))
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="lg" className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('workOrderEmails.detail.title')}</DialogTitle>
          <DialogDescription>{t('workOrderEmails.detail.subtitle')}</DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center py-10">
            <Loader2 className="size-5 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : isError || !email ? (
          <div className="flex flex-col items-start gap-2">
            <p role="alert" className="text-sm text-destructive">
              {t('workOrderEmails.detail.loadError')}
            </p>
            <Button variant="outline" size="sm" onClick={() => refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : (
          <div className="flex flex-col gap-3 text-sm">
            <div className="flex items-center justify-between gap-2">
              <WorkOrderEmailStatusBadge status={email.status} />
              <span className="text-xs text-muted-foreground">
                {formatDateTime(email.sent_at ?? email.failed_at ?? email.updated_at)}
              </span>
            </div>

            <DetailRow label={t('workOrderEmails.detail.from')} value={email.from_address ?? email.sender.name} />
            {email.to.length > 0 ? <DetailRow label={t('workOrderEmails.detail.to')} value={email.to.join(', ')} /> : null}
            {email.cc.length > 0 ? <DetailRow label={t('workOrderEmails.detail.cc')} value={email.cc.join(', ')} /> : null}
            {email.bcc.length > 0 ? <DetailRow label={t('workOrderEmails.detail.bcc')} value={email.bcc.join(', ')} /> : null}
            <DetailRow label={t('workOrderEmails.detail.subject')} value={email.subject ?? '—'} />

            {email.status === 'failed' && email.error_message ? (
              <p
                role="alert"
                className="rounded-md border border-destructive/30 bg-destructive/5 px-2.5 py-1.5 text-xs text-destructive"
              >
                <span className="font-medium">{t('workOrderEmails.detail.errorMessage')}: </span>
                {email.error_message}
              </p>
            ) : null}

            <div>
              <p className="mb-1 text-xs font-medium text-muted-foreground">{t('workOrderEmails.detail.body')}</p>
              <div className="rounded-md border bg-card p-3">
                <RichTextContent html={email.body} />
              </div>
            </div>

            {email.attachments.length > 0 ? (
              <div>
                <p className="mb-1 text-xs font-medium text-muted-foreground">{t('workOrderEmails.detail.attachments')}</p>
                <ul className="flex flex-col gap-1.5">
                  {email.attachments.map((attachment) => (
                    <li
                      key={attachment.id}
                      className="flex items-center justify-between gap-2 rounded-md border bg-card px-2.5 py-1.5 text-xs"
                    >
                      <span className="min-w-0 flex-1 truncate">{attachment.original_name}</span>
                      <span className="shrink-0 text-muted-foreground">{formatBytes(attachment.size)}</span>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        aria-label={t('attachments.download')}
                        onClick={() => handleDownload(attachment)}
                      >
                        <Download aria-hidden="true" />
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        )}

        <DialogFooter>
          {email?.status === 'failed' && email.can.send ? (
            <Button type="button" onClick={() => void handleResend()} disabled={resend.isPending}>
              {resend.isPending ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
              {t('workOrderEmails.detail.resend')}
            </Button>
          ) : null}
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t('workOrderEmails.composer.cancel')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-medium text-muted-foreground">{label}</span>
      <span className="break-words">{value}</span>
    </div>
  )
}
