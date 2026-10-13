import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { UnitOfMeasureDetailView } from '@/features/units-of-measure/unit-of-measure-detail'
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'

/**
 * Spec 0088: the detail shows name (hero title), code (hero subtitle),
 * symbol, description and both timestamps. Wave 3 of the "no edit pages" plan
 * (spec 0236): every row edits in place, each save a PATCH of that field
 * alone; `code` is immutable (D-2), so it is the hero subtitle and never a
 * row. The Activity Log gate is exercised on `permissions.actions.view_activity`.
 */

const activityLogSectionMock = vi.fn()
const updateUnitOfMeasureMock = vi.fn()

vi.mock('@/features/units-of-measure/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/units-of-measure/api')>()),
  updateUnitOfMeasure: (...args: unknown[]) => updateUnitOfMeasureMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function unitOfMeasure(
  overrides: Partial<UnitOfMeasureDetailWithPermissions> = {},
): UnitOfMeasureDetailWithPermissions {
  return {
    id: 4,
    name: 'Kilogram',
    symbol: 'kg',
    code: 'kilogram',
    description: 'Mass unit',
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

const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateUnitOfMeasureMock.mockReset()
})

describe('UnitOfMeasureDetailView — detail fields', () => {
  it('shows name, code, symbol, description and both timestamps', () => {
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    expect(screen.getByRole('heading', { name: 'Kilogram' })).toBeInTheDocument()
    expect(screen.getByText('kilogram')).toBeInTheDocument()
    expect(screen.getByText('kg')).toBeInTheDocument()
    expect(screen.getByText('Mass unit')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('UnitOfMeasureDetailView — activity log section', () => {
  it('mounts the section for the viewed unit of measure when view_activity is granted', () => {
    render(
      <UnitOfMeasureDetailView
        unitOfMeasure={unitOfMeasure({
          permissions: { ...unitOfMeasure().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'units-of-measure', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('UnitOfMeasureDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed with its pencil, and code is not a row', () => {
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('unitsOfMeasure.form.name')).toBeInTheDocument()
    expect(pencilOf('unitsOfMeasure.form.symbol')).toBeInTheDocument()
    expect(pencilOf('unitsOfMeasure.form.code')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never code', async () => {
    updateUnitOfMeasureMock.mockResolvedValue(unitOfMeasure({ name: 'Kilograms' }))
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    fireEvent.click(pencilOf('unitsOfMeasure.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Kilograms' } })
    saveRow()

    await waitFor(() => expect(updateUnitOfMeasureMock).toHaveBeenCalledWith(4, { name: 'Kilograms' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    fireEvent.click(pencilOf('unitsOfMeasure.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Kilograms' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Kilograms')).not.toBeInTheDocument()
    expect(updateUnitOfMeasureMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateUnitOfMeasureMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<UnitOfMeasureDetailView unitOfMeasure={unitOfMeasure()} />)

    fireEvent.click(pencilOf('unitsOfMeasure.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <UnitOfMeasureDetailView
        unitOfMeasure={unitOfMeasure({
          permissions: {
            ...unitOfMeasure().permissions,
            resource: { ...unitOfMeasure().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('unitsOfMeasure.form.name')).not.toBeInTheDocument()
    expect(pencilOf('unitsOfMeasure.form.symbol')).not.toBeInTheDocument()
  })
})
