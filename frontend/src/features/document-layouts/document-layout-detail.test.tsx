import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { DocumentLayoutDetailView } from '@/features/document-layouts/document-layout-detail'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'

/**
 * Spec 0069: the detail shows name, code, description, module, active and
 * default status, and both timestamps, with the shared detail kit's
 * placeholder on the nullable field. Purely presentational (the caller
 * fetches and passes the detail down), so the Activity Log section gate is
 * exercised directly on the `permissions.actions.view_activity` prop
 * (mirrors `PaymentMethodDetailView`'s suite). The block/zone `config` is
 * NOT rendered here — that surface belongs to the visual editor, a
 * different owner.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function documentLayout(
  overrides: Partial<DocumentLayoutDetailWithPermissions> = {},
): DocumentLayoutDetailWithPermissions {
  return {
    id: 7,
    name: 'Standard quote layout',
    code: 'standard',
    description: 'Default layout used for every quote',
    module: 'quotes',
    module_label: 'Quotes',
    is_active: true,
    is_default: true,
    config: createEmptyDocumentLayoutConfig(),
    images: [],
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

describe('DocumentLayoutDetailView — detail fields (AC-134)', () => {
  it('shows name, code, description, module label, active/default status and both timestamps', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.getByRole('heading', { name: 'Standard quote layout' })).toBeInTheDocument()
    expect(screen.getByText('standard')).toBeInTheDocument()
    expect(screen.getByText('Default layout used for every quote')).toBeInTheDocument()
    expect(screen.getByText('Quotes')).toBeInTheDocument()
    expect(screen.getAllByText('Yes')).toHaveLength(2)
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder when description is empty', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('shows the inactive and non-default status', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({ is_active: false, is_default: false })}
      />,
    )

    expect(screen.getAllByText('No')).toHaveLength(2)
  })

  it('does not render an A4 preview of the config: that surface belongs to the visual editor', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.queryByText(/preview/i)).not.toBeInTheDocument()
  })
})

describe('DocumentLayoutDetailView — activity log section', () => {
  it('mounts the section for the viewed layout when view_activity is granted', () => {
    render(
      <DocumentLayoutDetailView
        documentLayout={documentLayout({
          permissions: { ...documentLayout().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'document-layouts', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<DocumentLayoutDetailView documentLayout={documentLayout()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
