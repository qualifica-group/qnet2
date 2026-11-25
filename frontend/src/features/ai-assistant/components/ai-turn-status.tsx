import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Loader2, X } from 'lucide-react'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'
import { BubbleAvatar } from '@/features/ai-assistant/components/ai-message-bubble'
import { AI_WIDGET_REPLY_CLASS } from '@/features/ai-assistant/components/ai-widget-styles'
import type { AiTurn, AiTurnErrorCode, AiTurnStep } from '@/features/ai-assistant/types'
import { cn } from '@/lib/utils'

const KNOWN_ERROR_CODES: readonly AiTurnErrorCode[] = [
  'model_unavailable',
  'connector_unavailable',
  'link_lost',
  'too_many_steps',
  'turn_timeout',
  'stream_error',
  'switched_off',
  'limit_person',
  'limit_total',
  'internal',
]

const STEP_STATE_KEY = {
  running: 'aiAssistant.turn.stepRunning',
  done: 'aiAssistant.turn.stepDone',
  failed: 'aiAssistant.turn.stepFailed',
} as const

const STEP_ICON_CLASS = 'size-3.5 shrink-0'

function StepIcon({ state }: Pick<AiTurnStep, 'state'>) {
  if (state === 'running') {
    return <Loader2 aria-hidden className={cn(STEP_ICON_CLASS, 'animate-spin')} />
  }
  return state === 'done' ? (
    <Check aria-hidden className={STEP_ICON_CLASS} />
  ) : (
    <X aria-hidden className={cn(STEP_ICON_CLASS, 'text-destructive')} />
  )
}

/**
 * State of the turn being followed (spec 0433): queued, steps, partial text, closed
 * without a reply. Error texts are fixed and translated here by `error_code`, never
 * the server text. The preview card with Confirm/Cancel is ticket 05.
 */
export function AiTurnStatus({ turn }: { turn: AiTurn }) {
  const { t } = useTranslation()
  const { status } = turn

  if (status === 'queued') {
    return (
      <TurnBubble>
        <p role="status" className="text-xs text-muted-foreground">
          {turn.queue_position === null
            ? t('aiAssistant.turn.queued')
            : t('aiAssistant.turn.queuedPosition', { position: turn.queue_position })}
        </p>
      </TurnBubble>
    )
  }

  const stoppedWithoutReply = status === 'failed' || status === 'cancelled' || status === 'limit_reached'
  const code = turn.error_code !== null && KNOWN_ERROR_CODES.includes(turn.error_code) ? turn.error_code : null
  const hasBody = turn.assistant_text !== '' || turn.steps.length > 0

  return (
    <TurnBubble>
      {turn.steps.length > 0 ? (
        <ul aria-label={t('aiAssistant.turn.stepsLabel')} className="mb-1 flex flex-col gap-0.5 text-xs text-muted-foreground">
          {turn.steps.map((step) => (
            <li key={step.index} className="flex items-center gap-1.5">
              <StepIcon state={step.state} />
              <span className="min-w-0 truncate">{step.label}</span>
              <span className="sr-only">{t(STEP_STATE_KEY[step.state])}</span>
            </li>
          ))}
        </ul>
      ) : null}
      {turn.assistant_text !== '' ? <AiMarkdown content={turn.assistant_text} /> : null}
      {status === 'running' && !hasBody ? (
        <p role="status" className="text-xs text-muted-foreground">
          {t('aiAssistant.messages.typing')}
        </p>
      ) : null}
      {status === 'awaiting_confirmation' ? (
        <p role="status" className="text-xs text-muted-foreground">
          {t('aiAssistant.turn.awaitingConfirmation')}
        </p>
      ) : null}
      {stoppedWithoutReply ? (
        <p role="alert" className="text-xs text-destructive">
          {code ? t(`aiAssistant.turn.errors.${code}`) : t(`aiAssistant.turn.stopped.${status}`)}
        </p>
      ) : null}
    </TurnBubble>
  )
}

function TurnBubble({ children }: { children: ReactNode }) {
  return (
    <li className="flex gap-2">
      <BubbleAvatar isUser={false} />
      <div
        className={cn(
          'min-w-0 max-w-[85%] rounded-lg border border-border bg-card px-3 py-2 text-sm text-card-foreground',
          AI_WIDGET_REPLY_CLASS,
        )}
      >
        {children}
      </div>
    </li>
  )
}
