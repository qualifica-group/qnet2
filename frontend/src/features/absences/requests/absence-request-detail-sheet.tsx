import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader } from '@/components/detail/record-panel'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import { AbsenceInsightPanel } from '@/features/absences/requests/absence-insight-panel'
import { AbsenceRequestActionsBar } from '@/features/absences/requests/absence-request-actions'
import { AbsenceRequestDetailBody } from '@/features/absences/requests/absence-request-detail-body'
import { AbsenceRequestFlagBadges } from '@/features/absences/requests/absence-request-flag-badges'
import { useAbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import { useAbsenceRequestDetail } from '@/features/absences/requests/use-absence-request-detail'
import { useAbsenceRequestInsight } from '@/features/absences/requests/use-absence-request-insight'
import type { AbsenceRequest } from '@/features/absences/types'
import { cn } from '@/lib/utils'

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
  const decides = Boolean(request?.can.approve || request?.can.reject)
  const insight = useAbsenceRequestInsight(requestId, decides)

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
            title={
              <span className="inline-flex max-w-full items-center gap-1.5">
                <span
                  aria-hidden="true"
                  className={cn('size-2.5 shrink-0 rounded-full', absenceTypeDotClass(request.absence_type.color))}
                />
                <span className="truncate">{request.absence_type.name}</span>
              </span>
            }
            subtitle={request.user.name}
            badges={<AbsenceRequestFlagBadges request={request} />}
            actions={<AbsenceRequestActionsBar request={request} actions={actions} onEdit={onEdit} />}
          />
        }
      >
        <AbsenceRequestDetailBody request={request} actions={actions} />
      </RecordBody>
      {decides ? (
        <AbsenceInsightPanel
          insight={insight.data}
          isPending={insight.isPending}
          isError={insight.isError}
          variant="context"
        />
      ) : null}
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
