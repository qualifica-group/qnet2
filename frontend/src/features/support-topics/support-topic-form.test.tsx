import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SupportTopicForm } from '@/features/support-topics/support-topic-form'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/** Spec 0334 AC-018: the create form mirrors the server rules and posts the contract payload. */

const createSupportTopicMock = vi.fn()
vi.mock('@/features/support-topics/api', () => ({
  createSupportTopic: (...args: unknown[]) => createSupportTopicMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const USERS = [
  { id: 10, label: 'Mario Rossi' },
  { id: 11, label: 'Lucia Bianchi' },
]

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: resource === 'users' ? USERS : [],
    export_link: null,
    pagination: { total: 2, offset: 0, limit: 25, total_pages: 1, has_more: false },
  })),
}))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const FIELD_KEYS = [
  'name', 'description', 'icon', 'sort_order', 'is_active', 'task_type_id', 'task_category_id',
  'task_priority_id', 'urgent_task_priority_id', 'task_importance_id', 'due_days', 'urgent_due_days',
  'is_private', 'requires_closure_feedback', 'assignee_ids', 'watcher_ids',
]

const ALL_EDITABLE: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: Object.fromEntries(FIELD_KEYS.map((key) => [key, EDITABLE])),
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: ALL_EDITABLE }, isError: false, refetch: vi.fn() }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('supportTopics.form.save') })[0])

/** Opens the people row, picks the user from the async list and confirms the row. */
async function pickPerson(rowKey: string, userLabel: string) {
  openRow(rowKey)
  fireEvent.click(await screen.findByRole('button', { name: new RegExp(`^${i18n.t(rowKey)}`) }))
  fireEvent.click(await screen.findByRole('option', { name: new RegExp(userLabel) }))
  fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
}

function nameTheTopic() {
  openRow('supportTopics.form.name')
  fireEvent.change(screen.getByRole('textbox', { name: new RegExp(`^${i18n.t('supportTopics.form.name')}`) }), {
    target: { value: 'Stampante' },
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createSupportTopicMock.mockReset()
})

describe('SupportTopicForm (create)', () => {
  it('posts the contract payload once an assignee is picked', async () => {
    createSupportTopicMock.mockResolvedValue({ id: 1 })
    const onSuccess = vi.fn()
    render(<SupportTopicForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    nameTheTopic()
    await pickPerson('supportTopics.form.assignees', 'Mario Rossi')
    save()

    await waitFor(() => expect(createSupportTopicMock).toHaveBeenCalledTimes(1))
    expect(createSupportTopicMock).toHaveBeenCalledWith({
      name: 'Stampante',
      description: null,
      icon: null,
      sort_order: 0,
      is_active: true,
      task_type_id: null,
      task_category_id: null,
      task_priority_id: null,
      urgent_task_priority_id: null,
      task_importance_id: null,
      due_days: 2,
      urgent_due_days: null,
      is_private: false,
      requires_closure_feedback: false,
      assignee_ids: [10],
      watcher_ids: [],
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('shows an accessible error and does not call the API without an assignee', async () => {
    render(<SupportTopicForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    nameTheTopic()
    save()

    const alert = await screen.findByText(i18n.t('supportTopics.form.assigneesRequired'))
    expect(alert).toHaveAttribute('role', 'alert')
    expect(createSupportTopicMock).not.toHaveBeenCalled()
  })

  it('does not offer an assignee as watcher, so the overlap cannot be submitted', async () => {
    render(<SupportTopicForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    await pickPerson('supportTopics.form.assignees', 'Mario Rossi')
    openRow('supportTopics.form.watchers')
    fireEvent.click(await screen.findByRole('button', { name: new RegExp(`^${i18n.t('supportTopics.form.watchers')}`) }))

    expect(await screen.findByRole('option', { name: /Lucia Bianchi/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Mario Rossi/ })).not.toBeInTheDocument()
  })
})
