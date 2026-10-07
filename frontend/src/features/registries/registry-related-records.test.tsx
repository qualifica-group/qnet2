import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RegistryRelatedRecords } from '@/features/registries/registry-related-records'
import type { RegistryRelatedPanelProps } from '@/features/registries/registry-related-panels'

/**
 * Spec 0199: the tab strip of the anagrafica's related records — which tabs
 * exist (the module's `viewAny`; spec 0204: the supplier-only "Configured
 * commissions"), which panel each mounts, and the counter
 * each grid reports. The panels themselves are covered by
 * `registry-related-panels.test.tsx`.
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

function stubPanel(name: string) {
  return function PanelStub({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
    return (
      <div>
        <span>{`${name}-panel:${registryId}`}</span>
        <button type="button" onClick={() => onRowCountChanged(4)}>
          {`${name} reports`}
        </button>
      </div>
    )
  }
}

vi.mock('@/features/registries/registry-related-panels', () => ({
  RegistryOpportunitiesPanel: stubPanel('opportunities'),
  RegistryQuotesPanel: stubPanel('quotes'),
  RegistryWorkOrdersPanel: stubPanel('work-orders'),
  RegistryTasksPanel: stubPanel('tasks'),
  RegistryCommissionConfigurationsPanel: stubPanel('commission-configurations'),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('RegistryRelatedRecords (spec 0199)', () => {
  it('shows one tab per module, the first one open on this anagrafica', () => {
    render(<RegistryRelatedRecords registryId={5} isSupplier={false} />)

    const tabs = screen.getAllByRole('tab').map((tab) => tab.textContent)
    expect(tabs).toEqual(['Opportunities', 'Quotes', 'Work orders', 'Tasks'])
    expect(screen.getByText('opportunities-panel:5')).toBeInTheDocument()
    expect(screen.queryByText('quotes-panel:5')).not.toBeInTheDocument()
  })

  it("leaves out the tab of a module the user cannot list", () => {
    canMock.mockImplementation((permission) => permission !== 'quotes.viewAny')
    render(<RegistryRelatedRecords registryId={5} isSupplier={false} />)

    expect(screen.queryByRole('tab', { name: 'Quotes' })).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Work orders' })).toBeInTheDocument()
  })

  it('renders nothing when no module can be listed', () => {
    canMock.mockReturnValue(false)
    const { container } = render(<RegistryRelatedRecords registryId={5} isSupplier={false} />)

    expect(container).toBeEmptyDOMElement()
  })

  it("shows a tab's counter once its grid reports the total", () => {
    render(<RegistryRelatedRecords registryId={5} isSupplier={false} />)

    expect(screen.queryByLabelText('4 related records')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'opportunities reports' }))
    expect(screen.getByLabelText('4 related records')).toHaveTextContent('4')
  })

  it('adds the configured commissions tab on a supplier anagrafica only (spec 0204)', () => {
    render(<RegistryRelatedRecords registryId={5} isSupplier />)

    const tabs = screen.getAllByRole('tab').map((tab) => tab.textContent)
    expect(tabs).toEqual(['Opportunities', 'Quotes', 'Work orders', 'Tasks', 'Configured commissions'])
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Configured commissions' }))
    expect(screen.getByText('commission-configurations-panel:5')).toBeInTheDocument()
  })

  it('leaves out the configured commissions tab without the Configuratore viewAny', () => {
    canMock.mockImplementation((permission) => permission !== 'commission-configurations.viewAny')
    render(<RegistryRelatedRecords registryId={5} isSupplier />)

    expect(screen.queryByRole('tab', { name: 'Configured commissions' })).not.toBeInTheDocument()
  })
})
