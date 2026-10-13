import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import {
  operationalSiteFixture,
  operationalSiteWrapper,
} from '@/features/operational-sites/operational-site-test-fixtures'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'

/** Spec 0011: what the detail shows. Its in-place editing is covered by `operational-site-detail-inline-edit.test.tsx`. */

function renderDetail(site: OperationalSiteDetailWithPermissions) {
  const { wrapper } = operationalSiteWrapper()
  return render(<OperationalSiteDetailView operationalSite={site} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('OperationalSiteDetailView', () => {
  it('renders the street and the postal code', () => {
    renderDetail(operationalSiteFixture())

    expect(screen.getByText('Via Roma 1')).toBeInTheDocument()
    expect(screen.getByText('20100')).toBeInTheDocument()
  })

  it('renders the geo levels in the KPI strip and the row', () => {
    renderDetail(operationalSiteFixture())

    expect(screen.getByText('Lombardy')).toBeInTheDocument()
    expect(screen.getByText('Italy')).toBeInTheDocument()
    expect(screen.getByText('Milan, Milan, Lombardy, Italy')).toBeInTheDocument()
  })

  it('renders an em dash for a missing postal code and for a site without a geo place', () => {
    renderDetail(
      operationalSiteFixture({
        postal_code: null,
        country: null,
        region: null,
        province: null,
        city: null,
      }),
    )

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })

  it('renders whether the site is active (spec 0135)', () => {
    const { unmount } = renderDetail(operationalSiteFixture())
    expect(screen.getByText('Yes')).toBeInTheDocument()
    unmount()

    renderDetail(operationalSiteFixture({ is_active: false }))
    expect(screen.getByText('No')).toBeInTheDocument()
  })

  it('renders the formatted creation date', () => {
    renderDetail(operationalSiteFixture())

    expect(screen.getByText(i18n.t('operationalSites.detail.created_at'))).toBeInTheDocument()
    expect(screen.getByText(/2026/)).toBeInTheDocument()
  })
})
