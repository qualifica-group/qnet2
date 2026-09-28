import { useTranslation } from 'react-i18next'
import { Paperclip } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { REGISTRY_ATTACHABLE_ALIAS } from '@/features/registries/api'

export const REGISTRY_DOCUMENTS_TAB = 'registry-documents'

/**
 * The "Documenti anagrafica" tab a related record (Opportunita', Offerta,
 * Commessa) mounts in its collaboration card (spec 0173): the client's
 * documents, ALWAYS read-only — they belong to the anagrafica, this screen
 * only shows them, exactly like the "Documenti opportunita'" tab of the offer
 * and the contract. `null` without a registry or without
 * `registries.viewDocuments`, the permission that opens those documents on the
 * anagrafica itself.
 */
export function useRegistryDocumentsTab(registryId: number | null | undefined): RecordCollaborationTab | null {
  const { t } = useTranslation()
  const { can } = useAbilities()

  if (registryId == null || !can('registries.viewDocuments')) {
    return null
  }

  return {
    value: REGISTRY_DOCUMENTS_TAB,
    label: t('registries.detail.registryDocumentsTab'),
    icon: <Paperclip className="size-3.5" aria-hidden="true" />,
    content: (
      <DocumentsSection resource={REGISTRY_ATTACHABLE_ALIAS} id={registryId} canUpload={false} canDelete={false} />
    ),
  }
}
