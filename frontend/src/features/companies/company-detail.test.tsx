import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { CompanyDetailView } from '@/features/companies/company-detail'
import { companyFixture, companyWrapper, permissionsFor } from '@/features/companies/company-test-fixtures'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'

/**
 * Spec 0034, AC-015: the "Activity log" tab mounts only when the detail
 * envelope grants `permissions.actions.view_activity`. The in-place editing is
 * covered by `company-detail-inline-edit.test.tsx`.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function renderDetail(company: CompanyDetailWithPermissions) {
  const { wrapper } = companyWrapper()
  return render(<CompanyDetailView company={company} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('CompanyDetailView — activity log section (AC-015)', () => {
  it('mounts the section for the viewed company when view_activity is granted', () => {
    renderDetail(
      companyFixture({ permissions: { ...permissionsFor(), actions: { view_activity: true } } }),
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'companies', id: 7 })
  })

  it('hides the section when view_activity is not granted', () => {
    renderDetail(companyFixture())

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('CompanyDetailView — what it shows', () => {
  it('shows the denomination, VAT number, street, postal code and place', () => {
    renderDetail(companyFixture())

    expect(screen.getAllByText('Acme Srl').length).toBeGreaterThan(0)
    expect(screen.getAllByText('IT12345678903').length).toBeGreaterThan(0)
    expect(screen.getByText('221B Baker Street')).toBeInTheDocument()
    expect(screen.getByText('20100')).toBeInTheDocument()
    expect(screen.getByText('Milan, Milan, Lombardy, Italy')).toBeInTheDocument()
  })

  it('shows the em dash for a company without an address', () => {
    renderDetail(companyFixture({ address: null, vat_number: null }))

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})
