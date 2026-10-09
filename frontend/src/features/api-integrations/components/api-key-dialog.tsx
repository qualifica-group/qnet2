import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Copy, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

export interface ApiKeyReveal {
  clientName: string
  plainTextKey: string
  rotated: boolean
}

interface ApiKeyDialogProps {
  /** The key lives only in the caller's state: `null` unmounts it from the DOM. */
  reveal: ApiKeyReveal | null
  onClose: () => void
}

/**
 * One-time display of an API key (creation and rotation share it). Closing is
 * explicit only (no outside click), so the key is not lost by a stray click.
 */
export function ApiKeyDialog({ reveal, onClose }: ApiKeyDialogProps) {
  return reveal ? <ApiKeyDialogContent reveal={reveal} onClose={onClose} /> : null
}

function ApiKeyDialogContent({ reveal, onClose }: { reveal: ApiKeyReveal; onClose: () => void }) {
  const { t } = useTranslation()
  const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle')

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(reveal.plainTextKey)
      setCopyState('copied')
    } catch {
      setCopyState('failed')
    }
  }

  const titleKey = reveal.rotated ? 'apiIntegrations.key.rotatedTitle' : 'apiIntegrations.key.createdTitle'

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent
        size="sm"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{t(titleKey, { name: reveal.clientName })}</DialogTitle>
          <DialogDescription className="flex items-start gap-2 text-foreground">
            <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden />
            {t('apiIntegrations.key.warning')}
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-2">
          <p className="text-xs font-medium">{t('apiIntegrations.key.label')}</p>
          <pre
            aria-label={t('apiIntegrations.key.label')}
            className="overflow-x-auto rounded-md border border-border bg-surface p-3 font-mono text-xs break-all whitespace-pre-wrap"
          >
            {reveal.plainTextKey}
          </pre>
          <div className="flex items-center gap-2">
            <Button type="button" size="sm" variant="secondary" onClick={() => void copy()}>
              {copyState === 'copied' ? (
                <Check className="size-3.5" aria-hidden />
              ) : (
                <Copy className="size-3.5" aria-hidden />
              )}
              {copyState === 'copied' ? t('apiIntegrations.key.copied') : t('apiIntegrations.key.copy')}
            </Button>
            <span role="status" className="text-xs text-destructive">
              {copyState === 'failed' ? t('apiIntegrations.key.copyError') : null}
            </span>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" onClick={onClose}>
            {t('apiIntegrations.key.close')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
