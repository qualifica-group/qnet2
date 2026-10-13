import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { CostTypeDetailView } from '@/features/cost-types/cost-type-detail'
import { moduleScreen } from '@/features/cost-types/cost-type-screens'
import type { CostTypeDetailWithPermissions } from '@/features/cost-types/types'

/**
 * Spec 0249 AC-006: the detail edits every row in place (name, description,
 * nature, color, active), each save a PATCH of that field alone, and there is
 * no separate edit page nor Edit button.
 */

const activityLogSectionMock = vi.fn()
const updateCostTypeMock = vi.fn()

vi.mock('@/features/cost-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/cost-types/api')>()),
  updateCostType: (...args: unknown[]) => updateCostTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function costType(overrides: Partial<CostTypeDetailWithPermissions> = {}): CostTypeDetailWithPermissions {
  return {
    id: 4,
    name: 'Collaboratori',
    description: 'External staff',
    nature: 'direct',
    color: 'blue',
    sort_order: 7,
    is_active: true,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    abilities: { update: true, delete: true },
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
  updateCostTypeMock.mockReset()
})

describe('CostTypeDetailView - detail fields', () => {
  it('shows name, description, nature, color, order, status and both timestamps', () => {
    render(<CostTypeDetailView costType={costType()} />)

    expect(screen.getByRole('heading', { name: 'Collaboratori' })).toBeInTheDocument()
    expect(screen.getByText('External staff')).toBeInTheDocument()
    expect(screen.getByText('Direct')).toBeInTheDocument()
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByText('7')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<CostTypeDetailView costType={costType({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('mounts the activity log only when view_activity is granted', () => {
    const base = costType()
    render(<CostTypeDetailView costType={costType({ permissions: { ...base.permissions, actions: { view_activity: true } } })} />)

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'cost-types', id: 4 })
  })
})

describe('CostTypeDetailView - in-place editing (spec 0249 AC-006)', () => {
  it('has no Edit button and the registry generates no edit route', () => {
    render(<CostTypeDetailView costType={costType()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(moduleScreen.generateEditRoute).toBe(false)
    for (const key of ['name', 'description', 'nature', 'color', 'isActive']) {
      expect(pencilOf(`costTypes.form.${key}`)).toBeInTheDocument()
    }
    expect(pencilOf('costTypes.detail.sortOrder')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never sort_order', async () => {
    updateCostTypeMock.mockResolvedValue(costType({ name: 'Renamed' }))
    render(<CostTypeDetailView costType={costType()} />)

    fireEvent.click(pencilOf('costTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateCostTypeMock).toHaveBeenCalledWith(4, { name: 'Renamed' }))
  })

  it('edits the nature through a select over the three localized values', async () => {
    updateCostTypeMock.mockResolvedValue(costType({ nature: 'indirect' }))
    render(<CostTypeDetailView costType={costType()} />)

    fireEvent.click(pencilOf('costTypes.form.nature') as HTMLElement)
    fireEvent.click(screen.getByRole('combobox'))
    expect(screen.getByRole('option', { name: 'Indirect' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'General' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Indirect' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateCostTypeMock).toHaveBeenCalledWith(4, { nature: 'indirect' }))
  })

  it('edits the color through the shared palette picker', async () => {
    updateCostTypeMock.mockResolvedValue(costType({ color: 'emerald' }))
    render(<CostTypeDetailView costType={costType()} />)

    fireEvent.click(pencilOf('costTypes.form.color') as HTMLElement)
    fireEvent.click(screen.getByRole('button', { name: i18n.t('customFields.colors.blue') }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateCostTypeMock).toHaveBeenCalledWith(4, { color: 'emerald' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<CostTypeDetailView costType={costType()} />)

    fireEvent.click(pencilOf('costTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateCostTypeMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateCostTypeMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<CostTypeDetailView costType={costType()} />)

    fireEvent.click(pencilOf('costTypes.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = costType()
    render(
      <CostTypeDetailView
        costType={costType({ permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } } })}
      />,
    )

    expect(pencilOf('costTypes.form.name')).not.toBeInTheDocument()
    expect(pencilOf('costTypes.form.nature')).not.toBeInTheDocument()
  })
})
