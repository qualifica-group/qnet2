import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

/** Server 422 key (relative to `config.`) -> form path (relative to `table.`), first match wins. */
const CONFIG_ERROR_RULES: readonly [RegExp, (match: RegExpMatchArray) => string][] = [
  [/^columns$/, () => 'columns'],
  [/^columns\.(\d+)\.options\.(\d+)\.(value|label)$/, (m) => `columns.${m[1]}.options.${m[2]}.${m[3]}`],
  [/^columns\.(\d+)\.options(\.\d+\.color)?$/, (m) => `columns.${m[1]}.options`],
  [/^columns\.(\d+)\.(key|label|required)$/, (m) => `columns.${m[1]}.${m[2]}`],
  [/^columns\.(\d+)\.(type|config(\..+)?)$/, (m) => `columns.${m[1]}.type`],
  [/^selectable(\.key)?$/, () => 'selectable_key'],
  [/^selectable\.label$/, () => 'selectable_label'],
  [/^summary(\.column)?$/, () => 'summary_column'],
  [/^summary\.strategy$/, () => 'summary_strategy'],
  [/^(min_rows|max_rows)$/, (m) => m[1]],
]

/**
 * Maps the nested `config.*` 422 keys a `table` definition can raise (spec 0180)
 * onto the `table.*` form paths of the columns editor. `applyServerValidationErrors`
 * only handles exact pre-listed paths, so the indexed keys are resolved here.
 * Call it next to that function.
 */
export function applyTableConfigServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
): void {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  for (const [key, messages] of Object.entries(errors ?? {})) {
    const message = messages?.[0]
    if (!message || !key.startsWith('config.')) {
      continue
    }
    const relative = key.slice('config.'.length)
    for (const [pattern, toPath] of CONFIG_ERROR_RULES) {
      const match = relative.match(pattern)
      if (match) {
        setError(`table.${toPath(match)}` as Path<T>, { message })
        break
      }
    }
  }
}
