import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import {
  detailViewContract as contract,
  terminatedContract,
  validatedContract,
} from '@/features/contracts/contract-renewal-fixtures'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/** Split out of `contract-detail.test.tsx` (500-line limit): the status-group lifecycle gating of the detail view. */

// The related-record links mount `useModuleOpener`, whose mode resolution
// reads the authenticated user. They force `modal` anyway (they must never
// navigate away), so the preference lookup is stubbed rather than wrapping
// every render in an AuthProvider.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

const { canMock } = vi.hoisted(() => ({ canMock: vi.fn<(permission: string) => boolean>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// Radix `Tabs.Content` only mounts the active panel, and simulating a real
// pointer/click activation on its `Trigger` is unreliable under jsdom (no
// `@testing-library/user-event` in this repo). This suite asserts the VIEW'S
// OWN wiring (which props reach which section), not Radix's tab-switching
// mechanics — so every panel is rendered unconditionally here, `Tabs`/
// `TabsList`/`TabsTrigger` stay the real components (the "tab" role queries
// below still exercise them).
vi.mock('@/components/ui/tabs', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui/tabs')>()
  return {
    ...actual,
    TabsContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  }
})

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({
    resource,
    id,
    canUpload,
    canDelete,
  }: {
    resource: string
    id: number
    canUpload: boolean
    canDelete: boolean
  }) => <div>{`documents:${resource}:${id}:upload=${canUpload}:delete=${canDelete}`}</div>,
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: ({ resource, id }: { resource: string; id: number }) => (
    <div>{`activity:${resource}:${id}`}</div>
  ),
}))

// The Commesse tab (spec 0095 D-8) mounts the real `TableView`; stubbed the
// same way every other detail-composing suite stubs it (e.g.
// `opportunity-quotes-section.test.tsx`) so this suite exercises the VIEW'S
// OWN wiring, not the generic grid/AG Grid Enterprise setup.
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { onRowCountChanged?: (count: number | null) => void }
  >(function TableViewStub(_props, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn(), clearSelection: vi.fn() }))
    return <div>work-orders-table-stub</div>
  }),
}))
const confirmMock = vi.fn<ConfirmFn>()

function renderView(data: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmContext.Provider value={confirmMock}>
          <ContractDetailView contract={data} />
        </ConfirmContext.Provider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `en-contracts.ts`/`it-contracts.ts` are not yet merged into `en.ts`/`it.ts`
  // (MT-09's job): registered here at runtime, mirrors the wave-1
  // `document-layouts` pre-merge test pattern.
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  confirmMock.mockReset()
  confirmMock.mockResolvedValue(true)
})

describe('ContractDetailView — gating per gruppo di stato (direttiva 2026-08-31 rev.2)', () => {
  it('offre i dati sul posto, Modifica stato, Valida e Disdici su uno stato APERTO', () => {
    renderView(contract())
    expect(screen.getByRole('button', { name: 'Validate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Change status' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Renewal' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Program' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reopen contract' })).not.toBeInTheDocument()
  })

  it('si comporta allo stesso modo su uno stato PENDING', () => {
    renderView(
      contract({ contract_status: { id: 3, name: 'Programmato', color: 'blue', group: 'pending' } }),
    )
    expect(screen.getByRole('button', { name: 'Validate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Change status' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Program' })).not.toBeInTheDocument()
  })

  // Decisione utente 2026-11-04: un contratto gia' validato su uno stato
  // PENDING (migrazione legacy, es. "Da programmare") si programma.
  it('su PENDING gia validato offre Programma al posto di Valida', () => {
    renderView(
      contract({
        contract_status: { id: 2, name: 'Da programmare', color: 'blue', group: 'pending' },
        validated_at: '2026-07-14',
      }),
    )
    expect(screen.getByRole('button', { name: 'Program' })).toBeEnabled()
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
  })

  it('non offre Programma su un contratto sospeso, anche se gia validato', () => {
    renderView(contract({ is_suspended: true, suspended_at: '2026-02-01T10:00:00Z', validated_at: '2026-01-15' }))
    expect(screen.queryByRole('button', { name: 'Program' })).not.toBeInTheDocument()
  })

  it('su CHIUSO POSITIVO lascia Disdici, Programma (attivo, spec 0095) e Riapri', () => {
    // Direttiva utente 2026-08-31 rev.3: anche la chiusura positiva si riapre.
    renderView(validatedContract())
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Program' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Reopen contract' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
    // REQUIREMENT CHANGED (spec 0360 D-2): da Validato i dati si modificano ancora sul posto.
    expect(screen.getByRole('button', { name: 'Edit Expiry' })).toBeInTheDocument()
  })

  it('su CHIUSO POSITIVO la riapertura chiede lo stato di ripartenza, non il confirm inline', async () => {
    renderView(validatedContract())

    fireEvent.click(screen.getByRole('button', { name: 'Reopen contract' }))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Restart status')).toBeInTheDocument()
    expect(confirmMock).not.toHaveBeenCalled()
  })

  it('su CHIUSO NEGATIVO lascia solo Riapri', () => {
    renderView(terminatedContract())
    expect(screen.getByRole('button', { name: 'Reopen contract' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Program' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Terminate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Expiry' })).not.toBeInTheDocument()
    // I due link verso Offerta/Opportunita' non sono gated sul ciclo di vita.
    expect(screen.getByRole('button', { name: 'Accordo Acme' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Fornitura 2026' })).toBeInTheDocument()
  })

  it('chiede lo stato di ripartenza riattivando un contratto chiuso, invece del confirm inline', async () => {
    renderView(terminatedContract())

    fireEvent.click(screen.getByRole('button', { name: 'Reopen contract' }))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Restart status')).toBeInTheDocument()
    // Il percorso sospeso (confirm inline) non deve scattare qui.
    expect(confirmMock).not.toHaveBeenCalled()
  })

  it('omette Riapri su un contratto chiuso senza contracts.reactivate', () => {
    renderView(
      terminatedContract({
        permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, reactivate: false } },
      }),
    )
    expect(screen.queryByRole('button', { name: 'Reopen contract' })).not.toBeInTheDocument()
  })

  it('omette Modifica stato senza contracts.changeStatus', () => {
    renderView(
      contract({
        permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, change_status: false } },
      }),
    )
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
  })

  it('non offre Valida su un contratto sospeso, ma ne offre la riattivazione', () => {
    renderView(contract({ is_suspended: true, suspended_at: '2026-02-01T10:00:00Z' }))
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reopen contract' })).toBeInTheDocument()
  })

  // Direttiva utente 2026-08-31: non piu' bottoni nella barra azioni ma link
  // SUL campo che nomina il record. Restano `<button>`, non `<a>`: aprono una
  // modale, non navigano via dal contratto.
  it('apre Offerta e Opportunita in modale dai rispettivi campi, non da bottoni della barra azioni', () => {
    renderView(contract())
    expect(screen.getByRole('button', { name: 'Accordo Acme' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Fornitura 2026' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Accordo Acme' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Fornitura 2026' })).not.toBeInTheDocument()
  })
})
