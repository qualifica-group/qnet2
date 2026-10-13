import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PurchaseRequestPriorityDetailView } from '@/features/purchase-request-priorities/purchase-request-priority-detail'
import type { PurchaseRequestPriorityDetailWithPermissions } from '@/features/purchase-request-priorities/types'

/**
 * Spec 0247: the detail shows the name (hero title), description, the palette
 * color as its localized token name, the icon as its canonical lucide name,
 * the server-managed order and the active flag; every row but the order edits
 * in place (wave 3 of the "no edit pages" plan), each save a PATCH of that
 * field alone. The Activity Log gate is exercised directly on the
 * `permissions.actions.view_activity` prop.
 *
 * The fixture values deliberately never read like a field LABEL: the detail
 * renders label and value as siblings, so a `description` of "Description"
 * makes `getByText` ambiguous the moment the locale bundle resolves the
 * label to the same word.
 */

const activityLogSectionMock = vi.fn()
const updatePurchaseRequestPriorityMock = vi.fn()

vi.mock('@/features/purchase-request-priorities/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-request-priorities/api')>()),
  updatePurchaseRequestPriority: (...args: unknown[]) => updatePurchaseRequestPriorityMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function purchaseRequestPriority(
  overrides: Partial<PurchaseRequestPriorityDetailWithPermissions> = {},
): PurchaseRequestPriorityDetailWithPermissions {
  return {
    id: 4,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    is_default: false,
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
  updatePurchaseRequestPriorityMock.mockReset()
})

describe('PurchaseRequestPriorityDetailView — detail fields', () => {
  it('shows the name, description, color token, icon, order and both timestamps', () => {
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    expect(screen.getByRole('heading', { name: 'High' })).toBeInTheDocument()
    expect(screen.getByText('Follow-up on the client request')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('star')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description and an unset icon', () => {
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority({ description: null, icon: null })} />)

    expect(screen.getAllByText('—')).toHaveLength(2)
  })
})

describe('PurchaseRequestPriorityDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <PurchaseRequestPriorityDetailView
        purchaseRequestPriority={purchaseRequestPriority({
          permissions: { ...purchaseRequestPriority().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'purchase-request-priorities', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('PurchaseRequestPriorityDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', () => {
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('purchaseRequestPriorities.form.name')).toBeInTheDocument()
    expect(pencilOf('purchaseRequestPriorities.form.isDefault')).toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updatePurchaseRequestPriorityMock.mockResolvedValue(purchaseRequestPriority({ name: 'Renamed' }))
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    fireEvent.click(pencilOf('purchaseRequestPriorities.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updatePurchaseRequestPriorityMock).toHaveBeenCalledWith(4, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    fireEvent.click(pencilOf('purchaseRequestPriorities.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updatePurchaseRequestPriorityMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updatePurchaseRequestPriorityMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<PurchaseRequestPriorityDetailView purchaseRequestPriority={purchaseRequestPriority()} />)

    fireEvent.click(pencilOf('purchaseRequestPriorities.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <PurchaseRequestPriorityDetailView
        purchaseRequestPriority={purchaseRequestPriority({
          permissions: {
            ...purchaseRequestPriority().permissions,
            resource: { ...purchaseRequestPriority().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('purchaseRequestPriorities.form.name')).not.toBeInTheDocument()
    expect(pencilOf('purchaseRequestPriorities.form.color')).not.toBeInTheDocument()
  })
})
