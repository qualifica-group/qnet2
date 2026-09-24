import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import {
  EDITABLE_FIELD,
  FULL_ACCESS_PERMISSIONS,
  taskDetailWithPermissions,
  taskRecurrenceDetail,
} from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskFormMode } from '@/features/tasks/types'

/**
 * Spec 0120 AC-032/AC-034/D-12: the recurrence section's own suite, split out
 * of `task-form-body.test.tsx` (engineering.md §6, file-size split — mirrors
 * `task-complete-dialog.test.tsx`'s own split from `task-detail.test.tsx`).
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

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

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// See `task-form-body.test.tsx` for why the real Tiptap editor is stubbed here too.
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: { value: string | null; onChange: (html: string | null) => void; disabled?: boolean }) => (
    <textarea
      disabled={p.disabled}
      value={p.value ?? ''}
      onChange={(event) => p.onChange(event.target.value === '' ? null : event.target.value)}
    />
  ),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(mode: TaskFormMode, permissions: ResourcePermissions = FULL_ACCESS_PERMISSIONS) {
  return render(
    <ResourcePermissionsProvider permissions={permissions}>
      <TaskFormBody mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

/** Spec 0120 AC-032: closed by default; the master switch alone reveals the fields below it. */
describe('TaskFormBody — recurrence section (AC-032/AC-034)', () => {
  const enableSwitch = () => screen.getByRole('switch', { name: label('tasks.form.recurrence.enable') })
  const frequencyPicker = () => screen.getByRole('combobox', { name: label('tasks.form.recurrence.frequency') })
  const endsPicker = () => screen.getByRole('combobox', { name: label('tasks.form.recurrence.ends') })

  it('hides every field below the switch while disabled', () => {
    renderForm({ type: 'create' })

    expect(enableSwitch()).not.toBeChecked()
    expect(screen.queryByRole('combobox', { name: label('tasks.form.recurrence.frequency') })).not.toBeInTheDocument()
  })

  it('reveals frequency/interval/ends, seeded to a valid rule, on activation', () => {
    renderForm({ type: 'create' })

    fireEvent.click(enableSwitch())

    expect(frequencyPicker()).toHaveTextContent(label('tasks.form.recurrence.frequencyOption.daily'))
    expect(endsPicker()).toHaveTextContent(label('tasks.form.recurrence.endsOption.never'))
    expect(
      screen.queryByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') }),
    ).not.toBeInTheDocument()
  })

  it('shows the weekday checkboxes only for a weekly frequency, and clears them on frequency change (AC-032)', () => {
    renderForm({ type: 'create' })
    fireEvent.click(enableSwitch())

    fireEvent.click(frequencyPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.frequencyOption.weekly') }))

    const monday = screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') })
    fireEvent.click(monday)
    expect(monday).toBeChecked()

    fireEvent.click(frequencyPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.frequencyOption.monthly') }))

    expect(
      screen.queryByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('spinbutton', { name: label('tasks.form.recurrence.monthDay') }),
    ).toBeInTheDocument()

    fireEvent.click(frequencyPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.frequencyOption.weekly') }))

    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') })).not.toBeChecked()
  })

  it('shows the matching field for the picked end mode, and clears the other on change', () => {
    renderForm({ type: 'create' })
    fireEvent.click(enableSwitch())

    fireEvent.click(endsPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.endsOption.on_date') }))
    expect(screen.getByLabelText(label('tasks.form.recurrence.endsOn'))).toBeInTheDocument()

    fireEvent.click(endsPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.endsOption.after_count') }))
    expect(screen.queryByLabelText(label('tasks.form.recurrence.endsOn'))).not.toBeInTheDocument()
    expect(
      screen.getByRole('spinbutton', { name: label('tasks.form.recurrence.occurrenceCount') }),
    ).toBeInTheDocument()
  })

  it('hydrates from the persisted series in edit mode', () => {
    renderForm({
      type: 'edit',
      task: taskDetailWithPermissions({
        recurrence: taskRecurrenceDetail({ frequency: 'weekly', weekdays: [1, 3], ends: 'never' }),
      }),
    })

    expect(enableSwitch()).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.wed') })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.tue') })).not.toBeChecked()
  })

  /** Spec 0155 D-1: month_mode defaults to "fixed" (plain day-of-month input), ordinal is the deliberate extra step. */
  it('picking yearly shows the fixed day/month by default, and switches to the ordinal pickers', () => {
    renderForm({ type: 'create' })
    fireEvent.click(enableSwitch())

    fireEvent.click(frequencyPicker())
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.frequencyOption.yearly') }))

    expect(screen.getByRole('spinbutton', { name: label('tasks.form.recurrence.monthDay') })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: label('tasks.form.recurrence.yearMonth') })).toBeInTheDocument()
    expect(
      screen.queryByRole('combobox', { name: label('tasks.form.recurrence.ordinal') }),
    ).not.toBeInTheDocument()

    const monthModePicker = screen.getByRole('combobox', { name: label('tasks.form.recurrence.monthMode') })
    fireEvent.click(monthModePicker)
    fireEvent.click(screen.getByRole('option', { name: label('tasks.form.recurrence.monthModeOption.ordinal') }))

    expect(screen.queryByRole('spinbutton', { name: label('tasks.form.recurrence.monthDay') })).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: label('tasks.form.recurrence.ordinal') })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: label('tasks.form.recurrence.ordinalWeekday') })).toBeInTheDocument()
  })

  it('shows the workdays-only switch once recurrence is enabled', () => {
    renderForm({ type: 'create' })
    fireEvent.click(enableSwitch())

    expect(screen.getByRole('switch', { name: label('tasks.form.recurrence.workdaysOnly') })).toBeInTheDocument()
  })

  /** Spec 0120 D-12/AC-034: same mechanism `TaskFormBody — protected fields` already proves for other fields. */
  it('locks the whole section for an actor without the mandate (D-12)', () => {
    renderForm(
      { type: 'edit', task: taskDetailWithPermissions({ recurrence: taskRecurrenceDetail() }) },
      {
        resource: FULL_ACCESS_PERMISSIONS.resource,
        fields: { recurrence: { ...EDITABLE_FIELD, editable: false, readonly: true } },
        actions: {},
      },
    )

    expect(enableSwitch()).toBeDisabled()
    expect(frequencyPicker()).toBeDisabled()
  })
})
