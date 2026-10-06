import { UserAvatar } from '@/components/user-avatar'
import { AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import {
  UserProfileHoverAction,
  UserProfileHoverCard,
  type UserProfileSummary,
} from '@/components/user-profile-hover-card'

/** How many avatars are shown inline before collapsing into a "+N" chip. */
const MAX_VISIBLE_AVATARS = 5

interface UserAvatarStackProps {
  users: UserProfileSummary[]
}

/**
 * The app's one way to show MANY people at once (user directive 2026-10-06,
 * "regola generale"): an overlapping avatar stack, one clickable hover card
 * per user, collapsing beyond `MAX_VISIBLE_AVATARS` into a "+N" chip whose
 * card lists the remaining users (each clickable). Shared by the grid's
 * multi-user cells and the record details' people rows.
 */
export function UserAvatarStack({ users }: UserAvatarStackProps) {
  const visible = users.slice(0, MAX_VISIBLE_AVATARS)
  const overflow = users.slice(MAX_VISIBLE_AVATARS)

  return (
    <AvatarGroup>
      {visible.map((user) => (
        <UserProfileHoverCard key={user.id} user={user} triggerClassName="rounded-full">
          <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" />
        </UserProfileHoverCard>
      ))}
      {overflow.length > 0 ? (
        <HoverCard>
          <HoverCardTrigger asChild>
            <AvatarGroupCount
              tabIndex={0}
              className="cursor-default outline-none focus-visible:ring-2 focus-visible:ring-ring"
              aria-label={overflow.map((user) => user.name).join(', ')}
            >
              +{overflow.length}
            </AvatarGroupCount>
          </HoverCardTrigger>
          <HoverCardContent align="start" className="max-h-64 w-auto min-w-56 max-w-72 overflow-y-auto p-1">
            <ul className="flex flex-col gap-0.5">
              {overflow.map((user) => (
                <li key={user.id}>
                  <UserProfileHoverAction user={user} />
                </li>
              ))}
            </ul>
          </HoverCardContent>
        </HoverCard>
      ) : null}
    </AvatarGroup>
  )
}
