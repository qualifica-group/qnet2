import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { KanbanColumnShell } from '@/components/kanban/kanban-column-shell'

describe('KanbanColumnShell (spec 0157 D-6)', () => {
  it('renders the accent dot, label and count', () => {
    render(
      <KanbanColumnShell accentColor="bg-blue-500" label="Aperto" count={3}>
        <ul>rows</ul>
      </KanbanColumnShell>,
    )

    expect(screen.getByRole('heading', { name: 'Aperto', level: 3 })).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('renders the optional header extra and add slot only when given', () => {
    const { rerender } = render(
      <KanbanColumnShell accentColor={null} label="Chiuso" count={0}>
        <ul>rows</ul>
      </KanbanColumnShell>,
    )
    expect(screen.queryByText('extra')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()

    rerender(
      <KanbanColumnShell
        accentColor={null}
        label="Chiuso"
        count={0}
        headerExtra={<span>extra</span>}
        addSlot={<button type="button">+</button>}
      >
        <ul>rows</ul>
      </KanbanColumnShell>,
    )
    expect(screen.getByText('extra')).toBeInTheDocument()
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  it('renders the caller-owned children (the row list) as-is', () => {
    render(
      <KanbanColumnShell accentColor={null} label="Oggi" count={1}>
        <ul>
          <li>Task 1</li>
        </ul>
      </KanbanColumnShell>,
    )
    expect(screen.getByText('Task 1')).toBeInTheDocument()
  })
})
