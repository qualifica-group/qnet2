import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import { cn } from '@/lib/utils'

export interface MentionBadgeProps {
  userId: number
  name: string
  avatarUrl?: string | null
  className?: string
}

/**
 * A mentioned user rendered as a badge: avatar + name, hovering reveals the
 * shared profile card and clicking opens the user detail Sheet — the same
 * affordance the table's person columns already give
 * (`UserProfileHoverCard`), so a mention behaves like a person everywhere.
 */
export function MentionBadge({ userId, name, avatarUrl, className }: MentionBadgeProps) {
  return (
    <UserProfileHoverCard
      user={{ id: userId, name, avatar_url: avatarUrl ?? null }}
      triggerClassName={cn(
        'inline-flex max-w-full gap-1 rounded-full border border-primary/20 bg-primary/10 py-0.5 pr-2 pl-0.5',
        'align-middle text-primary transition-colors hover:bg-primary/20',
        className,
      )}
    >
      <UserAvatar name={name} src={avatarUrl ?? null} size="xs" className="shrink-0" />
      <span className="truncate text-xs font-medium">{name}</span>
    </UserProfileHoverCard>
  )
}
