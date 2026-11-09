import { useTranslation } from 'react-i18next'

/**
 * `/dashboard` page, under construction (user decision 2026-11-09): the blocks are
 * not mounted so the home issues no data request, because their aggregates
 * saturated production at peak time.
 */
export function DashboardScreen() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-1 flex-col gap-6">
      <div className="flex flex-col items-center gap-1 rounded-lg border border-dashed bg-card px-4 py-10 text-center">
        <p className="text-sm font-medium">{t('dashboard.underConstruction.title')}</p>
        <p className="text-sm text-muted-foreground">{t('dashboard.underConstruction.description')}</p>
      </div>
    </div>
  )
}
