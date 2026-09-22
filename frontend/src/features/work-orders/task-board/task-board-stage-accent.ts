/**
 * The colour identity of a phase (D-5), cycling over the app's own
 * `--chart-1..5` tokens so it follows the theme. One object per phase keeps
 * the dot next to its name and the stripe on its task card in the same hue in
 * both views; "Senza fase" gets a neutral tone instead. Class names are
 * written out in full so Tailwind can see them.
 */

export interface StageAccent {
  /** The small swatch before the phase name. */
  dot: string
  /** The vertical stripe on the left edge of the phase's task card. */
  stripe: string
}

const STAGE_ACCENTS: readonly StageAccent[] = [
  { dot: 'bg-chart-1', stripe: 'bg-chart-1' },
  { dot: 'bg-chart-2', stripe: 'bg-chart-2' },
  { dot: 'bg-chart-3', stripe: 'bg-chart-3' },
  { dot: 'bg-chart-4', stripe: 'bg-chart-4' },
  { dot: 'bg-chart-5', stripe: 'bg-chart-5' },
]

export const NO_STAGE_ACCENT: StageAccent = { dot: 'bg-muted-foreground/40', stripe: 'bg-border' }

export function stageAccentAt(index: number): StageAccent {
  return STAGE_ACCENTS[index % STAGE_ACCENTS.length]
}
