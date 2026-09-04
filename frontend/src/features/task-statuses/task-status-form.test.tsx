import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { TaskStatusForm } from '@/features/task-statuses/task-status-form'
import type { TaskStatusDetailWithPermissions } from '@/features/task-statuses/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0101 AC-087: the form composes the SHARED `ColorTokenPicker` and
 * `IconPicker`, so a color is picked as a palette token (never typed as a hex)
 * and an icon as a curated lucide name. AC-043: a system row keeps only
 * name/color/icon/completion_percentage writable.
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
 */

const createTaskStatusMock = vi.fn()
const updateTaskStatusMock = vi.fn()

vi.mock('@/features/task-statuses/api', () => ({
  createTaskStatus: (...args: unknown[]) => createTaskStatusMock(...args),
  updateTaskStatus: (...args: unknown[]) => updateTaskStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const ALL_EDITABLE: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    description: EDITABLE,
    color: EDITABLE,
    icon: EDITABLE,
    is_active: EDITABLE,
    completion_percentage: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/task-statuses/use-task-status-form-meta', () => ({
  useTaskStatusFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Anchors a translated label so a required field's trailing marker does not break the match. */
function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

/** The completion percentage input, the one field only this configurator has (D-4). */
function percentageInput(): HTMLElement {
  return screen.getByLabelText(labelFor('taskStatuses.form.completionPercentage'))
}

const COLOR_PLACEHOLDER = 'customFields.form.colorPickerPlaceholder'
const ICON_PLACEHOLDER = 'customFields.form.iconPickerPlaceholder'

function taskStatus(
  overrides: Partial<TaskStatusDetailWithPermissions> = {},
): TaskStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'In progress',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    system_key: null,
    completion_percentage: 25,
    created_at: null,
    updated_at: null,
    permissions: ALL_EDITABLE,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createTaskStatusMock.mockReset()
  updateTaskStatusMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('TaskStatusForm — create (spec 0101)', () => {
  it('renders the name, description, color and icon controls', () => {
    render(<TaskStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(labelFor('taskStatuses.form.name'))).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskStatuses.form.description'))).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }),
    ).toBeInTheDocument()
    // The picker trigger takes the field id, so its accessible name is the LABEL;
    // the placeholder is its visible content.
    expect(screen.getByLabelText(labelFor('taskStatuses.form.icon'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t(ICON_PLACEHOLDER))).toBeInTheDocument()
    expect(percentageInput()).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<TaskStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    const nameInput = await screen.findByLabelText(labelFor('taskStatuses.form.name'))
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText(i18n.t('taskStatuses.form.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createTaskStatusMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (D-4: the column is NOT NULL)', async () => {
    render(<TaskStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskStatuses.form.name')), {
      target: { value: 'In progress' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    await waitFor(() =>
      expect(screen.getByText(i18n.t('taskStatuses.form.colorRequired'))).toBeInTheDocument(),
    )
    expect(createTaskStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createTaskStatusMock.mockResolvedValue(taskStatus())
    const onSuccess = vi.fn()

    render(<TaskStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskStatuses.form.name')), {
      target: { value: 'In progress' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    await waitFor(() => expect(createTaskStatusMock).toHaveBeenCalledTimes(1))
    expect(createTaskStatusMock).toHaveBeenCalledWith({
      name: 'In progress',
      color: 'emerald',
      icon: null,
      description: null,
      is_active: true,
      completion_percentage: 0,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(taskStatus()))
  })
})

describe('TaskStatusForm — edit (spec 0101)', () => {
  it('hydrates every field from the loaded detail', () => {
    render(
      <TaskStatusForm
        mode={{ type: 'edit', taskStatus: taskStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('taskStatuses.form.name'))).toHaveValue('In progress')
    expect(screen.getByLabelText(labelFor('taskStatuses.form.description'))).toHaveValue('Follow-up on the client request')
    expect(
      screen.getByRole('button', { name: i18n.t('customFields.colors.blue') }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskStatuses.form.icon'))).toHaveTextContent('star')
    expect(percentageInput()).toHaveValue(25)
  })

  it('submits only the changed field on a partial update, never sort_order', async () => {
    updateTaskStatusMock.mockResolvedValue(taskStatus({ name: 'Renamed' }))

    render(
      <TaskStatusForm
        mode={{ type: 'edit', taskStatus: taskStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskStatuses.form.name')), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    await waitFor(() => expect(updateTaskStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateTaskStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Renamed' })
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateTaskStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { name: ['Name already taken.'] },
        },
      } as never),
    )

    render(
      <TaskStatusForm
        mode={{ type: 'edit', taskStatus: taskStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskStatuses.form.name')), {
      target: { value: 'Duplicate' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })

  it('locks description and the active switch on a system row (AC-043)', () => {
    render(
      <TaskStatusForm
        mode={{ type: 'edit', taskStatus: taskStatus({ system_key: 'closed_positive' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('taskStatuses.form.description'))).toBeDisabled()
    expect(screen.getByRole('switch')).toBeDisabled()
    // name / color / icon / completion_percentage stay writable (MUTABLE_SYSTEM_FIELDS).
    expect(screen.getByLabelText(labelFor('taskStatuses.form.name'))).toBeEnabled()
    expect(percentageInput()).toBeEnabled()
  })

  it('sends the new percentage of a system row, so every task in it follows (AC-021/AC-044)', async () => {
    updateTaskStatusMock.mockResolvedValue(
      taskStatus({ system_key: 'closed_negative', completion_percentage: 10 }),
    )

    render(
      <TaskStatusForm
        mode={{ type: 'edit', taskStatus: taskStatus({ system_key: 'closed_negative', completion_percentage: 0 }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(percentageInput(), { target: { value: '10' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskStatuses.form.save') }))

    await waitFor(() => expect(updateTaskStatusMock).toHaveBeenCalledTimes(1))
    expect(updateTaskStatusMock.mock.calls[0][1]).toEqual({ completion_percentage: 10 })
  })
})
