import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
// Side effect: registers the `migrations` i18next namespace before this
// dialog's own `t()` calls run.
import '@/features/migrations/i18n'
import { MassImportProgress } from '@/features/migrations/mass-import-progress'
import { useMassMigration } from '@/features/migrations/use-mass-migration'
import { useMigrationPlan } from '@/features/migrations/use-migration-plan'

/** The confirm step opens this wide until the user resizes it. */
const MASS_IMPORT_SHEET_DEFAULT_WIDTH = 512

interface MassImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * "Import all" wizard (spec 0046): confirm the ordered list of enabled sources
 * (from the saved plan), then start the aggregate run and watch per-source
 * progress. Orchestration lives in `useMassMigration`; this component only
 * routes between the confirm step and the progress step.
 */
export function MassImportDialog({ open, onOpenChange }: MassImportDialogProps) {
  const { t } = useTranslation('migrations')
  const massState = useMassMigration()
  const { plan } = useMigrationPlan()

  const enabledSources = (plan?.sources ?? []).filter((item) => item.enabled)
  const sourceLabel = (source: string, fallback: string) =>
    t(`sources.${source}`, { defaultValue: fallback })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      massState.reset()
    }
    onOpenChange(next)
  }

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent
        className="gap-0"
        defaultWidth={MASS_IMPORT_SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:mass-migration"
      >
        <SheetHeader>
          <SheetTitle>{t('massImport.title')}</SheetTitle>
          <SheetDescription>{t('massImport.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-4 overflow-auto p-4">
          {!massState.run ? (
            <div className="flex flex-col gap-4">
              <p className="text-sm text-muted-foreground">{t('massImport.confirmDescription')}</p>

              {enabledSources.length === 0 ? (
                <p className="text-sm text-destructive" role="alert">
                  {t('massImport.empty')}
                </p>
              ) : (
                <ol className="list-decimal space-y-1 pl-5 text-sm">
                  {enabledSources.map((item) => (
                    <li key={item.source} className="truncate">
                      {sourceLabel(item.source, item.label)}
                    </li>
                  ))}
                </ol>
              )}

              {massState.startError ? (
                <p className="text-sm text-destructive" role="alert">
                  {massState.startError}
                </p>
              ) : null}

              <div className="flex justify-end">
                <Button
                  type="button"
                  onClick={() => massState.start()}
                  disabled={massState.isStarting || enabledSources.length === 0}
                >
                  {massState.isStarting ? t('massImport.starting') : t('massImport.start')}
                </Button>
              </div>
            </div>
          ) : (
            <MassImportProgress
              run={massState.run}
              onClose={() => handleOpenChange(false)}
              isPollingStalled={massState.isPollingStalled}
              onRetryPolling={massState.retryPolling}
            />
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
