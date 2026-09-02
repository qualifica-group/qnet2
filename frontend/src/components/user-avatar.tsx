import type { AvatarSize } from '@/components/ui/avatar'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { avatarColor } from '@/components/avatar-color'
import { avatarInitials } from '@/components/avatar-initials'

interface UserAvatarProps {
  /** Display name: drives the deterministic color, the initials and the alt text. */
  name: string
  /** Avatar image source (data: URI or URL); falls back to initials when null. */
  src?: string | null
  /** Rung of the app-wide avatar scale; defaults to `"default"` (32px). */
  size?: AvatarSize
  /** Extra classes forwarded to the avatar root — layout only, never sizing. */
  className?: string
}

/**
 * Single source of truth for rendering an identity avatar across the app —
 * sidebar, tables, forms, selects, detail sheets. Renders the image when
 * available and the name's initials otherwise, always as a circle tinted by
 * `avatarColor(name)`. Change the avatar's look or behaviour HERE and it
 * propagates everywhere; do not re-compose Avatar/AvatarImage/AvatarFallback
 * ad hoc elsewhere, and do not override the diameter or the font size from a
 * call site — pick a `size` rung so the same person looks identical on every
 * screen.
 */
export function UserAvatar({ name, src, size, className }: UserAvatarProps) {
  const color = avatarColor(name)
  return (
    // Keying by image-vs-fallback remounts the Radix root when the avatar is
    // removed, resetting its internal image-loading status so the initials
    // fallback shows immediately instead of leaving a blank circle.
    <Avatar key={src ? 'image' : 'fallback'} size={size} className={className}>
      {src && <AvatarImage src={src} alt={name} />}
      <AvatarFallback style={{ backgroundColor: color.bg, color: color.fg }}>
        {avatarInitials(name)}
      </AvatarFallback>
    </Avatar>
  )
}

