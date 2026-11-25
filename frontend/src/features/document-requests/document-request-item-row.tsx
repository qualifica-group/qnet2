import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Check, Download, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { downloadDocumentRequestFile } from '@/features/document-requests/api'
import { DocumentRequestReviewDialog } from '@/features/document-requests/document-request-review-dialog'
import { itemReviewActions } from '@/features/document-requests/document-request-status'
import type {
  DocumentRequest,
  DocumentRequestDocument,
  DocumentRequestItem,
  DocumentRequestReviewDecision,
} from '@/features/document-requests/types'
import { formatBytes } from '@/features/attachments/format-bytes'
import { saveBlob } from '@/lib/download'

const OK_STATUSES: readonly string[] = ['ok', 'approved_manually']
const KO_STATUSES: readonly string[] = ['ko', 'rejected_manually']

function statusVariant(status: string) {
  return OK_STATUSES.includes(status) ? 'default' : KO_STATUSES.includes(status) ? 'destructive' : 'secondary'
}

function DocumentFile({ requestId, document }: { requestId: number; document: DocumentRequestDocument }) {
  const { t } = useTranslation()
  const [downloading, setDownloading] = useState(false)

  async function download() {
    setDownloading(true)
    try {
      saveBlob(await downloadDocumentRequestFile(requestId, document.id), document.original_name)
    } catch {
      toast.error(t('documentRequests.download.error'))
    } finally {
      setDownloading(false)
    }
  }

  return (
    <li className="flex flex-wrap items-center gap-2 text-muted-foreground">
      <span className="min-w-0 flex-1 break-all">
        {document.original_name}
        {document.size_bytes !== null ? ` (${formatBytes(document.size_bytes)})` : ''}
      </span>
      <Button
        type="button"
        size="sm"
        variant="secondary"
        disabled={downloading}
        aria-label={t('documentRequests.download.label', { name: document.original_name })}
        onClick={() => void download()}
      >
        <Download aria-hidden="true" />
        {t('documentRequests.download.button')}
      </Button>
    </li>
  )
}

/** One item: status, reason, its files (each downloadable) and the manual review actions (spec 0429). */
export function DocumentRequestItemRow({ request, item }: { request: DocumentRequest; item: DocumentRequestItem }) {
  const { t } = useTranslation()
  const [decision, setDecision] = useState<DocumentRequestReviewDecision | null>(null)
  const allowed = itemReviewActions(request, item)

  return (
    <li className="flex flex-col gap-1 px-3 py-1.5 text-xs">
      <div className="flex flex-wrap items-center gap-2">
        <span className="min-w-0 flex-1 break-words font-medium">
          {item.name}
          {item.is_required ? null : (
            <span className="ml-1 font-normal text-muted-foreground">({t('documentRequests.items.optional')})</span>
          )}
        </span>
        <span className="text-muted-foreground">{t('documentRequests.items.files', { count: item.documents_count })}</span>
        <Badge variant={statusVariant(item.status)}>
          {t(`documentRequests.itemStatus.${item.status}`, { defaultValue: item.status })}
        </Badge>
      </div>
      {item.status_reason ? (
        <p className="break-words text-muted-foreground">
          {t('documentRequests.items.reason')}: {item.status_reason}
        </p>
      ) : null}
      {item.documents.length > 0 ? (
        <ul className="flex flex-col gap-1">
          {item.documents.map((document) => (
            <DocumentFile key={document.id} requestId={request.id} document={document} />
          ))}
        </ul>
      ) : null}
      {allowed.approve || allowed.reject ? (
        <div className="flex flex-wrap gap-1.5">
          {allowed.approve ? (
            <Button type="button" size="sm" variant="secondary" onClick={() => setDecision('approve')}>
              <Check aria-hidden="true" />
              {t('documentRequests.review.approve')}
            </Button>
          ) : null}
          {allowed.reject ? (
            <Button type="button" size="sm" variant="destructive" onClick={() => setDecision('reject')}>
              <X aria-hidden="true" />
              {t('documentRequests.review.reject')}
            </Button>
          ) : null}
        </div>
      ) : null}
      {decision ? (
        <DocumentRequestReviewDialog
          request={request}
          item={item}
          decision={decision}
          open
          onOpenChange={(open) => (open ? undefined : setDecision(null))}
        />
      ) : null}
    </li>
  )
}
