import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { KanbanCardShell } from '@/components/kanban/kanban-card-shell'

describe('KanbanCardShell (spec 0157 D-6)', () => {
  it('renders the title, badges and footer slots', () => {
    render(
      <KanbanCardShell
        cardRef={() => {}}
        title="Task 1"
        onTitleClick={vi.fn()}
        badges={<span>badge</span>}
        footer={<span>footer</span>}
      />,
    )

    expect(screen.getByRole('button', { name: 'Task 1' })).toBeInTheDocument()
    expect(screen.getByText('badge')).toBeInTheDocument()
    expect(screen.getByText('footer')).toBeInTheDocument()
  })

  it('fires onTitleClick when the title is clicked', () => {
    const onTitleClick = vi.fn()
    render(
      <KanbanCardShell
        cardRef={() => {}}
        title="Task 1"
        onTitleClick={onTitleClick}
        badges={null}
        footer={null}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Task 1' }))
    expect(onTitleClick).toHaveBeenCalledTimes(1)
  })

  it('renders an optional description only when given', () => {
    const { rerender } = render(
      <KanbanCardShell cardRef={() => {}} title="Task 1" onTitleClick={vi.fn()} badges={null} footer={null} />,
    )
    expect(screen.queryByText('Some excerpt')).not.toBeInTheDocument()

    rerender(
      <KanbanCardShell
        cardRef={() => {}}
        title="Task 1"
        onTitleClick={vi.fn()}
        description="Some excerpt"
        badges={null}
        footer={null}
      />,
    )
    expect(screen.getByText('Some excerpt')).toBeInTheDocument()
  })

  it('renders the drag handle and selection slots only when given', () => {
    render(
      <KanbanCardShell
        cardRef={() => {}}
        title="Task 1"
        onTitleClick={vi.fn()}
        dragHandle={<button type="button" aria-label="drag">::</button>}
        selection={<input type="checkbox" aria-label="select" />}
        badges={null}
        footer={null}
      />,
    )
    expect(screen.getByRole('button', { name: 'drag' })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'select' })).toBeInTheDocument()
  })

  it('fires onCardClick on the whole card, only when given', () => {
    const onCardClick = vi.fn()
    render(
      <KanbanCardShell
        cardRef={() => {}}
        title="Task 1"
        onTitleClick={vi.fn()}
        onCardClick={onCardClick}
        badges={<span>badge</span>}
        footer={null}
      />,
    )

    fireEvent.click(screen.getByText('badge'))
    expect(onCardClick).toHaveBeenCalledTimes(1)
  })
})
