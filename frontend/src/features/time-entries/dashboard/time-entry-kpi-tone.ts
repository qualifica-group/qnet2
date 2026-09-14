/**
 * The four KPI tile tints (spec 0122 D-2): ONE map of constants, dark variant
 * included, so a tile never hand-rolls its own gradient. `via-card`/`bg-card`
 * anchor the gradient to the actual surface token (never `bg-white`, per D-2)
 * so the tile still reads correctly on the dark theme's `--card`. Every
 * `foreground`/`hint` pairing keeps AA against its own tinted background in
 * both themes (checked against the light `-950`/dark `-50`..`-300` steps,
 * which sit well clear of the 4.5:1 floor against their own tile tint).
 */

export type TimeEntryKpiTone = 'sky' | 'emerald' | 'violet' | 'rose'

export interface TimeEntryKpiToneClasses {
  /** Tile border + background gradient. */
  container: string
  /** Decorative blurred glow in the tile's top-right corner. */
  glow: string
  /** Uppercase eyebrow label. */
  label: string
  /** Icon chip background + icon color. */
  iconChip: string
  /** Big metric value. */
  value: string
  /** Secondary hint line under the value. */
  hint: string
}

export const TIME_ENTRY_KPI_TONE_CLASSES: Record<TimeEntryKpiTone, TimeEntryKpiToneClasses> = {
  sky: {
    container:
      'border-sky-200/70 bg-gradient-to-br from-sky-50 via-card to-sky-50/40 dark:border-sky-900/50 dark:from-sky-950/50 dark:via-card dark:to-sky-950/20',
    glow: 'bg-sky-200/40 dark:bg-sky-800/20',
    label: 'text-sky-900/70 dark:text-sky-200/70',
    iconChip: 'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
    value: 'text-sky-950 dark:text-sky-50',
    hint: 'text-sky-900/60 dark:text-sky-200/60',
  },
  emerald: {
    container:
      'border-emerald-200/70 bg-gradient-to-br from-emerald-50 via-card to-emerald-50/40 dark:border-emerald-900/50 dark:from-emerald-950/50 dark:via-card dark:to-emerald-950/20',
    glow: 'bg-emerald-200/40 dark:bg-emerald-800/20',
    label: 'text-emerald-900/70 dark:text-emerald-200/70',
    iconChip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
    value: 'text-emerald-950 dark:text-emerald-50',
    hint: 'text-emerald-900/60 dark:text-emerald-200/60',
  },
  violet: {
    container:
      'border-violet-200/70 bg-gradient-to-br from-violet-50 via-card to-violet-50/40 dark:border-violet-900/50 dark:from-violet-950/50 dark:via-card dark:to-violet-950/20',
    glow: 'bg-violet-200/40 dark:bg-violet-800/20',
    label: 'text-violet-900/70 dark:text-violet-200/70',
    iconChip: 'bg-violet-100 text-violet-700 dark:bg-violet-900/60 dark:text-violet-300',
    value: 'text-violet-950 dark:text-violet-50',
    hint: 'text-violet-900/60 dark:text-violet-200/60',
  },
  rose: {
    container:
      'border-rose-200/70 bg-gradient-to-br from-rose-50 via-card to-rose-50/40 dark:border-rose-900/50 dark:from-rose-950/50 dark:via-card dark:to-rose-950/20',
    glow: 'bg-rose-200/40 dark:bg-rose-800/20',
    label: 'text-rose-900/70 dark:text-rose-200/70',
    iconChip: 'bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300',
    value: 'text-rose-950 dark:text-rose-50',
    hint: 'text-rose-900/60 dark:text-rose-200/60',
  },
}

/** Anomaly pill tints (over/under target), same source as the tile map (D-2). */
export const TIME_ENTRY_ANOMALY_PILL_CLASSES = {
  over: 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200',
  under: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
} as const
