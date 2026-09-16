import { useTranslation } from 'react-i18next'
import { History, MessagesSquare, Paperclip } from 'lucide-react'
import { RecordCard } from '@/components/detail/record-panel'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { WORK_ORDER_ATTACHABLE_ALIAS, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { useWorkOrderCollaborationGates } from '@/features/work-orders/use-work-order-collaboration-gates'

const NOTES_TAB = 'notes'
const DOCUMENTS_TAB = 'documents'
const ACTIVITY_TAB = 'activity'

/** Compact trigger sizing, identical to the Opportunita'/Task tab strip. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface WorkOrderCollaborationSectionProps {
  workOrder: WorkOrderDetailWithPermissions
}

/**
 * The work order's collaboration surface, the same card the Opportunita'
 * detail puts in its side column: a compact Note | Documenti | Attivita' strip,
 * each tab absent when unauthorized, the whole card absent when none is.
 */
export function WorkOrderCollaborationSection({ workOrder }: WorkOrderCollaborationSectionProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { canViewNotes, canViewDocuments, canViewActivity, hasAny } =
    useWorkOrderCollaborationGates(workOrder)

  if (!hasAny) {
    return null
  }

  const defaultTab = canViewNotes ? NOTES_TAB : canViewDocuments ? DOCUMENTS_TAB : ACTIVITY_TAB

  return (
    <RecordCard>
      <Tabs defaultValue={defaultTab} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            {canViewNotes ? (
              <TabsTrigger value={NOTES_TAB} className={TRIGGER_CLASS}>
                <MessagesSquare className="size-3.5" aria-hidden="true" />
                {t('notes.section.title')}
              </TabsTrigger>
            ) : null}
            {canViewDocuments ? (
              <TabsTrigger value={DOCUMENTS_TAB} className={TRIGGER_CLASS}>
                <Paperclip className="size-3.5" aria-hidden="true" />
                {t('attachments.title')}
              </TabsTrigger>
            ) : null}
            {canViewActivity ? (
              <TabsTrigger value={ACTIVITY_TAB} className={TRIGGER_CLASS}>
                <History className="size-3.5" aria-hidden="true" />
                {t('activityLog.title')}
              </TabsTrigger>
            ) : null}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          {canViewNotes ? (
            <TabsContent value={NOTES_TAB}>
              <NotesSection entityType={WORK_ORDERS_DOMAIN} entityId={workOrder.id} showHeader={false} />
            </TabsContent>
          ) : null}
          {canViewDocuments ? (
            <TabsContent value={DOCUMENTS_TAB}>
              <DocumentsSection
                resource={WORK_ORDER_ATTACHABLE_ALIAS}
                id={workOrder.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </TabsContent>
          ) : null}
          {canViewActivity ? (
            <TabsContent value={ACTIVITY_TAB}>
              <ActivityLogSection resource={WORK_ORDERS_DOMAIN} id={workOrder.id} />
            </TabsContent>
          ) : null}
        </div>
      </Tabs>
    </RecordCard>
  )
}
