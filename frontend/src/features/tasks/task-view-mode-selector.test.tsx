import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskViewModeSelector } from '@/features/tasks/task-view-mode-selector'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('TaskViewModeSelector (spec 0157 D-5)', () => {
  it('switches Analitica/Sintetica/Kanban on click', () => {
    const onViewModeChange = vi.fn()
    render(
      <TaskViewModeSelector
        viewMode="analytic"
        onViewModeChange={onViewModeChange}
        kanbanMode="status"
        onKanbanModeChange={vi.fn()}
      />,
    )

    // Radix `TabsTrigger` activates on `mouseDown`, not `click`.
    fireEvent.mouseDown(screen.getByRole('tab', { name: /Sintetica/ }))
    expect(onViewModeChange).toHaveBeenCalledWith('synthetic')
  })

  it('shows the kanban sub-selector (per stato/per scadenza) only when Kanban is selected', () => {
    const { rerender } = render(
      <TaskViewModeSelector
        viewMode="analytic"
        onViewModeChange={vi.fn()}
        kanbanMode="status"
        onKanbanModeChange={vi.fn()}
      />,
    )
    expect(screen.queryByRole('tab', { name: 'Per scadenza' })).not.toBeInTheDocument()

    rerender(
      <TaskViewModeSelector
        viewMode="kanban"
        onViewModeChange={vi.fn()}
        kanbanMode="status"
        onKanbanModeChange={vi.fn()}
      />,
    )
    expect(screen.getByRole('tab', { name: 'Per scadenza' })).toBeInTheDocument()
  })
})
