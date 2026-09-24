/**
 * The colour of a completion percentage (user directive 2026-09-22), one rule
 * for the task row, the phase roll-up and the commessa KPI so the same number
 * always reads the same: behind (red), halfway (amber), nearly there (brand
 * blue), done (green). The number next to the bar always carries the value
 * too, so the state is never colour-only.
 */

export interface CompletionTone {
  /** The filled part of the bar. */
  indicator: string
  /** The bar's track, a wash of the same hue. */
  track: string
  /** The percentage figure next to the bar. */
  text: string
}

const BEHIND_MAX = 33
const HALFWAY_MAX = 66
const DONE = 100

const BEHIND: CompletionTone = { indicator: 'bg-destructive', track: 'bg-destructive/15', text: 'text-destructive' }
const HALFWAY: CompletionTone = { indicator: 'bg-warning', track: 'bg-warning/15', text: 'text-warning' }
const NEARLY: CompletionTone = { indicator: 'bg-primary', track: 'bg-primary/15', text: 'text-primary' }
const COMPLETE: CompletionTone = { indicator: 'bg-success', track: 'bg-success/15', text: 'text-success' }

export function completionTone(percentage: number): CompletionTone {
  if (percentage >= DONE) {
    return COMPLETE
  }
  if (percentage > HALFWAY_MAX) {
    return NEARLY
  }
  if (percentage > BEHIND_MAX) {
    return HALFWAY
  }
  return BEHIND
}
