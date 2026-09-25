import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormSubtasksSection } from '@/features/tasks/task-form-subtasks-section'
import { FULL_ACCESS_PERMISSIONS, taskFormValues } from '@/features/tasks/task-fixtures'
import { MAX_TASK_FORM_SUBTASKS, type TaskFormValues } from '@/features/tasks/task-schema'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** One `useForm<TaskFormValues>` instance feeding the section, mirroring how `TaskFormBody` wires it. */
function Harness({ defaultValues }: { defaultValues: TaskFormValues }) {
  const form = useForm<TaskFormValues>({ defaultValues })
  return (
    <Form {...form}>
      <TaskFormSubtasksSection control={form.control} />
    </Form>
  )
}

function renderSection(defaultValues: TaskFormValues = taskFormValues()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <Harness defaultValues={defaultValues} />
      </ResourcePermissionsProvider>
    </QueryClientProvider>,
  )
}

const label = (key: string) => i18n.t(key)
const titleInputs = () => screen.getAllByRole('textbox', { name: label('tasks.form.subtasks.title') })
const addButtons = () => screen.getAllByRole('button', { name: label('tasks.form.subtasks.add') })
const removeButtons = () => screen.getAllByRole('button', { name: label('tasks.form.subtasks.remove') })

/** 49 already-filled root rows, no children: one short of the 50-node cap. */
function rootRowsAtCapMinusOne(): TaskFormValues['subtasks'] {
  return Array.from({ length: MAX_TASK_FORM_SUBTASKS - 1 }, (_unused, index) => ({
    title: `Sotto-task ${index}`,
    end_date: null,
    assignee_ids: [],
    subtasks: [],
  }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

/** Spec 0161 D-1/AC-007: figlio -> nipote -> pronipote, no "add child" button past the 3rd level. */
describe('TaskFormSubtasksSection — depth (spec 0161 D-1)', () => {
  it('adds a row at each of the 3 levels and shows no add control past the 3rd', () => {
    renderSection()
    expect(addButtons()).toHaveLength(1)

    // Level 1 (figlio): the section's own "Aggiungi sotto-task".
    fireEvent.click(addButtons()[0])
    expect(titleInputs()).toHaveLength(1)
    expect(addButtons()).toHaveLength(2)

    // Level 2 (nipote): the figlio row's own add button, first in DOM order.
    fireEvent.click(addButtons()[0])
    expect(titleInputs()).toHaveLength(2)
    expect(addButtons()).toHaveLength(3)

    // Level 3 (pronipote): the nipote row's own add button.
    fireEvent.click(addButtons()[1])
    expect(titleInputs()).toHaveLength(3)
    // No 4th level: the pronipote row contributes no add button of its own.
    expect(addButtons()).toHaveLength(3)
  })
})

/** Spec 0161 D-1: removing a row removes its whole subtree. */
describe('TaskFormSubtasksSection — recursive removal (spec 0161 D-1)', () => {
  it('removing the level-1 row also removes its level-2/level-3 children', () => {
    renderSection()
    fireEvent.click(addButtons()[0])
    fireEvent.click(addButtons()[0])
    fireEvent.click(addButtons()[1])
    expect(titleInputs()).toHaveLength(3)

    fireEvent.click(removeButtons()[0])

    expect(screen.queryAllByRole('textbox', { name: label('tasks.form.subtasks.title') })).toHaveLength(0)
  })

  it('removing a level-2 row removes only its own level-3 child, the level-1 row stays', () => {
    renderSection()
    fireEvent.click(addButtons()[0])
    fireEvent.click(addButtons()[0])
    fireEvent.click(addButtons()[1])
    expect(titleInputs()).toHaveLength(3)

    fireEvent.click(removeButtons()[1])

    expect(titleInputs()).toHaveLength(1)
  })
})

/** Spec 0161 D-1: the 50-node cap counted across the whole tree gates every "add child" button, not just the root's. */
describe('TaskFormSubtasksSection — 50-node cap (spec 0161 D-1)', () => {
  it('disables the add button once the tree reaches 50 nodes', () => {
    renderSection(taskFormValues({ subtasks: rootRowsAtCapMinusOne() }))
    expect(addButtons()[0]).not.toBeDisabled()

    fireEvent.click(addButtons()[0])

    expect(addButtons()[0]).toBeDisabled()
  })
})
