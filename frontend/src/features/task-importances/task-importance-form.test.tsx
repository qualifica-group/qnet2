import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TaskImportanceForm } from '@/features/task-importances/task-importance-form'
import type { TaskImportanceDetailWithPermissions } from '@/features/task-importances/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0101 AC-087: the form composes the SHARED `ColorTokenPicker` and
 * `IconPicker`, so a color is picked as a palette token (never typed as a hex)
 * and an icon as a curated lucide name. Wave 3 of the "no edit pages" plan:
 * the form is create-only, a replica of the detail with its rows closed
 * (the in-place edits live in `task-importance-detail.test.tsx`).
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English: the
 * locale catalogue for this module is delivered by its own microtask, and the
 * suite must assert the wiring, not the copy.
 */

const createTaskImportanceMock = vi.fn()

vi.mock('@/features/task-importances/api', () => ({
  createTaskImportance: (...args: unknown[]) => createTaskImportanceMock(...args),
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
    is_default: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: metaPermissions }, isError: false, refetch: vi.fn() }),
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

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('taskImportances.form.save') })[0])

function taskImportance(
  overrides: Partial<TaskImportanceDetailWithPermissions> = {},
): TaskImportanceDetailWithPermissions {
  return {
    id: 9,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    is_default: false,
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
  metaPermissions = ALL_EDITABLE
})

describe('TaskImportanceForm — create (spec 0101)', () => {
  it('starts with every row closed; the name row opens on its own control', () => {
    render(<TaskImportanceForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow('taskImportances.form.name')
    expect(screen.getByLabelText(labelFor('taskImportances.form.name'))).toBeInTheDocument()
  })

  it('opens the shared color and icon pickers from their rows', () => {
    render(<TaskImportanceForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('taskImportances.form.color')
    expect(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) })).toBeInTheDocument()

    openRow('taskImportances.form.icon')
    // The picker trigger takes the field id, so its accessible name is the LABEL;
    // the placeholder is its visible content.
    expect(screen.getByLabelText(labelFor('taskImportances.form.icon'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t(ICON_PLACEHOLDER))).toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    render(<TaskImportanceForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText(i18n.t('taskImportances.form.nameRequired'))).toHaveAttribute('role', 'alert')

    openRow('taskImportances.form.name')
    const nameInput = screen.getByLabelText(labelFor('taskImportances.form.name'))
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createTaskImportanceMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while no palette color is picked (D-4: the column is NOT NULL)', async () => {
    render(<TaskImportanceForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('taskImportances.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), { target: { value: 'High' } })
    save()

    await waitFor(() => expect(screen.getByText(i18n.t('taskImportances.form.colorRequired'))).toBeInTheDocument())
    expect(createTaskImportanceMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with the picked palette token', async () => {
    createTaskImportanceMock.mockResolvedValue(taskImportance())
    const onSuccess = vi.fn()

    render(<TaskImportanceForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('taskImportances.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('taskImportances.form.name')), { target: { value: 'High' } })
    openRow('taskImportances.form.color')
    fireEvent.click(screen.getByRole('button', { name: i18n.t(COLOR_PLACEHOLDER) }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    save()

    await waitFor(() => expect(createTaskImportanceMock).toHaveBeenCalledTimes(1))
    expect(createTaskImportanceMock).toHaveBeenCalledWith({
      name: 'High',
      color: 'emerald',
      icon: null,
      description: null,
      is_active: true,
      is_default: false,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(taskImportance()))
  })
})
