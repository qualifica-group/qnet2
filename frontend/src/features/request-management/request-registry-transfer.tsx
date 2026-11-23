import { useResourcePermissions } from '@/features/authorization/permissions'
import { QuoteRegistryTransferButton } from '@/features/quotes/registry-transfer/quote-registry-transfer-button'
import { useRequestModule } from '@/features/request-management/request-module'
import type { RequestWorkPanel } from '@/features/request-management/types'

interface RequestRegistryTransferProps {
  panel: RequestWorkPanel
  /** The panel's "leave the record" callback, like the contact transfer. */
  onTransferred?: () => void
}

/**
 * "Trasferisci ad altra anagrafica" in the work panel (spec 0404, part B), on
 * the module's own route so its permission set authorizes. Gated by the
 * server-combined `transfer_registry` flag (`{module}.update` AND
 * `.transferRegistry`).
 */
export function RequestRegistryTransfer({ panel, onTransferred }: RequestRegistryTransferProps) {
  const module = useRequestModule()
  const { canAction } = useResourcePermissions()

  if (!canAction('transfer_registry')) {
    return null
  }

  return (
    <QuoteRegistryTransferButton
      basePath={module.apiBasePath}
      quoteId={panel.id}
      currentRegistry={panel.registry}
      currentTitle={panel.title ?? ''}
      attributes={panel.applicable_attributes}
      values={panel.attribute_values}
      onTransferred={() => onTransferred?.()}
    />
  )
}
