import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useCreateOutboundEmailDraft } from '@/features/outbound-emails/use-outbound-email-draft'
import { OutboundEmailList } from '@/features/outbound-emails/outbound-email-list'
import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import { OutboundEmailDetailDialog } from '@/features/outbound-emails/outbound-email-detail-dialog'
import type { CreateOutboundEmailPayload, EmailOwnerRef, OutboundEmailListItem } from '@/features/outbound-emails/types'

interface OutboundEmailsPanelProps {
  owner: EmailOwnerRef
  canSend: boolean
  /** Body of "Nuova email" (invoices: `{ attach_pdf: true }`); omitted = empty body, as for work orders. */
  newDraftPayload?: CreateOutboundEmailPayload
}

/**
 * "Email" tab content (AC-019/AC-020): the list plus the two mutually
 * exclusive dialogs a row can open — the composer for the actor's own draft
 * (D-3), the read-only detail for everything else.
 */
export function OutboundEmailsPanel({ owner, canSend, newDraftPayload }: OutboundEmailsPanelProps) {
  const { t } = useTranslation()
  const [composerEmailId, setComposerEmailId] = useState<number | null>(null)
  const [justCreatedDraft, setJustCreatedDraft] = useState(false)
  const [detailEmailId, setDetailEmailId] = useState<number | null>(null)
  const createDraft = useCreateOutboundEmailDraft(owner)

  const handleSelect = (item: OutboundEmailListItem) => {
    if (item.status === 'draft') {
      setJustCreatedDraft(false)
      setComposerEmailId(item.id)
    } else {
      setDetailEmailId(item.id)
    }
  }

  const handleNewEmail = () => {
    createDraft.mutate(newDraftPayload, {
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
            {t('outboundEmails.tab.newEmail')}
          </Button>
        </div>
      ) : null}

      <OutboundEmailList owner={owner} onSelect={handleSelect} />

      {composerEmailId !== null ? (
        <OutboundEmailComposerDialog
          key={composerEmailId}
          owner={owner}
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
        <OutboundEmailDetailDialog
          key={detailEmailId}
          owner={owner}
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
