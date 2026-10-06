import { useId, useRef, useState, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { FileText, FolderOpen, Loader2, Paperclip, Trash2, Upload } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { formatBytes } from '@/features/attachments/format-bytes'
import { useOutboundEmailAttachments } from '@/features/outbound-emails/use-outbound-email-attachments'
import { OutboundEmailAttachmentTile } from '@/features/outbound-emails/outbound-email-attachment-tile'
import { OutboundEmailDocumentsPickerDialog } from '@/features/outbound-emails/outbound-email-documents-picker-dialog'
import type { EmailOwnerRef, ComposeContext, OutboundEmail } from '@/features/outbound-emails/types'

const BYTES_PER_KB = 1024

interface OutboundEmailAttachmentsSectionProps {
  owner: EmailOwnerRef
  emailId: number
  email: OutboundEmail
  composeContext: ComposeContext | undefined
  disabled: boolean
}

/**
 * The composer's attachments panel (D-7, AC-021): current file list + the
 * four sources side by side (upload, commessa/anagrafica documents, a
 * Modello documenti, the quote PDF). Every action mutation returns the whole
 * updated email — this component only ever renders `email.attachments`, it
 * never assembles the list itself.
 */
export function OutboundEmailAttachmentsSection({
  owner,
  emailId,
  email,
  composeContext,
  disabled,
}: OutboundEmailAttachmentsSectionProps) {
  const { t } = useTranslation()
  const { upload, importAttachments, remove } = useOutboundEmailAttachments(owner, emailId)
  const [documentsDialogOpen, setDocumentsDialogOpen] = useState(false)
  const titleId = useId()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const isBusy = upload.isPending || importAttachments.isPending || remove.isPending || disabled

  const maxTotalBytes = (composeContext?.max_total_attachments_kb ?? 0) * BYTES_PER_KB
  const overLimit = maxTotalBytes > 0 && email.attachments_total_size > maxTotalBytes
  const usagePercent = maxTotalBytes > 0 ? Math.min(100, (email.attachments_total_size / maxTotalBytes) * 100) : 0
  const usageLabel = t('outboundEmails.composer.attachments.totalSize', {
    used: formatBytes(email.attachments_total_size),
    limit: formatBytes(maxTotalBytes),
  })

  const handleFiles = async (fileList: FileList | null) => {
    const files = Array.from(fileList ?? [])
    for (const file of files) {
      try {
        // Sequential on purpose (mirrors `useAttachments`): the endpoint takes
        // one file per request, and the server checks the running total on
        // every single upload — a parallel burst would race that check.
        await upload.mutateAsync(file)
      } catch {
        toast.error(t('outboundEmails.composer.attachments.uploadError'))
      }
    }
  }

  const handleFileInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    void handleFiles(event.target.files)
    event.target.value = ''
  }

  const handleImportDocuments = async (attachmentIds: number[]) => {
    try {
      await importAttachments.mutateAsync({ source: 'documents', attachment_ids: attachmentIds })
    } catch {
      toast.error(t('outboundEmails.composer.attachments.importError'))
    }
  }

  const handleImportBundle = async (documentBundleId: number) => {
    try {
      await importAttachments.mutateAsync({ source: 'document_bundle', document_bundle_id: documentBundleId })
    } catch {
      toast.error(t('outboundEmails.composer.attachments.importError'))
    }
  }

  const handleImportQuotePdf = async () => {
    try {
      await importAttachments.mutateAsync({ source: 'quote_pdf' })
    } catch {
      toast.error(t('outboundEmails.composer.attachments.importError'))
    }
  }

  const handleRemove = async (attachmentId: number) => {
    try {
      await remove.mutateAsync(attachmentId)
    } catch {
      toast.error(t('outboundEmails.composer.attachments.removeError'))
    }
  }

  return (
    <section
      aria-labelledby={titleId}
      className="flex flex-col gap-3 rounded-lg border bg-surface p-3"
    >
      <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
        <h3 id={titleId} className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
          <Paperclip className="size-3.5 text-muted-foreground" aria-hidden="true" />
          {t('outboundEmails.composer.attachments.title')}
          {email.attachments.length > 0 ? (
            <Badge variant="secondary" className="px-1.5 py-0 text-[11px]">
              {email.attachments.length}
            </Badge>
          ) : null}
        </h3>
        {composeContext ? (
          <div className="flex items-center gap-2">
            <span className={cn('text-[11px] tabular-nums', overLimit ? 'font-medium text-destructive' : 'text-muted-foreground')}>
              {usageLabel}
            </span>
            <Progress
              value={usagePercent}
              size="xs"
              aria-label={usageLabel}
              className="w-20"
              indicatorClassName={overLimit ? 'bg-destructive' : undefined}
            />
          </div>
        ) : null}
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        <input
          ref={fileInputRef}
          type="file"
          multiple
          className="sr-only"
          onChange={handleFileInputChange}
          disabled={isBusy}
        />
        <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()} disabled={isBusy}>
          {upload.isPending ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : <Upload className="size-3.5" aria-hidden="true" />}
          {t('outboundEmails.composer.attachments.upload')}
        </Button>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setDocumentsDialogOpen(true)}
          disabled={isBusy || !composeContext || composeContext.documents.length === 0}
        >
          <FolderOpen className="size-3.5" aria-hidden="true" />
          {t('outboundEmails.composer.attachments.fromDocuments')}
        </Button>

        <div className="w-full sm:w-52">
          <AsyncPaginatedSelect
            resource="document-bundles"
            value={null}
            onChange={(id) => {
              if (id !== null) {
                void handleImportBundle(id)
              }
            }}
            labels={{
              placeholder: t('outboundEmails.composer.attachments.fromDocumentBundle'),
              searchPlaceholder: t('outboundEmails.composer.templateSearch'),
              empty: t('outboundEmails.composer.attachments.documentBundleEmpty'),
              error: t('outboundEmails.composer.templateError'),
              retry: t('common.retry'),
              clearLabel: t('outboundEmails.composer.attachments.remove'),
              triggerLabel: t('outboundEmails.composer.attachments.fromDocumentBundle'),
            }}
            disabled={isBusy}
          />
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => void handleImportQuotePdf()}
          disabled={isBusy || !composeContext?.quote_pdf_available}
          title={!composeContext?.quote_pdf_available ? t('outboundEmails.composer.attachments.quotePdfUnavailable') : undefined}
        >
          <FileText className="size-3.5" aria-hidden="true" />
          {t('outboundEmails.composer.attachments.quotePdf')}
        </Button>
      </div>

      {email.attachments.length === 0 ? (
        <div className="flex items-center justify-center gap-2 rounded-lg border border-dashed border-muted-foreground/30 px-3 py-4 text-xs text-muted-foreground">
          <Paperclip className="size-3.5" aria-hidden="true" />
          {t('outboundEmails.composer.attachments.empty')}
        </div>
      ) : (
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
                  className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                  aria-label={t('outboundEmails.composer.attachments.remove')}
                  onClick={() => void handleRemove(attachment.id)}
                  disabled={isBusy}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              }
            />
          ))}
        </ul>
      )}

      <OutboundEmailDocumentsPickerDialog
        open={documentsDialogOpen}
        onOpenChange={setDocumentsDialogOpen}
        documents={composeContext?.documents ?? []}
        onImport={handleImportDocuments}
        isImporting={importAttachments.isPending}
      />
    </section>
  )
}
