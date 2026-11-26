import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { ArrowRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAiPreviewDecision } from '@/features/ai-assistant/hooks/use-ai-preview-decision'
import { useNow } from '@/features/ai-assistant/hooks/use-now'
import type { AiPreview, AiTurn } from '@/features/ai-assistant/types'

const MS_PER_SECOND = 1000
const SECONDS_PER_MINUTE = 60
/** The server answers 404/409 when the preview is no longer this turn's open one. */
const CLOSED_STATUSES: readonly number[] = [404, 409]
const EMPTY_VALUE = '—'

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return EMPTY_VALUE
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value)
}

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
 * The write the assistant prepared, as the connector returned it (0433, D-19): record, before
 * and after of every field, effects and time left. Everything is rendered as plain text; the
 * model text of the turn never reaches this card. Only the preview id goes back on a click.
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
      <p className="font-medium text-foreground">{preview.record.label ?? preview.record.type}</p>
      <table className="w-full table-fixed border-collapse">
        <tbody>
          {preview.changes.map((change) => (
            <tr key={change.field} className="border-t border-border">
              <th scope="row" className="w-1/3 py-1 pr-2 text-left align-top font-medium text-muted-foreground">
                {change.label}
              </th>
              <td className="break-words py-1 text-muted-foreground line-through">{formatValue(change.before)}</td>
              <td className="w-5 py-1 align-top">
                <ArrowRight aria-hidden className="size-3.5 text-muted-foreground" />
              </td>
              <td className="break-words py-1 font-medium text-foreground">{formatValue(change.after)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {preview.effects.length > 0 ? (
        <ul aria-label={t('aiAssistant.preview.effects')} className="list-disc pl-4 text-muted-foreground">
          {preview.effects.map((effect) => (
            <li key={effect}>{effect}</li>
          ))}
        </ul>
      ) : null}
      <p role="timer" className="text-muted-foreground">
        {isExpired ? t('aiAssistant.preview.expired') : t('aiAssistant.preview.expiresIn', { time: formatRemaining(remaining) })}
      </p>
      {failure ? (
        <p role="alert" className="text-destructive">
          {failure}
        </p>
      ) : null}
      <div className="flex gap-2">
        <Button type="button" size="xs" disabled={disabled} onClick={() => decide('confirm')}>
          {t('aiAssistant.preview.confirm')}
        </Button>
        <Button type="button" size="xs" variant="secondary" disabled={disabled} onClick={() => decide('cancel')}>
          {t('aiAssistant.preview.cancel')}
        </Button>
      </div>
    </div>
  )
}
