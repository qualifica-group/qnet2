import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Power, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { ArchivioSmartConnectionButton } from '@/features/document-checklists/archivio-smart-connection-button'
import { documentChecklistColumnRenderers } from '@/features/document-checklists/column-renderers'
import { useDocumentChecklistRowActions } from '@/features/document-checklists/use-document-checklist-row-actions'

const DOCUMENT_CHECKLISTS_DOMAIN = 'document-checklists'

/** Icon keys the backend catalog uses for the checklist-specific actions. */
const CHECKLIST_ACTION_ICONS: ActionIconMap = {
  'refresh-cw': RefreshCw,
  power: Power,
}

/**
 * Thin adapter over the generic table for the document checklists (spec
 * 0403): create button, "Sync all" and the ArchivioSmart connection button in
 * the page header, row actions (sync again, activate/deactivate, delete,
 * activity). Permission gating is an affordance only; the backend re-authorizes.
 */
export function DocumentChecklistsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const { openCreate, openView, sheet } = useModuleOpener(DOCUMENT_CHECKLISTS_DOMAIN, { onSaved: refreshGrid })
  const actions = useDocumentChecklistRowActions({ openView, refreshGrid })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <ArchivioSmartConnectionButton />
            <Can permission="document-checklists.update">
              <Button type="button" size="sm" variant="secondary" onClick={actions.syncAll} disabled={actions.isSyncingAll}>
                <RefreshCw aria-hidden="true" />
                {t('documentChecklists.actions.syncAll')}
              </Button>
            </Can>
            <Can permission="document-checklists.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('documentChecklists.form.newChecklist')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={DOCUMENT_CHECKLISTS_DOMAIN}
        renderers={documentChecklistColumnRenderers}
        iconMap={CHECKLIST_ACTION_ICONS}
        onAction={actions.handleAction}
        isBusy={actions.isBusy}
        resolveActionState={actions.resolveActionState}
      />

      {sheet}

      <ResourceActivityDialog
        resource={DOCUMENT_CHECKLISTS_DOMAIN}
        row={actions.activityRow}
        onOpenChange={(open) => {
          if (!open) {
            actions.closeActivity()
          }
        }}
      />
    </div>
  )
}
