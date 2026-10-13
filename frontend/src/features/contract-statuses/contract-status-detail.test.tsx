import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ContractStatusDetailView } from '@/features/contract-statuses/contract-status-detail'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'

/**
 * The detail shows the record's fields; every row but the server-managed order
 * edits in place (spec 0235), each save a PATCH of that field alone. The
 * Activity Log gate is exercised on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updateContractStatusMock = vi.fn()

vi.mock('@/features/contract-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contract-statuses/api')>()),
  updateContractStatus: (...args: unknown[]) => updateContractStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function contractStatus(overrides: Partial<ContractStatusDetailWithPermissions> = {}): ContractStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Active',
    description: 'Contract in force',
    color: 'blue',
    sort_order: 3,
    is_active: true,
    is_default: false,
    system_key: null,
    group: 'open',
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
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

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateContractStatusMock.mockReset()
})

describe('ContractStatusDetailView — detail fields', () => {
  it('shows the fields and the timestamps', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    expect(screen.getByRole('heading', { name: 'Active' })).toBeInTheDocument()
    expect(screen.getByText('Contract in force')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('Open')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })
})

describe('ContractStatusDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <ContractStatusDetailView
        contractStatus={contractStatus({
          permissions: { ...contractStatus().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'contract-statuses', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('ContractStatusDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.isActive')).toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.isDefault')).toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.group.label')).toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updateContractStatusMock.mockResolvedValue(contractStatus({ name: 'Renamed' }))
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    fireEvent.click(pencilOf('contractStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateContractStatusMock).toHaveBeenCalledWith(7, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    fireEvent.click(pencilOf('contractStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateContractStatusMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateContractStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<ContractStatusDetailView contractStatus={contractStatus()} />)

    fireEvent.click(pencilOf('contractStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = contractStatus()
    render(
      <ContractStatusDetailView
        contractStatus={contractStatus({
          permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } },
        })}
      />,
    )

    expect(pencilOf('contractStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.color')).not.toBeInTheDocument()
  })

  it('keeps every field but name and color fixed on a system row', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus({ system_key: 'suspended' })} />)

    expect(pencilOf('contractStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.color')).toBeInTheDocument()
    for (const key of ['description', 'group.label', 'isActive', 'isDefault']) {
      expect(pencilOf(`contractStatuses.form.${key}`)).not.toBeInTheDocument()
    }
  })

  it('locks only is_default on the current default (BR-5), not is_active or group', () => {
    render(<ContractStatusDetailView contractStatus={contractStatus({ is_default: true })} />)

    expect(pencilOf('contractStatuses.form.isDefault')).not.toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.isActive')).toBeInTheDocument()
    expect(pencilOf('contractStatuses.form.group.label')).toBeInTheDocument()
  })

  it('BR-5: refuses to deactivate the default row, with the error on the open row', async () => {
    render(<ContractStatusDetailView contractStatus={contractStatus({ is_default: true })} />)

    fireEvent.click(pencilOf('contractStatuses.form.isActive') as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /Active/ }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText(i18n.t('contractStatuses.form.defaultRequiresActive'))).toBeInTheDocument()
    expect(updateContractStatusMock).not.toHaveBeenCalled()
  })
})
