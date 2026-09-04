import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { TaskCategoryForm } from '@/features/task-categories/task-category-form'
import type { TaskCategoryDetailWithPermissions } from '@/features/task-categories/types'
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

const createTaskCategoryMock = vi.fn()
const updateTaskCategoryMock = vi.fn()

vi.mock('@/features/task-categories/api', () => ({
  createTaskCategory: (...args: unknown[]) => createTaskCategoryMock(...args),
  updateTaskCategory: (...args: unknown[]) => updateTaskCategoryMock(...args),
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

vi.mock('@/features/task-categories/use-task-category-form-meta', () => ({
  useTaskCategoryFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
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

function taskCategory(
  overrides: Partial<TaskCategoryDetailWithPermissions> = {},
): TaskCategoryDetailWithPermissions {
  return {
    id: 9,
    name: 'Commercial',
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
  createTaskCategoryMock.mockReset()
  updateTaskCategoryMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('TaskCategoryForm — create (spec 0101)', () => {
  it('renders the name, description, color and icon controls', () => {
    render(<TaskCategoryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(labelFor('taskCategories.form.name'))).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskCategories.form.description'))).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }),
    ).toBeInTheDocument()
    // The picker trigger takes the field id, so its accessible name is the LABEL;
    // the placeholder is its visible content.
    expect(screen.getByLabelText(labelFor('taskCategories.form.icon'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t(ICON_PLACEHOLDER))).toBeInTheDocument()
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<TaskCategoryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskCategories.form.save') }))

    const nameInput = await screen.findByLabelText(labelFor('taskCategories.form.name'))
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText(i18n.t('taskCategories.form.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createTaskCategoryMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (D-4: the column is NOT NULL)', async () => {
    render(<TaskCategoryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskCategories.form.name')), {
      target: { value: 'Commercial' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskCategories.form.save') }))

    await waitFor(() =>
      expect(screen.getByText(i18n.t('taskCategories.form.colorRequired'))).toBeInTheDocument(),
    )
    expect(createTaskCategoryMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createTaskCategoryMock.mockResolvedValue(taskCategory())
    const onSuccess = vi.fn()

    render(<TaskCategoryForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('taskCategories.form.name')), {
      target: { value: 'Commercial' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskCategories.form.save') }))

    await waitFor(() => expect(createTaskCategoryMock).toHaveBeenCalledTimes(1))
    expect(createTaskCategoryMock).toHaveBeenCalledWith({
      name: 'Commercial',
      color: 'emerald',
      icon: null,
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(taskCategory()))
  })
})

describe('TaskCategoryForm — edit (spec 0101)', () => {
  it('hydrates every field from the loaded detail', () => {
    render(
      <TaskCategoryForm
        mode={{ type: 'edit', taskCategory: taskCategory() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('taskCategories.form.name'))).toHaveValue('Commercial')
    expect(screen.getByLabelText(labelFor('taskCategories.form.description'))).toHaveValue('Follow-up on the client request')
    expect(
      screen.getByRole('button', { name: i18n.t('customFields.colors.blue') }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('taskCategories.form.icon'))).toHaveTextContent('star')
  })

  it('submits only the changed field on a partial update, never sort_order', async () => {
    updateTaskCategoryMock.mockResolvedValue(taskCategory({ name: 'Renamed' }))

    render(
      <TaskCategoryForm
        mode={{ type: 'edit', taskCategory: taskCategory() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskCategories.form.name')), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskCategories.form.save') }))

    await waitFor(() => expect(updateTaskCategoryMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateTaskCategoryMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Renamed' })
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateTaskCategoryMock.mockRejectedValue(
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
      <TaskCategoryForm
        mode={{ type: 'edit', taskCategory: taskCategory() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('taskCategories.form.name')), {
      target: { value: 'Duplicate' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('taskCategories.form.save') }))

    await waitFor(() => expect(screen.getByText('Name already taken.')).toBeInTheDocument())
  })
})
