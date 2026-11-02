import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ReferentRelatedRecords } from '@/features/referents/referent-related-records'
import type { RegistryRelatedCreate } from '@/features/registries/registry-related-grid'
import type { TableRowScope } from '@/features/table/rows-types'

/**
 * Spec 0336: the referente's "Configured commissions" card — gated by the
 * Configuratore's `viewAny`, its grid scoped to the referente, its counter, and
 * "New rule" opening the form on that referente. The grid itself is the
 * anagrafica's one, covered by `registry-related-panels.test.tsx`.
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const openCreateWith = vi.fn()
vi.mock('@/features/commission-configurations/use-commission-configuration-row-actions', () => ({
  useCommissionConfigurationRowActions: () => ({
    handleAction: vi.fn(),
    isBusy: () => false,
    openCreateWith,
    sheet: null,
    dialogs: null,
  }),
}))

interface GridStubProps {
  domain: string
  rowScope: TableRowScope
  onRowCountChanged: (count: number | null) => void
  create: RegistryRelatedCreate | null
}

vi.mock('@/features/registries/registry-related-grid', () => ({
  RegistryRelatedGrid: ({ domain, rowScope, onRowCountChanged, create }: GridStubProps) => (
    <div>
      <span>{`${domain}:${JSON.stringify(rowScope)}`}</span>
      <button type="button" onClick={() => onRowCountChanged(3)}>
        grid reports
      </button>
      {create ? (
        <button type="button" onClick={create.onCreate}>
          {create.label}
        </button>
      ) : null}
    </div>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  openCreateWith.mockReset()
})

describe('ReferentRelatedRecords (spec 0336)', () => {
  it('shows the configured commissions grid scoped to this referente', () => {
    render(<ReferentRelatedRecords referentId={7} />)

    expect(screen.getByRole('tab', { name: 'Configured commissions' })).toBeInTheDocument()
    expect(screen.getByText('commission-configurations:{"referentId":7}')).toBeInTheDocument()
  })

  it('renders nothing without the Configuratore viewAny', () => {
    canMock.mockImplementation((permission) => permission !== 'commission-configurations.viewAny')
    const { container } = render(<ReferentRelatedRecords referentId={7} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('shows the counter once the grid reports the total', () => {
    render(<ReferentRelatedRecords referentId={7} />)
    fireEvent.click(screen.getByRole('button', { name: 'grid reports' }))

    expect(screen.getByLabelText('3 related records')).toHaveTextContent('3')
  })

  it('opens the create on this referente, and offers it only with create', () => {
    const { unmount } = render(<ReferentRelatedRecords referentId={7} />)
    fireEvent.click(screen.getByRole('button', { name: 'New configuration' }))

    expect(openCreateWith).toHaveBeenCalledWith({ referent_id: 7 })
    unmount()

    canMock.mockImplementation((permission) => permission !== 'commission-configurations.create')
    render(<ReferentRelatedRecords referentId={7} />)
    expect(screen.queryByRole('button', { name: 'New configuration' })).not.toBeInTheDocument()
  })
})
