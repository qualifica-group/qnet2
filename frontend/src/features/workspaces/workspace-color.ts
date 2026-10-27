/**
 * Text classes of an app colour token (a `BADGE_COLOR_TOKENS` name), spelled out
 * in full so Tailwind keeps them in the bundle. Two surfaces, two shades:
 * - sidebar: the sidebar is dark in every theme and colour preset (lightness
 *   <= 24%), so the 300 shade is used; measured contrast >= 5.0:1 on the
 *   brightest preset surface (AA needs 4.5:1).
 * - panel: the launcher popover is white (light) or dark (dark theme):
 *   700 on light (>= 4.9:1), 300 on dark.
 */
const TEXT_CLASSES: Record<string, { sidebar: string; panel: string }> = {
  slate: { sidebar: 'text-slate-300', panel: 'text-slate-700 dark:text-slate-300' },
  gray: { sidebar: 'text-gray-300', panel: 'text-gray-700 dark:text-gray-300' },
  red: { sidebar: 'text-red-300', panel: 'text-red-700 dark:text-red-300' },
  orange: { sidebar: 'text-orange-300', panel: 'text-orange-700 dark:text-orange-300' },
  amber: { sidebar: 'text-amber-300', panel: 'text-amber-700 dark:text-amber-300' },
  yellow: { sidebar: 'text-yellow-300', panel: 'text-yellow-700 dark:text-yellow-300' },
  green: { sidebar: 'text-green-300', panel: 'text-green-700 dark:text-green-300' },
  emerald: { sidebar: 'text-emerald-300', panel: 'text-emerald-700 dark:text-emerald-300' },
  teal: { sidebar: 'text-teal-300', panel: 'text-teal-700 dark:text-teal-300' },
  blue: { sidebar: 'text-blue-300', panel: 'text-blue-700 dark:text-blue-300' },
  indigo: { sidebar: 'text-indigo-300', panel: 'text-indigo-700 dark:text-indigo-300' },
  violet: { sidebar: 'text-violet-300', panel: 'text-violet-700 dark:text-violet-300' },
  purple: { sidebar: 'text-purple-300', panel: 'text-purple-700 dark:text-purple-300' },
  pink: { sidebar: 'text-pink-300', panel: 'text-pink-700 dark:text-pink-300' },
}

export const NEUTRAL_SIDEBAR_TEXT_CLASS = 'text-sidebar-foreground'
export const NEUTRAL_PANEL_TEXT_CLASS = 'text-foreground'

export function workspaceSidebarTextClass(color: string | null | undefined): string {
  return (color ? TEXT_CLASSES[color]?.sidebar : undefined) ?? NEUTRAL_SIDEBAR_TEXT_CLASS
}

export function workspacePanelTextClass(color: string | null | undefined): string {
  return (color ? TEXT_CLASSES[color]?.panel : undefined) ?? NEUTRAL_PANEL_TEXT_CLASS
}

export const WORKSPACE_COLOR_TOKENS = Object.keys(TEXT_CLASSES)
