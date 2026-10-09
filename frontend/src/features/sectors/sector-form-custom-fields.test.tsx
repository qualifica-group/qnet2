import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SectorForm } from '@/features/sectors/sector-form'
import type { SectorDetailWithPermissions, SectorTreeNode } from '@/features/sectors/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: the sectors module wires the universal custom-fields renderer
 * (mirrors `company-form-custom-fields.test.tsx`) — mounting
 * `<CustomFieldsSection>` is the only sectors-specific integration.
 */

const createSectorMock = vi.fn()
const updateSectorMock = vi.fn()
const fetchSectorTreeMock = vi.fn<() => Promise<SectorTreeNode[]>>()

vi.mock('@/features/sectors/api', () => ({
  createSector: (...args: unknown[]) => createSectorMock(...args),
  updateSector: (...args: unknown[]) => updateSectorMock(...args),
  fetchSectorTree: () => fetchSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

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

function sector(overrides: Partial<SectorDetailWithPermissions> = {}): SectorDetailWithPermissions {
  return {
    id: 4,
    code: null,
    name: 'Applications',
    parent_id: null,
    parent: null,
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsWithNotes(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createSectorMock.mockReset()
  updateSectorMock.mockReset()
  fetchSectorTreeMock.mockReset()
  fetchSectorTreeMock.mockResolvedValue([])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('SectorForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createSectorMock.mockResolvedValue(sector())
    const onSuccess = vi.fn()

    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Applications' } })
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key sector' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createSectorMock).toHaveBeenCalledTimes(1))
    const payload = createSectorMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key sector' })
  })

  it('seeds the custom field value from the loaded sector detail in edit mode', async () => {
    render(
      <SectorForm
        mode={{ type: 'edit', sector: sector({ custom_fields: { notes: 'Existing note' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })
})
