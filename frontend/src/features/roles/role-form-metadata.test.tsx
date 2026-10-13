import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criteria 11-16 (spec 0004): the metadata-driven behaviour of the
 * form (hidden fields, readonly/disabled fields, required labels, edit-seeded
 * permissions + 422 mapping, graceful fallback). AC12 and AC15 now live in
 * `role-detail-inline-edit.test.tsx`, the only edit surface (spec 0230).
 * AC14 (gated action affordance) does not apply here — the role form has no action buttons of
 * its own (covered instead by `user-form-metadata.test.tsx`, avatar
 * upload/remove). The roles-CRUD behaviour itself (payload diffing, members
 * hydration, …) is covered by `role-form.test.tsx`.
 */

const createRoleMock = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRoleMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// This suite is not about the spec 0076 permission explorer (covered by
// `permission-explorer/permission-explorer.test.tsx`): an empty catalogue
// keeps the new section out of the way of the 0004 assertions below.
vi.mock('@/features/roles/permission-catalogue-api', () => ({
  fetchPermissionCatalogue: () => Promise.resolve({ areas: [] }),
}))

// Replace the async users multi-select with a lightweight stub so this suite
// focuses on the form's metadata wiring, not the network-backed select
// (covered by its own test).
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value }: { value: number[] }) => (
    <div data-testid="users-value">{value.join(',')}</div>
  ),
}))

/** The `<label>` element whose text starts with `text` (exact-match helper). */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

const findPencilOf = (field: string) =>
  screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRoleMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('RoleForm — metadata-driven authorization (spec 0004, create)', () => {
  it('AC11/13 — hides a hidden field, marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          name: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          permissions: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          users: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // AC11: the hidden field (and everything it would have rendered) is
    // absent from the DOM.
    fireEvent.click(await findPencilOf('Name'))
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByText('Permissions')).not.toBeInTheDocument()
    expect(screen.queryByText('Select all permissions')).not.toBeInTheDocument()

    // AC13: `required` from metadata drives the label's `*` — name is
    // required, the (visible) members field is not.
    expect(labelFor('Name').textContent).toContain('*')
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Members' }) }))
    expect(labelFor('Members').textContent).not.toContain('*')
  })

  it('AC16 — falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          // `name` intentionally absent from the metadata.
          permissions: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <RoleForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // No crash, and the field renders visible + editable (the graceful default).
    fireEvent.click(await findPencilOf('Name'))
    expect(screen.getByLabelText(/^Name/)).toBeEnabled()
  })
})
