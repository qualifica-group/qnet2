import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import {
  ApiClientFormDialog,
  type ApiClientFormTarget,
} from '@/features/api-integrations/components/api-client-form-dialog'
import { ApiKeyDialog, type ApiKeyReveal } from '@/features/api-integrations/components/api-key-dialog'
import { useApiClientActions } from '@/features/api-integrations/use-api-client-actions'

/** Domain key of the generic table for API clients (`/api/tables/api-clients/*`). */
export const API_CLIENTS_DOMAIN = 'api-clients'

/**
 * "API clients" tab: the generic SSRM table plus the create/edit dialog, the
 * rotate/revoke flows and the one-time key dialog. The plain-text key exists
 * only in this component's `reveal` state and disappears on close.
 */
export function ApiClientsTab() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const [target, setTarget] = useState<ApiClientFormTarget | null>(null)
  const [reveal, setReveal] = useState<ApiKeyReveal | null>(null)

  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const closeForm = useCallback(() => setTarget(null), [])
  const closeReveal = useCallback(() => setReveal(null), [])

  const handleChanged = useCallback(() => {
    setTarget(null)
    refreshGrid()
  }, [refreshGrid])

  const handleKeyIssued = useCallback((issued: ApiKeyReveal) => {
    setTarget(null)
    setReveal(issued)
  }, [])

  const { rotate, revoke } = useApiClientActions({
    onKeyIssued: handleKeyIssued,
    onChanged: handleChanged,
  })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      const id = Number(row.id)
      const name = String(row.name ?? '')
      switch (action.key) {
        case 'view':
        case 'edit':
          setTarget({ type: 'edit', id })
          break
        case 'rotate-key':
          void rotate(id, name)
          break
        case 'delete':
          void revoke(id, name)
          break
        default:
          break
      }
    },
    [revoke, rotate],
  )

  return (
    <div className="flex flex-col gap-3">
      <div className="flex justify-end">
        <Can permission="api-clients.create">
          <Button size="sm" onClick={() => setTarget({ type: 'create' })}>
            <Plus className="size-3.5" aria-hidden />
            {t('apiIntegrations.clients.new')}
          </Button>
        </Can>
      </div>

      <TableView ref={tableRef} domain={API_CLIENTS_DOMAIN} onAction={handleAction} />

      {target ? (
        <ApiClientFormDialog
          target={target}
          onClose={closeForm}
          onKeyIssued={handleKeyIssued}
          onSaved={handleChanged}
          onRotate={(id, name) => void rotate(id, name)}
          onRevoke={(id, name) => void revoke(id, name)}
        />
      ) : null}

      <ApiKeyDialog reveal={reveal} onClose={closeReveal} />
    </div>
  )
}
