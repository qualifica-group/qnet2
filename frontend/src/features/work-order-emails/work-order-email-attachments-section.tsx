import { useRef, useState, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { FileText, Loader2, Trash2, Upload } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { formatBytes } from '@/features/attachments/format-bytes'
import { useWorkOrderEmailAttachments } from '@/features/work-order-emails/use-work-order-email-attachments'
import { WorkOrderEmailDocumentsPickerDialog } from '@/features/work-order-emails/work-order-email-documents-picker-dialog'
import type { ComposeContext, OutboundEmail } from '@/features/work-order-emails/types'

const BYTES_PER_KB = 1024

interface WorkOrderEmailAttachmentsSectionProps {
  workOrderId: number
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
export function WorkOrderEmailAttachmentsSection({
  workOrderId,
  emailId,
  email,
  composeContext,
  disabled,
}: WorkOrderEmailAttachmentsSectionProps) {
  const { t } = useTranslation()
  const { upload, importAttachments, remove } = useWorkOrderEmailAttachments(workOrderId, emailId)
  const [documentsDialogOpen, setDocumentsDialogOpen] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const isBusy = upload.isPending || importAttachments.isPending || remove.isPending || disabled

  const maxTotalBytes = (composeContext?.max_total_attachments_kb ?? 0) * BYTES_PER_KB
  const overLimit = maxTotalBytes > 0 && email.attachments_total_size > maxTotalBytes

  const handleFiles = async (fileList: FileList | null) => {
    const files = Array.from(fileList ?? [])
    for (const file of files) {
      try {
        // Sequential on purpose (mirrors `useAttachments`): the endpoint takes
        // one file per request, and the server checks the running total on
        // every single upload — a parallel burst would race that check.
        await upload.mutateAsync(file)
      } catch {
        toast.error(t('workOrderEmails.composer.attachments.uploadError'))
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
      toast.error(t('workOrderEmails.composer.attachments.importError'))
    }
  }

  const handleImportBundle = async (documentBundleId: number) => {
    try {
      await importAttachments.mutateAsync({ source: 'document_bundle', document_bundle_id: documentBundleId })
    } catch {
      toast.error(t('workOrderEmails.composer.attachments.importError'))
    }
  }

  const handleImportQuotePdf = async () => {
    try {
      await importAttachments.mutateAsync({ source: 'quote_pdf' })
    } catch {
      toast.error(t('workOrderEmails.composer.attachments.importError'))
    }
  }

  const handleRemove = async (attachmentId: number) => {
    try {
      await remove.mutateAsync(attachmentId)
    } catch {
      toast.error(t('workOrderEmails.composer.attachments.removeError'))
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-medium text-foreground">{t('workOrderEmails.composer.attachments.title')}</p>
        {composeContext ? (
          <span className={cn('text-[11px]', overLimit ? 'font-medium text-destructive' : 'text-muted-foreground')}>
            {t('workOrderEmails.composer.attachments.totalSize', {
              used: formatBytes(email.attachments_total_size),
              limit: formatBytes(maxTotalBytes),
            })}
          </span>
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
          {t('workOrderEmails.composer.attachments.upload')}
        </Button>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setDocumentsDialogOpen(true)}
          disabled={isBusy || !composeContext || composeContext.documents.length === 0}
        >
          {t('workOrderEmails.composer.attachments.fromDocuments')}
        </Button>

        <div className="w-48">
          <AsyncPaginatedSelect
            resource="document-bundles"
            value={null}
            onChange={(id) => {
              if (id !== null) {
                void handleImportBundle(id)
              }
            }}
            labels={{
              placeholder: t('workOrderEmails.composer.attachments.fromDocumentBundle'),
              searchPlaceholder: t('workOrderEmails.composer.templateSearch'),
              empty: t('workOrderEmails.composer.attachments.documentBundleEmpty'),
              error: t('workOrderEmails.composer.templateError'),
              retry: t('common.retry'),
              clearLabel: t('workOrderEmails.composer.attachments.remove'),
              triggerLabel: t('workOrderEmails.composer.attachments.fromDocumentBundle'),
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
          title={!composeContext?.quote_pdf_available ? t('workOrderEmails.composer.attachments.quotePdfUnavailable') : undefined}
        >
          <FileText className="size-3.5" aria-hidden="true" />
          {t('workOrderEmails.composer.attachments.quotePdf')}
        </Button>
      </div>

      {email.attachments.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('workOrderEmails.composer.attachments.empty')}</p>
      ) : (
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
                aria-label={t('workOrderEmails.composer.attachments.remove')}
                onClick={() => void handleRemove(attachment.id)}
                disabled={isBusy}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </li>
          ))}
        </ul>
      )}

      <WorkOrderEmailDocumentsPickerDialog
        open={documentsDialogOpen}
        onOpenChange={setDocumentsDialogOpen}
        documents={composeContext?.documents ?? []}
        onImport={handleImportDocuments}
        isImporting={importAttachments.isPending}
      />
    </div>
  )
}
