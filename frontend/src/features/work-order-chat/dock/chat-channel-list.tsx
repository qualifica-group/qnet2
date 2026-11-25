import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { MessagesSquare, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { Skeleton } from '@/components/ui/skeleton'
import { ChatAssistantRow } from '@/features/work-order-chat/dock/chat-assistant-row'
import { ChatChannelRow } from '@/features/work-order-chat/dock/chat-channel-row'
import { useChatChannelFilter, type ChatChannelFilter } from '@/features/work-order-chat/dock/use-chat-channel-filter'
import { useNow } from '@/features/work-order-chat/use-now'
import type { ChatChannel } from '@/features/work-order-chat/types'
import { CHAT_DOCK_SOFT_BUTTON_CLASS } from '@/features/work-order-chat/dock/dock-styles'

const SKELETON_ROWS = 3

const NO_CHANNELS: ChatChannel[] = []

interface ChatChannelListProps {
  /** False with the chat switched off (CHAT_ENABLED): the panel holds the assistant alone. */
  showChannels: boolean
  channels: ChatChannel[] | undefined
  isPending: boolean
  /** The AI assistant is pinned on top (`useAiDockEntry`). */
  showAssistant: boolean
  onRetry: () => void
  onOpen: (workOrderId: number) => void
  onOpenAssistant: () => void
}

/**
 * The dock panel, like a CRM inbox: search and "Non letti" on top, the AI
 * assistant pinned first, then one row per channel, newest message first.
 */
export function ChatChannelList({
  showChannels,
  channels,
  isPending,
  showAssistant,
  onRetry,
  onOpen,
  onOpenAssistant,
}: ChatChannelListProps) {
  const { t } = useTranslation()
  const now = useNow()
  const { query, setQuery, filter, setFilter, visible, showsAssistant } = useChatChannelFilter(
    channels ?? NO_CHANNELS,
    t('aiAssistant.title'),
  )
  const assistantRow = showAssistant && showsAssistant ? <ChatAssistantRow onOpen={onOpenAssistant} /> : null

  if (!showChannels) {
    return <div className="min-h-0 flex-1 overflow-y-auto">{showAssistant ? <ChatAssistantRow onOpen={onOpenAssistant} /> : null}</div>
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="flex flex-col gap-1.5 border-b border-border p-2">
        <div className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            type="search"
            value={query}
            onChange={(event: ChangeEvent<HTMLInputElement>) => setQuery(event.target.value)}
            aria-label={t('workOrders.chat.dock.search')}
            placeholder={t('workOrders.chat.dock.search')}
            className="h-7 pl-7 text-xs md:text-xs"
          />
        </div>
        <SegmentedControl<ChatChannelFilter>
          aria-label={t('workOrders.chat.dock.filterLabel')}
          value={filter}
          onValueChange={setFilter}
          options={[
            { value: 'all', label: t('workOrders.chat.dock.filterAll') },
            { value: 'unread', label: t('workOrders.chat.dock.filterUnread') },
          ]}
        />
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto">
        {assistantRow ? <div className="border-b border-border">{assistantRow}</div> : null}
        <ChannelRows
          channels={channels}
          visible={visible}
          isPending={isPending}
          isFiltered={query.trim() !== '' || filter !== 'all'}
          now={now}
          onRetry={onRetry}
          onOpen={onOpen}
        />
      </div>
    </div>
  )
}

interface ChannelRowsProps {
  channels: ChatChannel[] | undefined
  visible: ChatChannel[]
  isPending: boolean
  isFiltered: boolean
  now: number
  onRetry: () => void
  onOpen: (workOrderId: number) => void
}

/** The channels below the assistant, with their loading, error, empty and no-result states. */
function ChannelRows({ channels, visible, isPending, isFiltered, now, onRetry, onOpen }: ChannelRowsProps) {
  const { t } = useTranslation()

  if (isPending) {
    return (
      <div role="status" aria-label={t('workOrders.chat.loading')} className="flex flex-col">
        {Array.from({ length: SKELETON_ROWS }, (_, index) => (
          <div key={index} className="flex items-center gap-2.5 px-3 py-2.5">
            <Skeleton className="size-8 rounded-full" />
            <div className="flex flex-1 flex-col gap-1.5">
              <Skeleton className="h-3 w-2/3" />
              <Skeleton className="h-2.5 w-full" />
            </div>
          </div>
        ))}
      </div>
    )
  }

  if (!channels) {
    return (
      <div role="alert" className="flex flex-col items-start gap-2 p-3 text-xs">
        <span>{t('workOrders.chat.dock.loadError')}</span>
        <Button type="button" size="xs" variant="outline" className={CHAT_DOCK_SOFT_BUTTON_CLASS} onClick={onRetry}>
          {t('workOrders.chat.retry')}
        </Button>
      </div>
    )
  }

  if (channels.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 p-6 text-center">
        <span className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <MessagesSquare className="size-4" aria-hidden="true" />
        </span>
        <span className="text-xs font-medium">{t('workOrders.chat.dock.emptyTitle')}</span>
        <span className="text-[0.7rem] text-muted-foreground">{t('workOrders.chat.dock.empty')}</span>
      </div>
    )
  }

  if (visible.length === 0) {
    return isFiltered ? <p className="p-4 text-center text-xs text-muted-foreground">{t('workOrders.chat.dock.noResults')}</p> : null
  }

  return (
    <ul aria-label={t('workOrders.chat.dock.channelsLabel')} className="divide-y divide-border">
      {visible.map((channel) => (
        <li key={channel.work_order_id}>
          <ChatChannelRow channel={channel} now={now} onOpen={onOpen} />
        </li>
      ))}
    </ul>
  )
}
