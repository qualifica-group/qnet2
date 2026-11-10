import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { MigrationStartOptions } from '@/features/migrations/types'
import { MigrationUpdateExistingField } from '@/features/migrations/migration-update-existing-field'
import { MigrationUpdatedSinceField } from '@/features/migrations/migration-updated-since-field'

interface MigrationImportStartProps {
  /** Receives the chosen start day (YYYY-MM-DD, or null for a full import) and the realign flag. */
  onStart: (options: MigrationStartOptions) => void
  /** Whether the source can realign already imported records (shows the checkbox). */
  supportsUpdateExisting: boolean
  isStarting: boolean
  startError: string | null
}

/**
 * The pre-import confirmation step: explains what the import does (creates
 * records in background, idempotent re-runs) and starts it on confirm. Shown
 * before any `MigrationRun` exists.
 */
export function MigrationImportStart({
  onStart,
  supportsUpdateExisting,
  isStarting,
  startError,
}: MigrationImportStartProps) {
  const { t } = useTranslation('migrations')
  const [updatedSince, setUpdatedSince] = useState('')
  const [updateExisting, setUpdateExisting] = useState(false)

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('import.confirmDescription')}</p>

      <MigrationUpdatedSinceField
        value={updatedSince}
        onChange={setUpdatedSince}
        error={startError}
      />

      {supportsUpdateExisting ? (
        <MigrationUpdateExistingField
          checked={updateExisting}
          onCheckedChange={setUpdateExisting}
          help={t('updateExisting.help')}
          disabled={isStarting}
        />
      ) : null}

      <div className="flex justify-end">
        <Button
          type="button"
          onClick={() =>
            onStart({
              updatedSince: updatedSince || null,
              updateExisting: supportsUpdateExisting && updateExisting,
            })
          }
          disabled={isStarting}
        >
          {isStarting ? t('import.starting') : t('import.start')}
        </Button>
      </div>
    </div>
  )
}
