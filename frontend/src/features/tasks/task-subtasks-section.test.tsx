import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskSubtasksSection } from '@/features/tasks/task-subtasks-section'
import type { TaskSubtask } from '@/features/tasks/types'

/** Abilities granted to the actor under test; rewritten per test. */
let granted: string[] = []

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => granted.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const label = (key: string) => i18n.t(key)

function subtask(overrides: Partial<TaskSubtask> = {}): TaskSubtask {
  return {
    id: 101,
    title: 'Preparare il preventivo',
    task_status: { id: 2, name: 'Aperto', color: 'amber', icon: null },
    completion_percentage: 0,
    assignees: [{ id: 31, name: 'Dario Dini' }],
    ...overrides,
  }
}

function renderSection(subtasks: TaskSubtask[], handlers: { onOpen?: () => void; onCreate?: () => void } = {}) {
  const onOpen = vi.fn(handlers.onOpen)
  const onCreate = vi.fn(handlers.onCreate)
  render(<TaskSubtasksSection subtasks={subtasks} onOpen={onOpen} onCreate={onCreate} />)
  return { onOpen, onCreate }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = ['tasks.view', 'tasks.create']
})

/**
 * AC-085. The section reads `data.subtasks`, already loaded with the parent
 * detail (D-12): there is no endpoint of its own, so nothing here fetches.
 */
describe('TaskSubtasksSection — listing (AC-085)', () => {
  it('lists every child already carried by the parent detail', () => {
    renderSection([subtask(), subtask({ id: 102, title: 'Inviare la conferma' })])

    expect(screen.getByRole('button', { name: 'Preparare il preventivo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Inviare la conferma' })).toBeInTheDocument()
  })

  it('renders the child status badge and its own derived percentage', () => {
    renderSection([subtask({ completion_percentage: 75 })])

    expect(screen.getByText('Aperto')).toBeInTheDocument()
    expect(
      screen.getByRole('progressbar', { name: label('tasks.detail.completionPercentage') }),
    ).toHaveAttribute('aria-valuenow', '75')
  })

  it('shows an empty state rather than an empty list', () => {
    renderSection([])

    expect(screen.getByText(label('tasks.detail.subtasksEmpty'))).toBeInTheDocument()
    expect(screen.queryByRole('listitem')).not.toBeInTheDocument()
  })
})

describe('TaskSubtasksSection — opening a child (AC-085)', () => {
  it('opens the child detail by its own id', () => {
    const { onOpen } = renderSection([subtask({ id: 102 })])

    fireEvent.click(screen.getByRole('button', { name: 'Preparare il preventivo' }))

    expect(onOpen).toHaveBeenCalledWith(102)
  })

  it('renders the title as plain text, not an affordance, without tasks.view', () => {
    granted = ['tasks.create']
    renderSection([subtask()])

    expect(screen.queryByRole('button', { name: 'Preparare il preventivo' })).not.toBeInTheDocument()
    expect(screen.getByText('Preparare il preventivo')).toBeInTheDocument()
  })
})

describe('TaskSubtasksSection — creating a child (AC-085)', () => {
  it('offers "crea sotto-task" and delegates the prefill to the caller', () => {
    const { onCreate } = renderSection([])

    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.createSubtask') }))

    expect(onCreate).toHaveBeenCalledTimes(1)
  })

  it('hides the action without tasks.create', () => {
    granted = ['tasks.view']
    renderSection([])

    expect(
      screen.queryByRole('button', { name: label('tasks.detail.createSubtask') }),
    ).not.toBeInTheDocument()
  })
})
