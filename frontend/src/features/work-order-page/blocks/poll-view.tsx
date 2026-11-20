import { NodeViewWrapper, type NodeViewProps } from '@tiptap/react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { usePageBlocks } from '@/features/work-order-page/blocks/page-blocks-context'
import { BLOCK_DATA_TYPE } from '@/features/work-order-page/editor/constants'

/**
 * Node view of a poll: question, options with count and named voters. Clicking
 * an option votes or changes the vote; clicking the chosen one retracts it.
 * Disabled on a read-only page (spec 0380 D-7).
 */
export function PollView({ node }: NodeViewProps) {
  const { t } = useTranslation()
  const { polls, canVote, pendingPollId, onVote, onRetractVote } = usePageBlocks()
  const pollId = typeof node.attrs.pollId === 'number' ? node.attrs.pollId : null
  const poll = pollId === null ? undefined : polls[String(pollId)]

  if (pollId === null || !poll) {
    return (
      <NodeViewWrapper data-type={BLOCK_DATA_TYPE.poll} contentEditable={false} className="my-1">
        <div className="rounded-md border border-dashed bg-card px-2.5 py-1.5 text-sm text-muted-foreground">
          {t('workOrderPage.poll.unavailable')}
        </div>
      </NodeViewWrapper>
    )
  }

  const disabled = !canVote || pendingPollId === poll.id

  return (
    <NodeViewWrapper data-type={BLOCK_DATA_TYPE.poll} contentEditable={false} className="my-1">
      <div role="group" aria-label={poll.question} className="flex flex-col gap-1.5 rounded-md border bg-card p-3 shadow-sm">
        <p className="text-sm font-semibold">{poll.question}</p>
        <ul className="flex flex-col gap-1">
          {poll.options.map((option) => {
            const isMine = poll.my_vote === option.key
            const share = poll.total_votes > 0 ? Math.round((option.votes / poll.total_votes) * 100) : 0
            return (
              <li key={option.key}>
                <button
                  type="button"
                  aria-pressed={isMine}
                  disabled={disabled}
                  onClick={() => (isMine ? onRetractVote(poll.id) : onVote(poll.id, option.key))}
                  className={cn(
                    'relative flex w-full flex-col gap-0.5 overflow-hidden rounded-md border px-2.5 py-1.5 text-left text-sm',
                    'hover:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-70',
                    isMine && 'border-primary',
                  )}
                >
                  <span
                    aria-hidden="true"
                    className="absolute inset-y-0 left-0 bg-primary/10"
                    style={{ width: `${share}%` }}
                  />
                  <span className="relative flex items-center justify-between gap-2">
                    <span className="min-w-0 truncate">{option.label}</span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                      {t('workOrderPage.poll.votes', { count: option.votes })}
                    </span>
                  </span>
                  {option.voters.length > 0 ? (
                    <span className="relative truncate text-xs text-muted-foreground">
                      {option.voters.map((voter) => voter.name).join(', ')}
                    </span>
                  ) : null}
                </button>
              </li>
            )
          })}
        </ul>
        <p className="text-xs text-muted-foreground">{t('workOrderPage.poll.total', { count: poll.total_votes })}</p>
      </div>
    </NodeViewWrapper>
  )
}
