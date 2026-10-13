import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { CommissionSettlementDetailView } from '@/features/commission-settlements/commission-settlement-detail'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

const LIST_PATH = '/commission-settlements'
const NO_CHANGE = () => undefined

/**
 * Dedicated page of one settlement (target of the links in a run's outcome):
 * back-to-list plus the same detail view the list opens in a sheet.
 */
export default function CommissionSettlementDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const settlementId = parseEntityId(id)

  if (settlementId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission="commission-settlements.view"
      fallback={<p className="text-sm text-muted-foreground">{t('commissionSettlements.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <Button variant="outline" className="bg-card" asChild>
              <Link to={LIST_PATH}>
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
          }
        />
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <CommissionSettlementDetailView
            settlementId={settlementId}
            onChanged={NO_CHANGE}
            onDeleted={() => void navigate(LIST_PATH)}
          />
        </div>
      </div>
    </Can>
  )
}
