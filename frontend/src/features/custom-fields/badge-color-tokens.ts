/**
 * The enum-badge color palette, mirroring `BADGE_COLOR_CLASSES` in
 * `features/table/cell-renderers.tsx` (the single source of truth that turns an
 * option's stored color TOKEN into the grid badge classes). An enum option's
 * `color` is one of these token NAMES, never an arbitrary hex — the grid badge
 * looks the token up by name, so a free hex value would render no color. The
 * color picker therefore offers exactly these tokens as clickable swatches.
 *
 * `swatch` classes are spelled out in full (not built as `bg-${token}-500`) so
 * Tailwind's scanner keeps them in the bundle.
 */
export interface BadgeColorToken {
  /** Stored value (also the `customFields.colors.<token>` i18n key). */
  token: string
  /** Static Tailwind class painting the swatch dot. */
  swatch: string
  /** Static Tailwind classes of a soft panel in the token's hue: a veil over the card plus its hairline. */
  tint: string
}

export const BADGE_COLOR_TOKENS: readonly BadgeColorToken[] = [
  { token: 'slate', swatch: 'bg-slate-500', tint: 'border-slate-500/25 bg-slate-500/5' },
  { token: 'gray', swatch: 'bg-gray-500', tint: 'border-gray-500/25 bg-gray-500/5' },
  { token: 'red', swatch: 'bg-red-500', tint: 'border-red-500/25 bg-red-500/5' },
  { token: 'orange', swatch: 'bg-orange-500', tint: 'border-orange-500/25 bg-orange-500/5' },
  { token: 'amber', swatch: 'bg-amber-500', tint: 'border-amber-500/25 bg-amber-500/5' },
  { token: 'yellow', swatch: 'bg-yellow-500', tint: 'border-yellow-500/25 bg-yellow-500/5' },
  { token: 'green', swatch: 'bg-green-500', tint: 'border-green-500/25 bg-green-500/5' },
  { token: 'emerald', swatch: 'bg-emerald-500', tint: 'border-emerald-500/25 bg-emerald-500/5' },
  { token: 'teal', swatch: 'bg-teal-500', tint: 'border-teal-500/25 bg-teal-500/5' },
  { token: 'blue', swatch: 'bg-blue-500', tint: 'border-blue-500/25 bg-blue-500/5' },
  { token: 'indigo', swatch: 'bg-indigo-500', tint: 'border-indigo-500/25 bg-indigo-500/5' },
  { token: 'violet', swatch: 'bg-violet-500', tint: 'border-violet-500/25 bg-violet-500/5' },
  { token: 'purple', swatch: 'bg-purple-500', tint: 'border-purple-500/25 bg-purple-500/5' },
  { token: 'pink', swatch: 'bg-pink-500', tint: 'border-pink-500/25 bg-pink-500/5' },
]

/** Soft panel classes for a stored token, or undefined when unset/unknown. */
export function tintClassFor(token: string | null | undefined): string | undefined {
  return BADGE_COLOR_TOKENS.find((entry) => entry.token === token)?.tint
}

/** Swatch class for a stored token, or undefined when unset/unknown. */
export function swatchClassFor(token: string | null | undefined): string | undefined {
  return BADGE_COLOR_TOKENS.find((entry) => entry.token === token)?.swatch
}
