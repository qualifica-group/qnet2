import { useTranslation } from 'react-i18next'
import { FolderOpen } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'

export interface DocumentsDialogProps {
  /**
   * Polymorphic owner alias sent as `attachable_type` (e.g. `'opportunity'`,
   * per `config('attachments.attachable_types')`) — NOT the plural
   * table-registry domain key.
   */
  resource: string
  /** Owner id whose documents are shown; `null` closes the dialog. */
  id: number | null
  onOpenChange: (open: boolean) => void
}

/**
 * Row-action Dialog mounting the shared `DocumentsSection` for a single
 * polymorphic owner, mirroring `ResourceActivityDialog`'s row-action-Dialog
 * shape. Shared by every module whose table exposes a `documents` row action
 * (opportunities, request-management): the module adapter owns only the open
 * state and the grid refresh, never a dialog of its own.
 */
export function DocumentsDialog({ resource, id, onOpenChange }: DocumentsDialogProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  return (
    <Dialog open={id !== null} onOpenChange={onOpenChange}>
      <DialogContent size="lg" className="gap-0 p-0">
        <DialogHeader className="flex-row items-center gap-3 space-y-0 rounded-t-lg border-b bg-muted/40 px-5 py-4 text-left">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FolderOpen className="size-4.5" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <DialogTitle className="text-base">{t('attachments.title')}</DialogTitle>
            <DialogDescription className="text-xs">{t('attachments.dialogSubtitle')}</DialogDescription>
          </div>
        </DialogHeader>
        {id !== null ? (
          <div className="max-h-[70vh] overflow-y-auto rounded-b-lg px-5 py-4">
            <DocumentsSection
              resource={resource}
              id={id}
              canUpload={can('attachments.create')}
              canDelete={can('attachments.delete')}
            />
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
