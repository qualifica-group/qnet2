/**
 * Up to two uppercase initials from a display name, shared by every avatar and
 * monogram so the same record never shows "A" on one screen and "AC" on
 * another. A single-word name (a company, a tag) yields its first two letters;
 * a multi-word one the first letter of its first two words.
 *
 * Lives outside `user-avatar.tsx` so that file only exports its component
 * (fast-refresh constraint), and next to `avatar-color` because the two
 * together are the whole identity of an initials fallback.
 */
export function avatarInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}
