import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader } from '@/components/detail/record-panel'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import { AbsenceRequestActionsBar } from '@/features/absences/requests/absence-request-actions'
import { AbsenceRequestDetailBody } from '@/features/absences/requests/absence-request-detail-body'
import { AbsenceRequestStatusBadge } from '@/features/absences/requests/absence-request-status-badge'
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
    return <DetailLoading />
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
    <RecordCanvas>
      <RecordBody
        header={
          <RecordCardHeader
            media={<DetailMonogram name={request.user.name} />}
            title={request.absence_type.name}
            subtitle={request.user.name}
            badges={<AbsenceRequestStatusBadge status={request.status} />}
            actions={<AbsenceRequestActionsBar request={request} actions={actions} onEdit={onEdit} />}
          />
        }
      >
        <AbsenceRequestDetailBody request={request} actions={actions} />
      </RecordBody>
      {request.can.approve || request.can.reject ? <AbsenceBalancesCard userId={request.user.id} /> : null}
    </RecordCanvas>
  )
}

/** Detail Sheet of a request on the record kit (spec 0287), opened by a grid row or the `?request={id}` deep link. */
export function AbsenceRequestDetailSheet({ requestId, onClose, onEdit, refreshGrid }: AbsenceRequestDetailSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={requestId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:absence-requests-detail">
        <SheetToolbar closeLabel={t('absences.requests.detail.close')} />
        <SheetHeader className="sr-only">
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
