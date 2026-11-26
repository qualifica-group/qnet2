import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'
import { useAiPreviewDecision } from '@/features/ai-assistant/hooks/use-ai-preview-decision'
import { useNow } from '@/features/ai-assistant/hooks/use-now'
import type { AiPreview, AiTurn } from '@/features/ai-assistant/types'

const MS_PER_SECOND = 1000
const SECONDS_PER_MINUTE = 60
/** The server answers 404/409 when the preview is no longer this turn's open one. */
const CLOSED_STATUSES: readonly number[] = [404, 409]
function formatRemaining(ms: number): string {
  const total = Math.ceil(ms / MS_PER_SECOND)
  const minutes = Math.floor(total / SECONDS_PER_MINUTE)
  return `${String(minutes).padStart(2, '0')}:${String(total % SECONDS_PER_MINUTE).padStart(2, '0')}`
}

interface Props {
  turn: AiTurn
  preview: AiPreview
}

/**
 * The write the assistant prepared, as the connector returned it (0433, D-19): its Markdown
 * (record, before and after, effects) through AiMarkdown (no raw HTML, no links) and the time left.
 * The model text of the turn never reaches this card. Only the preview id goes back on a click.
 */
export function AiPreviewCard({ turn, preview }: Props) {
  const { t } = useTranslation()
  const { decide, isLocked, error } = useAiPreviewDecision(turn.conversation_id, turn.id, preview.id)
  const isOpen = preview.state === 'open'
  const now = useNow(isOpen)
  const remaining = new Date(preview.expires_at).getTime() - now
  const isExpired = !isOpen || remaining <= 0
  const disabled = isLocked || isExpired

  const failure = error
    ? axios.isAxiosError(error) && CLOSED_STATUSES.includes(error.response?.status ?? 0)
      ? t('aiAssistant.preview.errors.closed')
      : t('aiAssistant.preview.errors.generic')
    : null

  return (
    <div role="group" aria-label={t('aiAssistant.preview.label')} className="mt-1 flex flex-col gap-2 rounded-md border border-border bg-background p-2.5 text-xs">
      <AiMarkdown content={preview.content} />
      <p role="timer" className="text-muted-foreground">
        {isExpired ? t('aiAssistant.preview.expired') : t('aiAssistant.preview.expiresIn', { time: formatRemaining(remaining) })}
      </p>
      {isOpen && !preview.confirmable ? (
        <p className="text-muted-foreground">{t('aiAssistant.preview.notConfirmable')}</p>
      ) : null}
      {failure ? (
        <p role="alert" className="text-destructive">
          {failure}
        </p>
      ) : null}
      <div className="flex gap-2">
        <Button type="button" size="xs" disabled={disabled || !preview.confirmable} onClick={() => decide('confirm')}>
          {t('aiAssistant.preview.confirm')}
        </Button>
        <Button type="button" size="xs" variant="secondary" disabled={disabled} onClick={() => decide('cancel')}>
          {t('aiAssistant.preview.cancel')}
        </Button>
      </div>
    </div>
  )
}
