import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AttributeForm } from '@/features/attributes/attribute-form'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: attributes is one of the universal custom-fields rollout
 * modules — mounting `<CustomFieldsSection>` is the ONLY attributes-specific
 * integration. This suite exercises the wiring (schema, defaults, payload,
 * 422) without touching the section's own per-type rendering (covered by
 * `CustomFieldsSection.test.tsx`).
 */

const createAttributeMock = vi.fn()
const updateAttributeMock = vi.fn()

vi.mock('@/features/attributes/api', () => ({
  createAttribute: (...args: unknown[]) => createAttributeMock(...args),
  updateAttribute: (...args: unknown[]) => updateAttributeMock(...args),
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createAttributeMock.mockReset()
  updateAttributeMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('AttributeForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createAttributeMock.mockResolvedValue(attribute())
    const onSuccess = vi.fn()

    render(
      <AttributeForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Code/), { target: { value: 'weight' } })
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Weight' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key attribute' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    const payload = createAttributeMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key attribute' })
  })

  it('seeds the custom field value from the loaded attribute detail in edit mode', async () => {
    render(
      <AttributeForm
        mode={{ type: 'edit', attribute: attribute({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = attribute({ custom_fields: { notes: 'Existing note' } })
    updateAttributeMock.mockResolvedValue(original)

    render(
      <AttributeForm mode={{ type: 'edit', attribute: original }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.change(notes, { target: { value: 'Updated note' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateAttributeMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateAttributeMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { notes: 'Updated note' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateAttributeMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { 'custom_fields.notes': ['Notes must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <AttributeForm
        mode={{ type: 'edit', attribute: attribute({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes must be shorter.')).toBeInTheDocument())
    expect(updateAttributeMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
