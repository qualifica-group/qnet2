import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { ContractActionsBar } from '@/features/contracts/contract-actions-bar'
import { contractWithPermissions } from '@/features/contracts/contract-renewal-fixtures'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'
import type { FieldPermission } from '@/features/authorization/types'

/**
 * Regression (segnalazione utente 2026-11-24, Annarita Palumbo): un utente con
 * `contracts.terminate`/`contracts.changeStatus` ma senza `contracts.update`
 * apriva "Disdici contratto" e trovava il menu dello stato bloccato. Causa: il
 * picker leggeva il permesso del CAMPO `contract_status_id` della scheda (sola
 * lettura senza `contracts.update`), mentre la finestra e' autorizzata
 * dall'azione, che il server verifica da sola.
 */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

// Stubbed to a plain trigger carrying the `disabled` flag the field forwards:
// this suite checks the lock, not the async for-select network path.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels, disabled }: { labels: { triggerLabel: string }; disabled?: boolean }) => (
    <button type="button" disabled={disabled}>
      {labels.triggerLabel}
    </button>
  ),
}))

const READONLY_STATUS: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

function actorWithoutUpdate(): ContractDetailWithPermissions {
  const contract = contractWithPermissions(
    { terminate: true, change_status: true, validate: true },
    {
      contract_status: { id: 1, name: 'Da validare', color: 'slate', group: 'open' },
      validated_at: null,
    },
  )
  return {
    ...contract,
    permissions: {
      ...contract.permissions,
      resource: { ...contract.permissions.resource, update: false },
      fields: { contract_status_id: READONLY_STATUS },
    },
  }
}

// Mirrors the real tree: the bar lives inside the detail's provider, which
// carries the record's field permissions.
function renderBar(contract: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={vi.fn<ConfirmFn>()}>
        <ResourcePermissionsProvider permissions={contract.permissions}>
          <ContractActionsBar contract={contract} onChanged={vi.fn()} onWorkOrderCreated={vi.fn()} />
        </ResourcePermissionsProvider>
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

describe('ContractActionsBar - status picker of the action dialogs', () => {
  it.each([
    ['Terminate contract', 'Destination status'],
    ['Change status', 'New status'],
  ])('"%s" lets an actor without contracts.update pick the status', async (action, pickerLabel) => {
    renderBar(actorWithoutUpdate())

    fireEvent.click(screen.getByRole('button', { name: action }))

    expect(await screen.findByRole('button', { name: pickerLabel })).toBeEnabled()
  })
})
