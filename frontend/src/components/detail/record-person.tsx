import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard, type UserProfileSummary } from '@/components/user-profile-hover-card'

/**
 * A person on a `RecordField` row: avatar + name, wrapped in the app's shared
 * `UserProfileHoverCard` — hovering reveals the card whose action opens the
 * read-only user detail Sheet, and the row itself is the button that opens it
 * on click/Enter, so the profile is reachable by keyboard too (user directive
 * 2026-08-06).
 *
 * Lives in the shared detail kit rather than inside a feature because more
 * than one record has a Team block and they must look identical: Opportunità
 * and Offerta had it first, the Anagrafica joined them (user directive
 * 2026-09-11, "così graficamente è coerente"). A copy per module would be free
 * to drift on the avatar size, the hover affordance or the keyboard path.
 *
 * Outside a `UserDetailSheetProvider` the opener degrades to a no-op rather
 * than throwing — the context ships a no-op default for exactly this.
 */
export function RecordPerson({ user }: { user: UserProfileSummary }) {
  return (
    <UserProfileHoverCard user={user} triggerClassName="rounded-md">
      <UserAvatar name={user.name} src={user.avatar_url ?? null} className="shrink-0" />
      <span className="truncate text-sm text-foreground">{user.name}</span>
    </UserProfileHoverCard>
  )
}

/**
 * The `RecordField` modifier a person row needs: the avatar makes the row
 * taller than the text-only ones, so it centers on its label instead of
 * sitting on the label's baseline (`RecordField`'s own default, right for
 * plain text).
 */
export const RECORD_PERSON_ROW_CLASS = '@md:items-center'
