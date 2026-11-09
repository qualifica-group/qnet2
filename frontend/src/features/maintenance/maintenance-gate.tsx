import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useMaintenanceActive } from '@/api/maintenance-state'
import { Button } from '@/components/ui/button'
import { useMaintenanceRecovery } from '@/features/maintenance/use-maintenance-recovery'

function MaintenanceScreen() {
  const { t } = useTranslation('maintenance')
  const { isChecking, checkNow } = useMaintenanceRecovery()

  return (
    <div
      className="fixed inset-0 z-[2100] flex min-h-svh flex-col items-center justify-center gap-3 bg-background p-4 text-center"
      role="alert"
    >
      <h1 className="text-xl font-semibold tracking-tight">{t('title')}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{t('description')}</p>
      <Button size="sm" disabled={isChecking} onClick={checkNow}>
        {t('retry')}
      </Button>
    </div>
  )
}

/**
 * Covers the app during maintenance (spec 0376) instead of unmounting it, so an
 * open form keeps its data. The z-index sits above the boot splash.
 */
export function MaintenanceGate({ children }: { children: ReactNode }) {
  const isActive = useMaintenanceActive()

  return (
    <>
      <div className="contents" inert={isActive}>
        {children}
      </div>
      {isActive ? <MaintenanceScreen /> : null}
    </>
  )
}
