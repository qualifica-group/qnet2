import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useFormalabActions } from '@/integrations/formalab/use-formalab-actions'
import { useFormalabSyncState } from '@/integrations/formalab/use-formalab-sync-state'

/**
 * "Sincronizza ora" of the status and classes pages: disabled while a sync is
 * queued or running, so a second one is never requested (the server refuses
 * it anyway with 409).
 */
export function SyncButton() {
  const { t } = useTranslation('formalab')
  const { data } = useFormalabSyncState()
  const { sync } = useFormalabActions()
  const running = data?.sync_in_progress === true

  return (
    <Button size="sm" variant="secondary" disabled={sync.isPending || running} onClick={() => sync.mutate()}>
      <RefreshCw className={cn('size-3.5', running && 'animate-spin motion-reduce:animate-none')} aria-hidden />
      {running ? t('actions.syncRunning') : t('actions.sync')}
    </Button>
  )
}
