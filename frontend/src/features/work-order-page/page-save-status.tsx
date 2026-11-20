import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { PageSaveStatus } from '@/features/work-order-page/use-page-autosave'
import type { WorkOrderPagePayload } from '@/features/work-order-page/types'

interface PageSaveIndicatorProps {
  status: PageSaveStatus
  onRetry: () => void
}

/** "Salvataggio... / Salvato / Errore, riprova" (spec 0420 AC-022). Conflict has its own banner. */
export function PageSaveIndicator({ status, onRetry }: PageSaveIndicatorProps) {
  const { t } = useTranslation()

  return (
    <div role="status" aria-live="polite" className="flex min-h-6 items-center gap-1.5 text-xs text-muted-foreground">
      {status === 'saving' ? t('workOrderPage.save.saving') : null}
      {status === 'saved' ? t('workOrderPage.save.saved') : null}
      {status === 'error' ? (
        <>
          <span className="text-destructive">{t('workOrderPage.save.error')}</span>
          <Button type="button" size="xs" variant="secondary" onClick={onRetry}>
            {t('workOrderPage.save.retry')}
          </Button>
        </>
      ) : null}
    </div>
  )
}

interface PageConflictBannerProps {
  conflict: WorkOrderPagePayload
  onReload: () => void
}

/** Shown while saving is suspended after a 409 version conflict (D-3). Local edits stay visible and copyable. */
export function PageConflictBanner({ conflict, onReload }: PageConflictBannerProps) {
  const { t } = useTranslation()
  const author = conflict.updated_by?.name

  return (
    <div role="alert" className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-warning bg-warning/10 px-2.5 py-1.5 text-sm">
      <span>{author ? t('workOrderPage.conflict.by', { name: author }) : t('workOrderPage.conflict.generic')}</span>
      <Button type="button" size="xs" onClick={onReload}>
        {t('workOrderPage.conflict.reload')}
      </Button>
    </div>
  )
}
