import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { WorkflowStatusesEditor } from '@/features/quote-workflows/workflow-statuses-editor'
import { WorkflowStatusOption } from '@/features/quote-workflows/workflow-status-option'
import type { WorkflowStatusFormRow } from '@/features/quote-workflows/types'

/**
 * Spec 0047 amendment: a working status carries a `description` and a
 * `requires_note` marker. These pin where the two surface — the configurator
 * editor (editable) and the status picker option (read-only marker).
 */
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const ROWS: WorkflowStatusFormRow[] = [
  {
    id: 'system-open',
    statusId: 1,
    name: 'Open',
    description: 'First take-over of the request',
    color: null,
    group: 'open',
    system_key: 'open',
    requires_note: false,
  },
  {
    id: 'custom-1',
    statusId: 2,
    name: 'Waiting',
    description: null,
    color: 'orange',
    group: 'pending',
    system_key: null,
    requires_note: true,
  },
]

function renderEditor(onUpdateRow = vi.fn(), onMarkValidated = vi.fn()) {
  render(
    <WorkflowStatusesEditor
      rows={ROWS}
      onReorder={vi.fn()}
      onAddCustom={vi.fn()}
      onRemoveCustom={vi.fn()}
      onUpdateRow={onUpdateRow}
      onMarkValidated={onMarkValidated}
    />,
  )
  return onUpdateRow
}

describe('WorkflowStatusesEditor — optional validated mark', () => {
  it('offers the mark on a custom row but never on a mandatory system row', () => {
    renderEditor()

    // One switch only: the 'open' row is mandatory and cannot carry the mark.
    expect(screen.getAllByLabelText('System status "Validated"')).toHaveLength(1)
  })

  it('reports the row the user marks, unchecked by default', () => {
    const onMarkValidated = vi.fn()
    renderEditor(vi.fn(), onMarkValidated)

    const toggle = screen.getByLabelText('System status "Validated"')
    expect(toggle).not.toBeChecked()

    fireEvent.click(toggle)

    expect(onMarkValidated).toHaveBeenCalledWith('custom-1', true)
  })
})

describe('WorkflowStatusesEditor — description and note marker', () => {
  it('renders the persisted description of every row, system rows included', () => {
    renderEditor()

    const descriptions = screen.getAllByLabelText('Status description')
    expect(descriptions).toHaveLength(2)
    expect(descriptions[0]).toHaveValue('First take-over of the request')
    expect(descriptions[1]).toHaveValue('')
  })

  it('patches the row with the typed description, and with null once cleared', () => {
    const onUpdateRow = renderEditor()
    const description = screen.getAllByLabelText('Status description')[1]

    fireEvent.change(description, { target: { value: 'Waiting for the documents' } })
    expect(onUpdateRow).toHaveBeenCalledWith('custom-1', { description: 'Waiting for the documents' })

    // Clearing the row that HAS a description patches it back to null.
    fireEvent.change(screen.getAllByLabelText('Status description')[0], { target: { value: '' } })
    expect(onUpdateRow).toHaveBeenCalledWith('system-open', { description: null })
  })

  it('toggles `requires_note` from the switch and marks the flagged row with the badge', () => {
    const onUpdateRow = renderEditor()

    const switches = screen.getAllByRole('switch', { name: 'Requires an explanatory note' })
    expect(switches[1]).toBeChecked()

    fireEvent.click(switches[0])
    expect(onUpdateRow).toHaveBeenCalledWith('system-open', { requires_note: true })

    // Only the flagged row carries the marker.
    expect(screen.getAllByText('Note required')).toHaveLength(1)
  })
})

describe('WorkflowStatusOption', () => {
  it('shows the description and the note marker of the option', () => {
    render(
      <WorkflowStatusOption
        name="Waiting"
        description="Waiting for the documents"
        color="orange"
        requiresNote
      />,
    )

    expect(screen.getByText('Waiting')).toBeInTheDocument()
    expect(screen.getByText('Waiting for the documents')).toBeInTheDocument()
    expect(screen.getByText('Note required')).toBeInTheDocument()
  })

  it('shows neither when the status has no description and requires no note', () => {
    render(<WorkflowStatusOption name="Open" description={null} color={null} requiresNote={false} />)

    expect(screen.getByText('Open')).toBeInTheDocument()
    expect(screen.queryByText('Note required')).toBeNull()
  })
})
