import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ProductTypologyDetailView } from '@/features/product-typologies/product-typology-detail'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'

/**
 * Spec 0088: the detail shows name (hero title), code (hero subtitle),
 * description and both timestamps, with the shared detail kit's
 * placeholder on the nullable description. Purely presentational (the caller
 * fetches and passes the detail down), so the Activity Log section gate is
 * exercised directly on the `permissions.actions.view_activity` prop
 * (mirrors `PaymentMethodDetailView`'s suite).
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function productTypology(
  overrides: Partial<ProductTypologyDetailWithPermissions> = {},
): ProductTypologyDetailWithPermissions {
  return {
    id: 4,
    name: 'Kilogram',
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('ProductTypologyDetailView — detail fields', () => {
  it('shows name, code, description and both timestamps', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.getByRole('heading', { name: 'Kilogram' })).toBeInTheDocument()
    expect(screen.getByText('kilogram')).toBeInTheDocument()
    expect(screen.getByText('Mass unit')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<ProductTypologyDetailView productTypology={productTypology({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('ProductTypologyDetailView — activity log section', () => {
  it('mounts the section for the viewed product typology when view_activity is granted', () => {
    render(
      <ProductTypologyDetailView
        productTypology={productTypology({
          permissions: { ...productTypology().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'product-typologies', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<ProductTypologyDetailView productTypology={productTypology()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
