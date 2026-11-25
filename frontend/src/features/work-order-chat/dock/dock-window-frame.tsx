import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronUp, Minus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'
import { DockHeader } from '@/features/work-order-chat/dock/dock-header'
import {
  CHAT_DOCK_BODY_CLASS,
  CHAT_DOCK_BOX_CLASS,
  CHAT_DOCK_FULL_SCREEN_CLASS,
  CHAT_DOCK_HEADER_BUTTON_CLASS,
  CHAT_DOCK_HEADER_MUTED_CLASS,
} from '@/features/work-order-chat/dock/dock-styles'

interface DockWindowFrameProps {
  title: string
  /** Secondary line next to the title while open (the client of a channel). */
  subtitle?: string | null
  /** Avatar or icon before the title, decorative. */
  leading: ReactNode
  minimized: boolean
  onToggleMinimized: () => void
  onClose: () => void
  /** Shown on the minimized bar (the unread count). */
  minimizedBadge?: ReactNode
  /** Icon buttons shown while open, before minimize and close (`CHAT_DOCK_HEADER_BUTTON_CLASS`). */
  actions?: ReactNode
  /** Width of the open window from `sm` up. */
  openWidthClass: string
  /**
   * Keeps the body mounted while minimized (the assistant: an ongoing answer
   * and the draft survive). Otherwise the body mounts only while open, so a
   * channel is marked read only then.
   */
  keepMounted?: boolean
  children: ReactNode
}

/** The chrome of a dock conversation window (spec 0432), like Reezl's: header, minimize to a low bar, close. */
export function DockWindowFrame({
  title,
  subtitle,
  leading,
  minimized,
  onToggleMinimized,
  onClose,
  minimizedBadge,
  actions,
  openWidthClass,
  keepMounted = false,
  children,
}: DockWindowFrameProps) {
  const { t } = useTranslation()

  return (
    <Collapsible
      open={!minimized}
      onOpenChange={onToggleMinimized}
      className={cn(CHAT_DOCK_BOX_CLASS, !minimized && cn(CHAT_DOCK_FULL_SCREEN_CLASS, openWidthClass))}
    >
      <DockHeader
        title={title}
        actions={
          <>
            {minimized ? null : actions}
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className={CHAT_DOCK_HEADER_BUTTON_CLASS}
              aria-label={minimized ? t('workOrders.chat.dock.expand') : t('workOrders.chat.dock.minimize')}
              onClick={onToggleMinimized}
            >
              {minimized ? <ChevronUp aria-hidden="true" /> : <Minus aria-hidden="true" />}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className={CHAT_DOCK_HEADER_BUTTON_CLASS}
              aria-label={t('workOrders.chat.dock.close')}
              onClick={onClose}
            >
              <X aria-hidden="true" />
            </Button>
          </>
        }
      >
        <span aria-hidden="true" className="flex shrink-0">
          {leading}
        </span>
        <span className="flex min-w-0 flex-1 items-baseline gap-1.5">
          <span className="truncate">{title}</span>
          {subtitle && !minimized ? (
            <span className={cn('hidden truncate text-[0.65rem] font-normal sm:inline', CHAT_DOCK_HEADER_MUTED_CLASS)}>{subtitle}</span>
          ) : null}
        </span>
        {minimized ? minimizedBadge : null}
      </DockHeader>
      <CollapsibleContent
        forceMount={keepMounted ? true : undefined}
        className={cn(CHAT_DOCK_BODY_CLASS, 'data-[state=closed]:hidden')}
      >
        {children}
      </CollapsibleContent>
    </Collapsible>
  )
}
