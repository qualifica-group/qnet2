import { LedgerPanel } from '@/features/ledger/ledger-panel'
import type { RegistryRelatedPanelProps } from '@/features/registries/registry-related-panels'

/** The "Partitario" tab of the anagrafica record: the shared panel on the current registry, no selector. */
export function RegistryLedgerPanel({ registryId }: RegistryRelatedPanelProps) {
  return <LedgerPanel registryId={registryId} />
}
