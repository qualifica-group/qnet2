import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { CompanyFilter } from '@/features/treasury-overview/company-filter'
import { TreasuryOverviewPanel } from '@/features/treasury-overview/treasury-overview-panel'
import { useTreasuryOverview } from '@/features/treasury-overview/use-treasury-overview'

/** Data part of the page, mounted only when the user can view it, so no call is made otherwise. */
function TreasuryOverviewContent() {
  const { t } = useTranslation()
  const [companyId, setCompanyId] = useState<number | null>(null)
  const { data, isError, isLoading } = useTreasuryOverview(companyId)

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <CompanyFilter value={companyId} onChange={setCompanyId} />
      {isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('treasuryOverview.loadError')}
        </p>
      ) : null}
      {isLoading ? <p className="text-sm text-muted-foreground">{t('treasuryOverview.loading')}</p> : null}
      {data ? <TreasuryOverviewPanel data={data} /> : null}
    </div>
  )
}

/** Treasury overview page (Accounting > Finance, spec 0362), gated by `treasury-movements.view`. */
export default function TreasuryOverviewPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="treasury-movements.view"
      fallback={<p className="text-sm text-muted-foreground">{t('treasuryOverview.forbidden')}</p>}
    >
      <TreasuryOverviewContent />
    </Can>
  )
}
