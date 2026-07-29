import type { ReactNode } from 'react'
import type { ForSelectItem } from '@/features/for-select/types'
import { QuickCreateButton } from '@/features/quick-create/quick-create-button'
import { useQuickCreated } from '@/features/quick-create/use-quick-created'
import {
  QuickCreateDepthContext,
  useQuickCreateDepth,
} from '@/components/form/quick-create-depth-context'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

interface QuickCreateAction {
  /** Refs created so far for this field, kept selected until the invalidated options page catches up (AC-006). */
  quickCreated: RelationFieldRef[]
  /**
   * Builds the `action` slot for `AsyncPaginatedSelect`/`AsyncPaginatedMultiSelect`:
   * `undefined` once already nested inside a quick-create Dialog (see
   * `quick-create-depth-context.ts`). `onCreated` runs after the shared
   * tracking/invalidation, so callers only need to react to their own
   * field's write (replace for single, append for multi — AC-010).
   */
  renderAction: (onCreated: (ref: RelationFieldRef) => void, disabled?: boolean) => ReactNode
  /**
   * `AsyncPaginatedSelect.selectedItem` for a field whose value may be a
   * just-created record: its `{id,label}` while it is still missing from the
   * (invalidated) options page, `null` otherwise. Callers with their own
   * hydrated projection fall back to it with `?? theirs`.
   */
  selectedItemFor: (value: number | null) => ForSelectItem | null
}

/**
 * Wires a relation field's `resource` to the quick-create module: tracks
 * freshly created refs and increments the nesting depth for the "+" it
 * renders (spec 0028). Shared by `RelationSelectField`, `RelationMultiSelectField`
 * and the handful of call sites whose relation picker isn't a plain RHF field
 * (custom `onChange` side effects, non-RHF value/onChange props, ...).
 */
export function useQuickCreateAction(resource: string): QuickCreateAction {
  const depth = useQuickCreateDepth()
  const { quickCreated, handleCreated } = useQuickCreated(resource)

  const renderAction = (onCreated: (ref: RelationFieldRef) => void, disabled = false): ReactNode => {
    if (depth > 0) {
      return undefined
    }
    return (
      <QuickCreateDepthContext.Provider value={depth + 1}>
        <QuickCreateButton
          resource={resource}
          disabled={disabled}
          onCreated={(ref) => {
            handleCreated(ref)
            onCreated(ref)
          }}
        />
      </QuickCreateDepthContext.Provider>
    )
  }

  const selectedItemFor = (value: number | null): ForSelectItem | null => {
    if (value === null) {
      return null
    }
    const created = quickCreated.find((ref) => ref.id === value)
    return created ? { id: created.id, label: created.name } : null
  }

  return { quickCreated, renderAction, selectedItemFor }
}
