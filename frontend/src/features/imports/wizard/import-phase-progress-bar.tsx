import { useTranslation } from 'react-i18next'
import { Progress } from '@/components/ui/progress'
import type { ImportPhaseProgress } from '@/features/imports/wizard/types'

export interface ImportPhaseProgressBarProps {
  /** Caption of the running phase, used as the progressbar's accessible name. */
  label: string
  progress: ImportPhaseProgress
}

/**
 * Determinate progress of a running import phase (spec 0137): the bar plus
 * the "X of Y rows · Z%" caption. The caption opts out of the surrounding
 * `role="status"` live region, otherwise every poll would be read aloud; the
 * progressbar already exposes the value.
 */
export function ImportPhaseProgressBar({ label, progress }: ImportPhaseProgressBarProps) {
  const { t } = useTranslation('importWizard')
  const percent = Math.floor((progress.processed / progress.total) * 100)

  return (
    <div className="flex w-full max-w-sm flex-col items-center gap-1">
      <Progress value={percent} className="w-full" aria-label={label} />
      <p className="text-xs tabular-nums text-muted-foreground" aria-live="off">
        {t('background.progress', { processed: progress.processed, total: progress.total, percent })}
      </p>
    </div>
  )
}
