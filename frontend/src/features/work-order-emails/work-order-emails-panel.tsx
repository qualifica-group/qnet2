import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useCreateWorkOrderEmailDraft } from '@/features/work-order-emails/use-work-order-email-draft'
import { WorkOrderEmailList } from '@/features/work-order-emails/work-order-email-list'
import { WorkOrderEmailComposerDialog } from '@/features/work-order-emails/work-order-email-composer-dialog'
import { WorkOrderEmailDetailDialog } from '@/features/work-order-emails/work-order-email-detail-dialog'
import type { OutboundEmailListItem } from '@/features/work-order-emails/types'

interface WorkOrderEmailsPanelProps {
  workOrderId: number
  canSend: boolean
}

/**
 * "Email" tab content (AC-019/AC-020): the list plus the two mutually
 * exclusive dialogs a row can open — the composer for the actor's own draft
 * (D-3), the read-only detail for everything else.
 */
export function WorkOrderEmailsPanel({ workOrderId, canSend }: WorkOrderEmailsPanelProps) {
  const { t } = useTranslation()
  const [composerEmailId, setComposerEmailId] = useState<number | null>(null)
  const [justCreatedDraft, setJustCreatedDraft] = useState(false)
  const [detailEmailId, setDetailEmailId] = useState<number | null>(null)
  const createDraft = useCreateWorkOrderEmailDraft(workOrderId)

  const handleSelect = (item: OutboundEmailListItem) => {
    if (item.status === 'draft') {
      setJustCreatedDraft(false)
      setComposerEmailId(item.id)
    } else {
      setDetailEmailId(item.id)
    }
  }

  const handleNewEmail = () => {
    createDraft.mutate(undefined, {
      onSuccess: (email) => {
        setJustCreatedDraft(true)
        setComposerEmailId(email.id)
      },
    })
  }

  return (
    <div className="flex flex-col gap-3">
      {canSend ? (
        <div className="flex justify-end">
          <Button size="sm" onClick={handleNewEmail} disabled={createDraft.isPending}>
            <Plus className="size-3.5" aria-hidden="true" />
            {t('workOrderEmails.tab.newEmail')}
          </Button>
        </div>
      ) : null}

      <WorkOrderEmailList workOrderId={workOrderId} onSelect={handleSelect} />

      {composerEmailId !== null ? (
        <WorkOrderEmailComposerDialog
          key={composerEmailId}
          workOrderId={workOrderId}
          emailId={composerEmailId}
          justCreated={justCreatedDraft}
          open
          onOpenChange={(open) => {
            if (!open) {
              setComposerEmailId(null)
            }
          }}
        />
      ) : null}

      {detailEmailId !== null ? (
        <WorkOrderEmailDetailDialog
          key={detailEmailId}
          workOrderId={workOrderId}
          emailId={detailEmailId}
          open
          onOpenChange={(open) => {
            if (!open) {
              setDetailEmailId(null)
            }
          }}
        />
      ) : null}
    </div>
  )
}
