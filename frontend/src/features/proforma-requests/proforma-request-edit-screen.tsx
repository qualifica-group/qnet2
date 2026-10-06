import { useTranslation } from 'react-i18next'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProformaRequest, proformaRequestDetailQueryKey } from '@/features/proforma-requests/api'
import { ProformaRequestNoteForm } from '@/features/proforma-requests/proforma-request-note-form'

interface ProformaRequestEditScreenProps {
  id: number
  onSuccess: () => void
  onCancel: () => void
}

/**
 * Fetches the fresh record before mounting the note form, so the edit starts
 * from authoritative values. Shared by the registry form screen and the
 * "update" row action's dialog.
 */
export function ProformaRequestEditScreen({ id, onSuccess, onCancel }: ProformaRequestEditScreenProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = useEntityDetail(proformaRequestDetailQueryKey(id), () =>
    fetchProformaRequest(id),
  )

  if (isError) {
    return (
      <DetailError
        message={t('proformaRequests.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }
  if (isLoading || !data) {
    return <DetailLoading />
  }
  return <ProformaRequestNoteForm proformaRequest={data} onSuccess={onSuccess} onCancel={onCancel} />
}
