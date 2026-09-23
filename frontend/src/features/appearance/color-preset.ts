/**
 * Per-user accent palette (Settings > System > Theme color), ported from the
 * legacy q-net color presets. The colors live in `color-presets.css`, keyed by
 * `[data-color-preset]`; this module owns only the ids, which must stay in step
 * with that stylesheet and the backend `ColorPresetEnum`.
 */
export const COLOR_PRESETS = [
  'default',
  'forest',
  'amber',
  'rose',
  'ocean',
  'plum',
  'graphite',
  'terracotta',
] as const

export type ColorPreset = (typeof COLOR_PRESETS)[number]

/** Matches the backend default serialized when the column is null. */
export const COLOR_PRESET_DEFAULT: ColorPreset = 'default'

/** The attribute `color-presets.css` keys every palette on. */
export const COLOR_PRESET_ATTRIBUTE = 'data-color-preset'

/** Narrow any input to a known preset, falling back to the default. */
export function toColorPreset(value: unknown): ColorPreset {
  return COLOR_PRESETS.find((preset) => preset === value) ?? COLOR_PRESET_DEFAULT
}
