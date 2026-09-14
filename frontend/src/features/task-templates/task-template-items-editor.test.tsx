import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskTemplateItemsEditor } from '@/features/task-templates/task-template-items-editor'
import type { TaskTemplateItemFormRow } from '@/features/task-templates/types'

/**
 * Spec 0124 AC-025: the row status select shows only `meta.group`
 * open/pending options, and a row's validation error is wired with the
 * accessible triad (aria-invalid + aria-describedby + `role="alert"`,
 * frontend.md §10).
 */

const useTaskStatusesForSelectMock = vi.fn()
vi.mock('@/features/task-statuses/for-select-api', () => ({
  useTaskStatusesForSelect: (...args: unknown[]) => useTaskStatusesForSelectMock(...args),
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => null,
}))

const ROW: TaskTemplateItemFormRow = {
  id: 'row-1',
  title: 'Kickoff call',
  description: null,
  estimated_minutes: null,
  task_status_id: null,
  due_offset_days: 0,
}

function renderEditor(rows: TaskTemplateItemFormRow[], errors = {}) {
  const onUpdateRow = vi.fn()
  render(
    <TaskTemplateItemsEditor
      rows={rows}
      errors={errors}
      stagedFilesByRow={{}}
      onReorder={vi.fn()}
      onAdd={vi.fn()}
      onRemove={vi.fn()}
      onUpdateRow={onUpdateRow}
      onAddStagedFiles={vi.fn()}
      onRemoveStagedFile={vi.fn()}
    />,
  )
  return onUpdateRow
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useTaskStatusesForSelectMock.mockReset()
  useTaskStatusesForSelectMock.mockReturnValue({
    data: {
      pages: [
        {
          items: [
            { id: 1, label: 'Open', meta: { group: 'open' } },
            { id: 2, label: 'Waiting', meta: { group: 'pending' } },
            { id: 3, label: 'Validating', meta: { group: 'in_validation' } },
            { id: 4, label: 'Closed', meta: { group: 'closed_positive' } },
          ],
        },
      ],
    },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
    fetchNextPage: vi.fn(),
  })
})

describe('TaskTemplateItemsEditor — status select filtered to open/pending (AC-025)', () => {
  it('lists only the open/pending options, never in_validation/closed_*', () => {
    renderEditor([ROW])

    fireEvent.click(screen.getByRole('combobox', { name: 'Initial status' }))

    expect(screen.getByRole('option', { name: 'Open' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Waiting' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Validating' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Closed' })).not.toBeInTheDocument()
  })

  it('patches the row with the picked status id', () => {
    const onUpdateRow = renderEditor([ROW])

    fireEvent.click(screen.getByRole('combobox', { name: 'Initial status' }))
    fireEvent.click(screen.getByRole('option', { name: 'Waiting' }))

    expect(onUpdateRow).toHaveBeenCalledWith('row-1', { task_status_id: 2 })
  })
})

describe('TaskTemplateItemsEditor — accessible error triad (frontend.md §10)', () => {
  it('wires a title error with aria-invalid, aria-describedby and role=alert', () => {
    renderEditor([ROW], { 'row-1': { title: 'Title is required.' } })

    const titleInput = screen.getByLabelText('Title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'true')
    const describedBy = titleInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()

    const message = screen.getByText('Title is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
  })

  it('renders no aria-invalid/aria-describedby when the row has no error', () => {
    renderEditor([ROW])

    const titleInput = screen.getByLabelText('Title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'false')
    expect(titleInput).not.toHaveAttribute('aria-describedby')
  })
})
