/** Keyboard shortcut written with Tiptap's tokens (`Mod`, `Alt`, `Shift`) and a final key. */
export type ShortcutTokens = readonly string[]

// Written as escapes: they are plain Mac modifier glyphs, not emoji.
const MAC_SYMBOLS: Record<string, string> = { Mod: '⌘', Alt: '⌥', Shift: '⇧' }
const PC_NAMES: Record<string, string> = { Mod: 'Ctrl' }

function isMacPlatform(): boolean {
  return typeof navigator !== 'undefined' && /Mac|iPhone|iPad/i.test(navigator.platform)
}

/** Display text of a shortcut for the current platform: `Ctrl+Alt+1` on PC, the modifier glyphs on Mac. */
export function formatShortcut(tokens: ShortcutTokens): string {
  if (isMacPlatform()) {
    return tokens.map((token) => MAC_SYMBOLS[token] ?? token).join('')
  }
  return tokens.map((token) => PC_NAMES[token] ?? token).join('+')
}
