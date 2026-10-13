import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ReferentTypeDetailView } from '@/features/referent-types/referent-type-detail'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the detail shows the name (hero title), the custom fields and
 * the creation date; every row edits in place (wave 3 of the "no edit pages"
 * plan), each save a PATCH of that field alone. The Activity Log gate is
 * exercised directly on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updateReferentTypeMock = vi.fn()

vi.mock('@/features/referent-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/referent-types/api')>()),
  updateReferentType: (...args: unknown[]) => updateReferentTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

const EDITABLE = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

function permissions(
  overrides: Partial<ResourcePermissions> = {},
): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: { 'custom.notes': EDITABLE },
    actions: { view_activity: false },
    ...overrides,
  }
}

function referentType(overrides: Partial<ReferentTypeDetailWithPermissions> = {}): ReferentTypeDetailWithPermissions {
  return {
    id: 9,
    name: 'Sponsor',
    created_at: '2026-01-01T09:00:00Z',
    custom_fields: { notes: 'Existing note' },
    permissions: permissions(),
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (label: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })

const NAME_LABEL = () => i18n.t('referentTypes.form.name')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateReferentTypeMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('ReferentTypeDetailView — detail fields', () => {
  it('shows the name, the custom field value and the creation date', async () => {
    render(<ReferentTypeDetailView referentType={referentType()} />)

    expect(screen.getByRole('heading', { name: 'Sponsor' })).toBeInTheDocument()
    expect(await screen.findByText('Existing note')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })
})

describe('ReferentTypeDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <ReferentTypeDetailView referentType={referentType({ permissions: permissions({ actions: { view_activity: true } }) })} />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'referent-types', id: 9 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<ReferentTypeDetailView referentType={referentType()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('ReferentTypeDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', async () => {
    render(<ReferentTypeDetailView referentType={referentType()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf(NAME_LABEL())).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateReferentTypeMock.mockResolvedValue(referentType({ name: 'Renamed' }))
    render(<ReferentTypeDetailView referentType={referentType()} />)

    fireEvent.click(pencilOf(NAME_LABEL()) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateReferentTypeMock).toHaveBeenCalledWith(9, { name: 'Renamed' }))
  })

  it('a custom field row saves only that custom field', async () => {
    updateReferentTypeMock.mockResolvedValue(referentType({ custom_fields: { notes: 'Updated note' } }))
    render(<ReferentTypeDetailView referentType={referentType()} />)

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Updated note' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateReferentTypeMock).toHaveBeenCalledWith(9, { custom_fields: { notes: 'Updated note' } }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<ReferentTypeDetailView referentType={referentType()} />)

    fireEvent.click(pencilOf(NAME_LABEL()) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateReferentTypeMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateReferentTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<ReferentTypeDetailView referentType={referentType()} />)

    fireEvent.click(pencilOf(NAME_LABEL()) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', async () => {
    render(
      <ReferentTypeDetailView
        referentType={referentType({
          permissions: permissions({
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: { 'custom.notes': { ...EDITABLE, editable: false, readonly: true } },
          }),
        })}
      />,
    )

    expect(pencilOf(NAME_LABEL())).not.toBeInTheDocument()
    await screen.findByText('Existing note')
    expect(pencilOf('Notes')).not.toBeInTheDocument()
  })

  it('offers no pencil on a readonly field', () => {
    render(
      <ReferentTypeDetailView
        referentType={referentType({
          permissions: permissions({
            fields: {
              name: { visible: true, hidden: false, editable: false, readonly: true, required: true, disabled: false },
            },
          }),
        })}
      />,
    )

    expect(pencilOf(NAME_LABEL())).not.toBeInTheDocument()
  })
})
