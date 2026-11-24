import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FilePlus2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DocumentRequestCard } from '@/features/document-requests/document-request-card'
import { DocumentRequestCreateDialog } from '@/features/document-requests/document-request-create-dialog'
import { useDocumentRequests } from '@/features/document-requests/use-document-requests'

interface DocumentRequestsSectionProps {
  workOrderId: number
}

/** "Richieste documenti" tab of the work order detail (spec 0428): requests sent to ArchivioSmart and their progress. */
export function DocumentRequestsSection({ workOrderId }: DocumentRequestsSectionProps) {
  const { t } = useTranslation()
  const [creating, setCreating] = useState(false)
  const query = useDocumentRequests(workOrderId)

  if (query.isError) {
    return (
      <div role="alert" className="flex items-center gap-2 text-sm text-destructive">
        {t('documentRequests.loadError')}
        <Button type="button" variant="secondary" size="sm" onClick={() => void query.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!query.data) {
    return <Skeleton className="h-24 w-full" />
  }

  const { requests, canCreate } = query.data

  return (
    <div className="flex flex-col gap-3 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">{t('documentRequests.subtitle')}</p>
        {canCreate ? (
          <Button type="button" size="sm" onClick={() => setCreating(true)}>
            <FilePlus2 aria-hidden="true" />
            {t('documentRequests.create.open')}
          </Button>
        ) : null}
      </div>
      {requests.length === 0 ? (
        <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
          {t('documentRequests.empty')}
        </p>
      ) : (
        requests.map((request) => <DocumentRequestCard key={request.id} request={request} />)
      )}
      {creating ? <DocumentRequestCreateDialog workOrderId={workOrderId} open onOpenChange={setCreating} /> : null}
    </div>
  )
}
