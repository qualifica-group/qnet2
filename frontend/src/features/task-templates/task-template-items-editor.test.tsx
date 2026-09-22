import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskTemplateItemRowContent } from '@/features/task-templates/task-template-items-editor'
import type { TaskTemplateItemFormRow } from '@/features/task-templates/types'

/**
 * Spec 0124 AC-025: the row status select shows only `meta.group`
 * open/pending options, and a row's validation error is wired with the
 * accessible triad (aria-invalid + aria-describedby + `role="alert"`,
 * frontend.md §10). Spec 0146: exercises `TaskTemplateItemRowContent`
 * directly — the row's own fields, now shared between
 * `<TaskTemplateStagesEditor>`'s stage cards rather than a retired
 * single-container `<SortableList>` wrapper.
 */

const useTaskStatusesForSelectMock = vi.fn()
vi.mock('@/features/task-statuses/for-select-api', () => ({
  useTaskStatusesForSelect: (...args: unknown[]) => useTaskStatusesForSelectMock(...args),
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => null,
}))

// Tiptap itself is covered end to end by rich-text-editor.test.tsx; only the
// WIRING matters here, so a native control stands in — labelled via the same
// `htmlFor`/`id` pair the real component receives through `FormControl`/`id`.
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: {
    id?: string
    value: string | null
    onChange: (html: string | null) => void
    placeholder?: string
    disabled?: boolean
    'aria-invalid'?: boolean
    'aria-describedby'?: string
  }) => (
    <textarea
      id={p.id}
      placeholder={p.placeholder}
      disabled={p.disabled}
      aria-invalid={p['aria-invalid']}
      aria-describedby={p['aria-describedby']}
      value={p.value ?? ''}
      onChange={(event) => p.onChange(event.target.value === '' ? null : event.target.value)}
    />
  ),
}))

const ROW: TaskTemplateItemFormRow = {
  id: 'row-1',
  title: 'Kickoff call',
  description: null,
  estimated_minutes: null,
  task_status_id: null,
  due_offset_days: 0,
  stage_key: null,
}

function renderRow(row: TaskTemplateItemFormRow, errors = {}) {
  const onUpdateRow = vi.fn()
  render(
    <TaskTemplateItemRowContent
      row={row}
      errors={errors}
      stagedFiles={[]}
      onUpdateRow={onUpdateRow}
      onRemove={vi.fn()}
      onAddStagedFiles={vi.fn()}
      onRemoveStagedFile={vi.fn()}
      disabled={false}
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

describe('TaskTemplateItemRowContent — status select filtered to open/pending (AC-025)', () => {
  it('lists only the open/pending options, never in_validation/closed_*', () => {
    renderRow(ROW)

    fireEvent.click(screen.getByRole('combobox', { name: 'Initial status' }))

    expect(screen.getByRole('option', { name: 'Open' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Waiting' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Validating' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Closed' })).not.toBeInTheDocument()
  })

  it('patches the row with the picked status id', () => {
    const onUpdateRow = renderRow(ROW)

    fireEvent.click(screen.getByRole('combobox', { name: 'Initial status' }))
    fireEvent.click(screen.getByRole('option', { name: 'Waiting' }))

    expect(onUpdateRow).toHaveBeenCalledWith('row-1', { task_status_id: 2 })
  })
})

describe('TaskTemplateItemRowContent — accessible error triad (frontend.md §10)', () => {
  it('wires a title error with aria-invalid, aria-describedby and role=alert', () => {
    renderRow(ROW, { title: 'Title is required.' })

    const titleInput = screen.getByLabelText('Title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'true')
    const describedBy = titleInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()

    const message = screen.getByText('Title is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
  })

  it('renders no aria-invalid/aria-describedby when the row has no error', () => {
    renderRow(ROW)

    const titleInput = screen.getByLabelText('Title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'false')
    expect(titleInput).not.toHaveAttribute('aria-describedby')
  })

  it('wires a description error the same way, via its own sr-only label', () => {
    renderRow(ROW, { description: 'Too long.' })

    const descriptionField = screen.getByLabelText('Description')
    expect(descriptionField).toHaveAttribute('aria-invalid', 'true')
    const message = screen.getByText('Too long.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(descriptionField.getAttribute('aria-describedby')).toContain(message.id)
  })
})

/** Spec 0128 AC-024: the row's description field is `RichTextEditor`. */
describe('TaskTemplateItemRowContent — description uses RichTextEditor (AC-024)', () => {
  it('patches the row with the emitted HTML', () => {
    const onUpdateRow = renderRow(ROW)

    fireEvent.change(screen.getByLabelText('Description'), { target: { value: '<p>Nota</p>' } })

    expect(onUpdateRow).toHaveBeenCalledWith('row-1', { description: '<p>Nota</p>' })
  })

  it('patches the row with null when the field is cleared', () => {
    const onUpdateRow = renderRow({ ...ROW, description: '<p>Nota</p>' })

    fireEvent.change(screen.getByLabelText('Description'), { target: { value: '' } })

    expect(onUpdateRow).toHaveBeenCalledWith('row-1', { description: null })
  })
})
