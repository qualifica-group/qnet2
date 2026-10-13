import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AttributeForm } from '@/features/attributes/attribute-form'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021/0240: attributes is one of the universal custom-fields rollout
 * modules — mounting the resource's custom-field rows is the ONLY attributes-specific
 * integration. This suite exercises the wiring (schema, defaults, payload,
 * 422) without touching the section's own per-type rendering (covered by
 * `CustomFieldsSection.test.tsx`).
 */

const createAttributeMock = vi.fn()

vi.mock('@/features/attributes/api', () => ({
  createAttribute: (...args: unknown[]) => createAttributeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: [], isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: [], isLoading: false, isError: false }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.notes': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** The form renders once `GET /meta/attributes` resolves: wait for the first row. */
async function renderForm(ui: ReactElement) {
  render(ui, { wrapper: wrapper() })
  await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Name' }) })
}

function attribute(
  overrides: Partial<AttributeDetailWithPermissions> = {},
): AttributeDetailWithPermissions {
  return {
    id: 3,
    code: 'weight',
    name: 'Weight',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    options: [],
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

const openRow = (field: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createAttributeMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('AttributeForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />)

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createAttributeMock.mockResolvedValue(attribute())
    const onSuccess = vi.fn()

    await renderForm(<AttributeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />)

    openRow('Code')
    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'weight' } })
    openRow('Name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Weight' } })
    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Key attribute' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    const payload = createAttributeMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key attribute' })
  })
})
