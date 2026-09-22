import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ComponentProps } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UNASSIGNED_STAGE_CONTAINER_ID } from '@/features/task-templates/task-template-item-stage-grouping'
import { TaskTemplateStagesEditor } from '@/features/task-templates/task-template-stages-editor'
import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'

/**
 * Spec 0146 AC-031: adding/renaming/removing/reordering fasi, and moving an
 * item between fasi via its accessible "Fase" select (the keyboard-operable
 * equivalent of a cross-fase pointer drop — dnd-kit's default keyboard
 * coordinate getter only reorders WITHIN one `SortableContext`).
 */

vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => null }))
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: { id?: string; value: string | null; onChange: (html: string | null) => void }) => (
    <textarea id={p.id} value={p.value ?? ''} onChange={(event) => p.onChange(event.target.value || null)} />
  ),
}))
vi.mock('@/features/task-statuses/for-select-api', () => ({
  useTaskStatusesForSelect: () => ({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
    fetchNextPage: vi.fn(),
  }),
}))

function stageRow(overrides: Partial<TaskTemplateStageFormRow> = {}): TaskTemplateStageFormRow {
  return { id: 'stage-1', name: 'Analisi', ...overrides }
}

function itemRow(overrides: Partial<TaskTemplateItemFormRow> = {}): TaskTemplateItemFormRow {
  return {
    id: 'row-1',
    title: 'Kickoff call',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
    stage_key: null,
    ...overrides,
  }
}

function renderEditor(props: Partial<ComponentProps<typeof TaskTemplateStagesEditor>> = {}) {
  const handlers = {
    onAddStage: vi.fn(),
    onRenameStage: vi.fn(),
    onRemoveStage: vi.fn(),
    onReorderStages: vi.fn(),
    onAddItem: vi.fn(),
    onUpdateItem: vi.fn(),
    onRemoveItem: vi.fn(),
    onMoveItem: vi.fn(),
    onAddStagedFiles: vi.fn(),
    onRemoveStagedFile: vi.fn(),
  }
  render(
    <TaskTemplateStagesEditor
      itemRows={[]}
      stageRows={[]}
      itemErrors={{}}
      stageErrors={{}}
      stagedFilesByRow={{}}
      {...handlers}
      {...props}
    />,
  )
  return handlers
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('TaskTemplateStagesEditor — add/rename/remove (AC-031)', () => {
  it('fires onAddStage when "Add phase" is clicked', () => {
    const handlers = renderEditor()

    fireEvent.click(screen.getByRole('button', { name: 'Add phase' }))

    expect(handlers.onAddStage).toHaveBeenCalledOnce()
  })

  it('fires onRenameStage with the typed name', () => {
    const handlers = renderEditor({ stageRows: [stageRow()] })

    fireEvent.change(screen.getByRole('textbox', { name: 'Phase name…' }), { target: { value: 'Analisi tecnica' } })

    expect(handlers.onRenameStage).toHaveBeenCalledWith('stage-1', 'Analisi tecnica')
  })

  it('fires onRemoveStage when the stage remove button is clicked', () => {
    const handlers = renderEditor({ stageRows: [stageRow()] })

    fireEvent.click(screen.getByRole('button', { name: 'Remove phase' }))

    expect(handlers.onRemoveStage).toHaveBeenCalledWith('stage-1')
  })

  it('always renders a trailing "Senza fase" group', () => {
    renderEditor({ stageRows: [stageRow()] })

    expect(screen.getByText('No phase')).toBeInTheDocument()
  })
})

describe('TaskTemplateStagesEditor — moving an item between fasi via the accessible select (AC-031)', () => {
  it('moves an item from "Senza fase" into a stage', () => {
    const handlers = renderEditor({
      stageRows: [stageRow()],
      itemRows: [itemRow({ stage_key: null })],
    })

    const moveSelect = screen.getByRole('combobox', { name: 'Phases' })
    fireEvent.click(moveSelect)
    fireEvent.click(screen.getByRole('option', { name: 'Analisi' }))

    expect(handlers.onMoveItem).toHaveBeenCalledWith('row-1', 'stage-1', 0)
  })

  it('moves an item back to "Senza fase"', () => {
    const handlers = renderEditor({
      stageRows: [stageRow()],
      itemRows: [itemRow({ stage_key: 'stage-1' })],
    })

    const moveSelect = screen.getByRole('combobox', { name: 'Phases' })
    fireEvent.click(moveSelect)
    fireEvent.click(screen.getByRole('option', { name: 'No phase' }))

    expect(handlers.onMoveItem).toHaveBeenCalledWith('row-1', UNASSIGNED_STAGE_CONTAINER_ID, 0)
  })
})

describe('TaskTemplateStagesEditor — read-only (D-9 mirrors is_read_only-style disabling)', () => {
  it('disables the add-phase/add-row buttons and the move select', () => {
    renderEditor({ stageRows: [stageRow()], itemRows: [itemRow({ stage_key: 'stage-1' })], disabled: true })

    expect(screen.getByRole('button', { name: 'Add phase' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Add row' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Phases' })).toBeDisabled()
  })
})
