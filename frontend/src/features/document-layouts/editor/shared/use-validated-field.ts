import { useState } from 'react'

interface ValidatedFieldResult {
  text: string
  isInvalid: boolean
  handleChange: (raw: string) => void
}

/**
 * Local-first controlled text input backing a validated, range/shape-checked
 * committed value (AC-124: "un margine fuori range mostra un errore e non
 * produce una config invalida"). The caller's `value` only advances through
 * `onCommit` when `parse` accepts the raw text — an invalid keystroke stays
 * purely local UI state and never reaches the saved config. Shared by every
 * bounded numeric/hex field in the editor (`IntegerField`, `HexColorField`)
 * instead of re-deriving this local/committed split at each call site.
 */
export function useValidatedField<T>(
  value: T,
  serialize: (value: T) => string,
  parse: (raw: string) => T | null,
  onCommit: (value: T) => void,
): ValidatedFieldResult {
  const [text, setText] = useState(() => serialize(value))
  const [isInvalid, setIsInvalid] = useState(false)
  const [syncedValue, setSyncedValue] = useState(value)

  // Adjusted during render (React's "adjusting state on prop change" recipe)
  // rather than an effect: when the committed value moves from elsewhere
  // (e.g. another field resets the whole block), the local draft resyncs
  // without an extra render pass.
  if (value !== syncedValue) {
    setSyncedValue(value)
    setText(serialize(value))
    setIsInvalid(false)
  }

  function handleChange(raw: string) {
    setText(raw)
    const parsed = parse(raw)
    if (parsed === null) {
      setIsInvalid(true)
      return
    }
    setIsInvalid(false)
    onCommit(parsed)
  }

  return { text, isInvalid, handleChange }
}
