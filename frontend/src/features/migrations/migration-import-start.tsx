import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { MigrationUpdatedSinceField } from '@/features/migrations/migration-updated-since-field'

interface MigrationImportStartProps {
  /** Receives the chosen start day (YYYY-MM-DD) or null for a full import. */
  onStart: (updatedSince: string | null) => void
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
  isStarting,
  startError,
}: MigrationImportStartProps) {
  const { t } = useTranslation('migrations')
  const [updatedSince, setUpdatedSince] = useState('')

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('import.confirmDescription')}</p>

      <MigrationUpdatedSinceField
        value={updatedSince}
        onChange={setUpdatedSince}
        error={startError}
      />

      <div className="flex justify-end">
        <Button type="button" onClick={() => onStart(updatedSince || null)} disabled={isStarting}>
          {isStarting ? t('import.starting') : t('import.start')}
        </Button>
      </div>
    </div>
  )
}
