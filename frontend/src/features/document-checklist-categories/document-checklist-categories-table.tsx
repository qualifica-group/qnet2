import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { documentChecklistCategoryColumnRenderers } from '@/features/document-checklist-categories/column-renderers'
import { useDocumentChecklistCategoryRowActions } from '@/features/document-checklist-categories/use-document-checklist-category-row-actions'

const CATEGORIES_DOMAIN = 'document-checklist-categories'

/** Icon keys the backend catalog uses for the category-specific actions. */
const CATEGORY_ACTION_ICONS: ActionIconMap = {
  'refresh-cw': RefreshCw,
}

/**
 * Thin adapter over the generic table for the checklist categories (spec
 * 0410): create button in the page header, row actions (sync again, delete,
 * activity). Permissions are the checklists' `document-checklists.*` (D-2);
 * gating is an affordance only, the backend re-authorizes.
 */
export function DocumentChecklistCategoriesTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const { openCreate, openView, sheet } = useModuleOpener(CATEGORIES_DOMAIN, { onSaved: refreshGrid })
  const actions = useDocumentChecklistCategoryRowActions({ openView, refreshGrid })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="document-checklists.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('documentChecklistCategories.form.newCategory')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={CATEGORIES_DOMAIN}
        renderers={documentChecklistCategoryColumnRenderers}
        iconMap={CATEGORY_ACTION_ICONS}
        onAction={actions.handleAction}
        isBusy={actions.isBusy}
        resolveActionState={actions.resolveActionState}
      />

      {sheet}

      <ResourceActivityDialog
        resource={CATEGORIES_DOMAIN}
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
