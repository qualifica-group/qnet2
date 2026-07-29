import { useTranslation } from 'react-i18next'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
// Side effect: registers the `migrations` i18next namespace (see the module
// doc comment) before this dialog's own `t()` calls run.
import '@/features/migrations/i18n'
import { useMigrationImport } from '@/features/migrations/use-migration-import'
import { MigrationImportStart } from '@/features/migrations/migration-import-start'
import { MigrationImportProgress } from '@/features/migrations/migration-import-progress'

/** Narrow wizard: the sheet opens at this width until the user resizes it. */
const IMPORT_SHEET_DEFAULT_WIDTH = 512

export interface ImportDialogProps {
  /** Source key that selects the backend `MigrationSource` (`/migrations/{source}`). */
  source: string
  /** Human-readable source label, interpolated into the dialog title. */
  sourceLabel: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Two-phase import wizard for a single migration source (spec 0013).
 * Orchestration (start/poll) lives in `useMigrationImport`; this component
 * only routes between the confirm step and the progress/summary step based on
 * whether a `MigrationRun` has been created yet.
 */
export function ImportDialog({ source, sourceLabel, open, onOpenChange }: ImportDialogProps) {
  const { t } = useTranslation('migrations')
  const importState = useMigrationImport({ source })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      importState.reset()
    }
    onOpenChange(next)
  }

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent
        className="gap-0"
        defaultWidth={IMPORT_SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:migrations"
      >
        <SheetHeader>
          <SheetTitle>{t('import.title', { source: sourceLabel })}</SheetTitle>
          <SheetDescription>{t('import.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-4 overflow-auto p-4">
          {!importState.run ? (
            <MigrationImportStart
              onStart={importState.start}
              isStarting={importState.isStarting}
              startError={importState.startError}
            />
          ) : (
            <MigrationImportProgress
              run={importState.run}
              onClose={() => handleOpenChange(false)}
              isPollingStalled={importState.isPollingStalled}
              onRetryPolling={importState.retryPolling}
            />
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
