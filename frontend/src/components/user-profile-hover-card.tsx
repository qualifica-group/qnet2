import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { UserAvatar } from '@/components/user-avatar'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import { useUserDetailSheet } from '@/features/users/user-detail-sheet-context'

/** Minimum projection needed to show a person and open their profile. */
export interface UserProfileSummary {
  id: number
  name: string
  avatar_url?: string | null
}

/** Avatar + name row shared by the clickable action and its read-only fallback. */
const HOVER_ROW_CLASS = 'flex w-full items-center gap-2 rounded-sm px-1.5 py-1 text-left text-sm'

/**
 * The clickable row shown inside a user's hover card: avatar + name + chevron.
 * Clicking (mouse or keyboard) opens the shared read-only user detail Sheet.
 * Without `users.view` it is the same row, not actionable.
 */
export function UserProfileHoverAction({ user }: { user: UserProfileSummary }) {
  const { t } = useTranslation()
  const { openUserDetail, canOpenUserDetail } = useUserDetailSheet()

  if (!canOpenUserDetail) {
    return (
      <div className={HOVER_ROW_CLASS}>
        <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" className="shrink-0" />
        <span className="truncate font-medium">{user.name}</span>
      </div>
    )
  }

  return (
    <button
      type="button"
      onClick={() => openUserDetail(user.id)}
      aria-label={t('common.viewProfile', { name: user.name })}
      className={cn(HOVER_ROW_CLASS, 'outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-2 focus-visible:ring-ring')}
    >
      <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" className="shrink-0" />
      <span className="truncate font-medium">{user.name}</span>
      <ChevronRight aria-hidden="true" className="ml-auto size-3.5 shrink-0 text-muted-foreground" />
    </button>
  )
}

/**
 * A single person chip: a hover card whose trigger (`children`) is also the
 * button that opens the user's detail Sheet on click/Enter — so the profile is
 * reachable by keyboard without depending on the hover card (a sighted-user
 * enhancement that reveals the same "open profile" action).
 *
 * Shared by the table's person columns and by the notes mentions: both need the
 * same hover-to-open-profile affordance, so it lives here rather than inside
 * either feature. Without `users.view` the person is shown, not linked: no
 * button, no hover card (user directive 2026-09-28); the name stays reachable
 * as a tooltip for avatar-only triggers.
 */
export function UserProfileHoverCard({
  user,
  triggerClassName,
  children,
}: {
  user: UserProfileSummary
  triggerClassName?: string
  children: ReactNode
}) {
  const { t } = useTranslation()
  const { openUserDetail, canOpenUserDetail } = useUserDetailSheet()

  if (!canOpenUserDetail) {
    return (
      <span title={user.name} className={cn('flex items-center gap-2 overflow-hidden', triggerClassName)}>
        {children}
      </span>
    )
  }

  return (
    <HoverCard>
      <HoverCardTrigger asChild>
        <button
          type="button"
          onClick={() => openUserDetail(user.id)}
          aria-label={t('common.viewProfile', { name: user.name })}
          className={cn(
            'flex items-center gap-2 overflow-hidden text-left outline-none focus-visible:ring-2 focus-visible:ring-ring',
            triggerClassName,
          )}
        >
          {children}
        </button>
      </HoverCardTrigger>
      <HoverCardContent align="start" className="w-auto max-w-72 min-w-56 p-1">
        <UserProfileHoverAction user={user} />
      </HoverCardContent>
    </HoverCard>
  )
}
