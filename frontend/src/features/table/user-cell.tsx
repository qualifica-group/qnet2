import type { ICellRendererParams } from 'ag-grid-community'
import { UserAvatar } from '@/components/user-avatar'
import { AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import {
  UserProfileHoverAction,
  UserProfileHoverCard,
} from '@/components/user-profile-hover-card'
import { EmptyCell } from '@/features/table/cell-renderers'

/**
 * The shape every "person" column emits: an `{id, name}` summary, optionally
 * with an inlined avatar (`avatar_url`). Single-user columns (leads operator,
 * opportunities supervisor, users reports_to, business-functions manager) carry
 * one; multi-user columns (business-functions members) carry an array.
 */
export interface UserSummary {
  id: number
  name: string
  avatar_url?: string | null
}

/** How many avatars are shown inline before collapsing into a "+N" chip. */
const MAX_VISIBLE_AVATARS = 5

function toUser(value: unknown): UserSummary | null {
  const user = value as UserSummary | null | undefined
  if (!user || typeof user.id !== 'number' || typeof user.name !== 'string' || user.name === '') {
    return null
  }
  return user
}

/**
 * A single-user column cell: an avatar + name, both a hover-card trigger and the
 * clickable surface that opens the user's detail Sheet. Em dash when empty.
 *
 * On an EDITABLE cell (spec 0055 follow-up) the profile button is dropped: its
 * `onClick` swallowed the click that AG Grid needs to start editing, so an
 * inline-editable person column could never be edited — the profile Sheet
 * opened instead. Both affordances cannot own the same single click (0053
 * D-9), and on an editable cell editing wins; the profile stays one click away
 * from the row's own actions. Read through AG Grid's own
 * `Column.isCellEditable(node)`, so this follows the real per-cell decision
 * (column allow-list AND row flag) instead of second-guessing it.
 */
export function UserCell({ value, column, node }: ICellRendererParams) {
  const user = toUser(value)
  if (!user) {
    return <EmptyCell align="left" />
  }

  const editable = node !== undefined && column?.isCellEditable?.(node) === true

  if (editable) {
    return (
      <div className="flex h-full items-center gap-2 overflow-hidden">
        <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" className="shrink-0" />
        <span className="truncate">{user.name}</span>
      </div>
    )
  }

  return (
    <div className="flex h-full items-center overflow-hidden">
      <UserProfileHoverCard user={user} triggerClassName="rounded-md">
        <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" className="shrink-0" />
        <span className="truncate">{user.name}</span>
      </UserProfileHoverCard>
    </div>
  )
}

/**
 * A multi-user column cell: an overlapping avatar stack, one clickable hover
 * card per user, collapsing beyond `MAX_VISIBLE_AVATARS` into a "+N" chip whose
 * card lists the remaining users (each clickable). Em dash when empty.
 */
export function UserStackCell({ value }: ICellRendererParams) {
  const users = Array.isArray(value) ? value.map(toUser).filter((u): u is UserSummary => u !== null) : []
  if (users.length === 0) {
    return <EmptyCell align="left" />
  }

  const visible = users.slice(0, MAX_VISIBLE_AVATARS)
  const overflow = users.slice(MAX_VISIBLE_AVATARS)

  return (
    <div className="flex h-full items-center">
      <AvatarGroup>
        {visible.map((user) => (
          <UserProfileHoverCard key={user.id} user={user} triggerClassName="rounded-full">
            <UserAvatar name={user.name} src={user.avatar_url ?? null} size="sm" />
          </UserProfileHoverCard>
        ))}
        {overflow.length > 0 && (
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
        )}
      </AvatarGroup>
    </div>
  )
}
