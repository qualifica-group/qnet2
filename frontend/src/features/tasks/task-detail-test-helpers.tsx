import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { TaskDetailView } from '@/features/tasks/task-detail'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

/**
 * Shared scaffolding for the tests of the task detail's in-place editors
 * (spec 0195): mounts the detail and opens one field's editor through its
 * pencil, exactly as the user does. Each test file still declares its own
 * `vi.mock(...)` (`use-auth`, `use-module-open-mode`, the api modules): mocks
 * are hoisted per file in Vitest and cannot be shared across modules.
 */

export function renderTaskDetail(task: TaskDetailWithPermissions, onChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <TaskDetailView task={task} onOpenSubtask={vi.fn()} onCreateSubtask={vi.fn()} onChanged={onChanged} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return { onChanged }
}

/** The pencil of the row labelled `fieldLabel`, or `null` when the row offers no edit. */
export function queryInlineEditButton(fieldLabel: string): HTMLElement | null {
  return screen.queryByRole('button', { name: i18n.t('tasks.detail.inlineEdit.edit', { field: fieldLabel }) })
}

/** Opens the editor of the row labelled `fieldLabel` (fails loudly when the row offers no edit). */
export function openInlineEditor(fieldLabel: string): void {
  const button = queryInlineEditButton(fieldLabel)
  if (!button) {
    throw new Error(`No inline edit affordance for "${fieldLabel}".`)
  }
  fireEvent.click(button)
}
