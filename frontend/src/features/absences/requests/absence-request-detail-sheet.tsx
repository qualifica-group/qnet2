import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import { AbsenceRequestActionsBar } from '@/features/absences/requests/absence-request-actions'
import { AbsenceRequestDetailBody } from '@/features/absences/requests/absence-request-detail-body'
import { useAbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import { useAbsenceRequestDetail } from '@/features/absences/requests/use-absence-request-detail'
import type { AbsenceRequest } from '@/features/absences/types'

const NOT_FOUND_STATUS = 404

interface AbsenceRequestDetailSheetProps {
  /** Request to show; `null` keeps the sheet closed. */
  requestId: number | null
  onClose: () => void
  onEdit: (request: AbsenceRequest) => void
  refreshGrid?: () => void
}

interface DetailContentProps extends Omit<AbsenceRequestDetailSheetProps, 'requestId'> {
  requestId: number
}

function DetailContent({ requestId, onClose, onEdit, refreshGrid }: DetailContentProps) {
  const { t } = useTranslation()
  const { data: request, isPending, error } = useAbsenceRequestDetail(requestId)
  const actions = useAbsenceRequestActions(requestId, refreshGrid, onClose)

  if (isPending) {
    return <Skeleton className="m-4 h-40" />
  }
  if (!request) {
    const missing = axios.isAxiosError(error) && error.response?.status === NOT_FOUND_STATUS
    return (
      <p role="alert" className="p-4 text-sm text-destructive">
        {t(missing ? 'absences.requests.detail.notFound' : 'absences.requests.detail.loadError')}
      </p>
    )
  }

  return (
    <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-4">
      <AbsenceRequestActionsBar request={request} actions={actions} onEdit={onEdit} />
      <AbsenceRequestDetailBody request={request} actions={actions} />
      {request.can.approve || request.can.reject ? <AbsenceBalancesCard userId={request.user.id} /> : null}
    </div>
  )
}

/** Detail Sheet of a request (grid row click or the `?request={id}` deep link of the notifications). */
export function AbsenceRequestDetailSheet({ requestId, onClose, onEdit, refreshGrid }: AbsenceRequestDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={requestId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:absence-requests-detail">
        <SheetHeader>
          <SheetTitle>{t('absences.requests.detail.title')}</SheetTitle>
          <SheetDescription>{t('absences.requests.detail.description')}</SheetDescription>
        </SheetHeader>
        {requestId !== null ? (
          <DetailContent requestId={requestId} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
