import type { ReactNode } from 'react'
import { CollapsibleTrigger } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'
import { CHAT_DOCK_HEADER_CLASS, CHAT_DOCK_TRIGGER_CLASS } from '@/features/work-order-chat/dock/dock-styles'

interface DockHeaderProps {
  /** Content of the trigger that opens and minimizes the box. */
  children: ReactNode
  /** Full name on hover, when the title is truncated. */
  title?: string
  /** Icon buttons on the right of the title. */
  actions?: ReactNode
}

/**
 * THE header of every dock box, the channel panel and each window alike, so
 * they always share the same 32px height and the brand-blue band (user
 * directives 2026-11-25): the row owns the height, the trigger and the
 * `icon-xs` actions (`CHAT_DOCK_HEADER_BUTTON_CLASS`) fit inside it.
 */
export function DockHeader({ children, title, actions }: DockHeaderProps) {
  return (
    <div className={cn('flex h-8 min-w-0 shrink-0 items-center gap-0.5 pr-1', CHAT_DOCK_HEADER_CLASS)}>
      <CollapsibleTrigger className={CHAT_DOCK_TRIGGER_CLASS} title={title}>
        {children}
      </CollapsibleTrigger>
      {actions}
    </div>
  )
}
