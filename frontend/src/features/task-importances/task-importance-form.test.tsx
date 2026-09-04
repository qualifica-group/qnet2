import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { TaskImportanceForm } from '@/features/task-importances/task-importance-form'
import type { TaskImportanceDetailWithPermissions } from '@/features/task-importances/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0101 AC-087: the form composes the SHARED `ColorTokenPicker` and
 * `IconPicker`, so a color is picked as a palette token (never typed as a hex)
 * and an icon as a curated lucide name.
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
 */

const createTaskImportanceMock = vi.fn()
const updateTaskImportanceMock = vi.fn()

vi.mock('@/features/task-importances/api', () => ({
  createTaskImportance: (...args: unknown[]) => createTaskImportanceMock(...args),
  updateTaskImportance: (...args: unknown[]) => updateTaskImportanceMock(...args),
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
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/task-importances/use-task-importance-form-meta', () => ({
  useTaskImportanceFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
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

const COLOR_PLACEHOLDER = 'customFields.form.colorPickerPlaceholder'
const ICON_PLACEHOLDER = 'customFields.form.iconPickerPlaceholder'

function taskImportance(
  overrides: Partial<TaskImportanceDetailWithPermissions> = {},
): TaskImportanceDetailWithPermissions {
  return {
    id: 9,
    name: 'Critical',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
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
  createTaskImportanceMock.mockReset()
  updateTaskImportanceMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('TaskImportanceForm — create (spec 0101)', () => {
  it('renders the name, description, color and icon controls', () => {
    render(<TaskImportanceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(labelFor('taskImportances.form.name'))).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskImportances.form.description'))).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }),
    ).toBeInTheDocument()
    // The picker trigger takes the field id, so its accessible name is the LABEL;
    // the placeholder is its visible content.
    expect(screen.getByLabelText(labelFor('taskImportances.form.icon'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t(ICON_PLACEHOLDER))).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<TaskImportanceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskImportances.form.save') }))

    const nameInput = await screen.findByLabelText(labelFor('taskImportances.form.name'))
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText(i18n.t('taskImportances.form.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createTaskImportanceMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (D-4: the column is NOT NULL)', async () => {
    render(<TaskImportanceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), {
      target: { value: 'Critical' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskImportances.form.save') }))

    await waitFor(() =>
      expect(screen.getByText(i18n.t('taskImportances.form.colorRequired'))).toBeInTheDocument(),
    )
    expect(createTaskImportanceMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createTaskImportanceMock.mockResolvedValue(taskImportance())
    const onSuccess = vi.fn()

    render(<TaskImportanceForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), {
      target: { value: 'Critical' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskImportances.form.save') }))

    await waitFor(() => expect(createTaskImportanceMock).toHaveBeenCalledTimes(1))
    expect(createTaskImportanceMock).toHaveBeenCalledWith({
      name: 'Critical',
      color: 'emerald',
      icon: null,
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(taskImportance()))
  })
})

describe('TaskImportanceForm — edit (spec 0101)', () => {
  it('hydrates every field from the loaded detail', () => {
    render(
      <TaskImportanceForm
        mode={{ type: 'edit', taskImportance: taskImportance() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('taskImportances.form.name'))).toHaveValue('Critical')
    expect(screen.getByLabelText(labelFor('taskImportances.form.description'))).toHaveValue('Follow-up on the client request')
    expect(
      screen.getByRole('button', { name: i18n.t('customFields.colors.blue') }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskImportances.form.icon'))).toHaveTextContent('star')
  })

  it('submits only the changed field on a partial update, never sort_order', async () => {
    updateTaskImportanceMock.mockResolvedValue(taskImportance({ name: 'Renamed' }))

    render(
      <TaskImportanceForm
        mode={{ type: 'edit', taskImportance: taskImportance() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskImportances.form.save') }))

    await waitFor(() => expect(updateTaskImportanceMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateTaskImportanceMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Renamed' })
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateTaskImportanceMock.mockRejectedValue(
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
      <TaskImportanceForm
        mode={{ type: 'edit', taskImportance: taskImportance() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), {
      target: { value: 'Duplicate' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskImportances.form.save') }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
