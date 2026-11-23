import type { HighlightColor, TextColor } from '@/features/work-order-page/editor/constants'

/**
 * Swatch looks of the two palettes. They repeat the page prose colors
 * (`page-prose.ts`), so a swatch previews what the text will look like.
 * Class names are written out in full: Tailwind cannot see concatenations.
 */
export const HIGHLIGHT_SWATCH_CLASS: Record<HighlightColor, string> = {
  yellow: 'bg-yellow-400/40',
  green: 'bg-green-500/30',
  blue: 'bg-blue-500/30',
  pink: 'bg-pink-500/30',
  purple: 'bg-purple-500/30',
}

export const TEXT_COLOR_SWATCH_CLASS: Record<TextColor, string> = {
  gray: 'bg-muted-foreground',
  red: 'bg-red-600 dark:bg-red-400',
  orange: 'bg-orange-600 dark:bg-orange-400',
  green: 'bg-green-700 dark:bg-green-400',
  blue: 'bg-blue-600 dark:bg-blue-400',
  purple: 'bg-purple-600 dark:bg-purple-400',
}
