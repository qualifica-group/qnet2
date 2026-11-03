import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText, Image as ImageIcon, Loader2 } from 'lucide-react'
import { saveBlob } from '@/lib/download'
import { downloadAiAttachment } from '@/features/ai-assistant/api'
import type { AiMessageAttachment } from '@/features/ai-assistant/types'

const IMAGE_MIME_PREFIX = 'image/'

interface Props {
  messageId: number
  attachment: AiMessageAttachment
}

/** Authenticated download through the API client (Blob), never a bare href. */
export function AiAttachmentChip({ messageId, attachment }: Props) {
  const { t } = useTranslation()
  const [status, setStatus] = useState<'idle' | 'loading' | 'failed'>('idle')
  const Icon = attachment.mime_type.startsWith(IMAGE_MIME_PREFIX) ? ImageIcon : FileText

  const download = async () => {
    setStatus('loading')
    try {
      saveBlob(await downloadAiAttachment(messageId, attachment.id), attachment.original_name)
      setStatus('idle')
    } catch {
      setStatus('failed')
    }
  }

  return (
    <span className="inline-flex max-w-full flex-col">
      <button
        type="button"
        onClick={() => void download()}
        disabled={status === 'loading'}
        aria-label={t('aiAssistant.messages.attachmentDownload', { name: attachment.original_name })}
        className="inline-flex max-w-full items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-xs text-card-foreground hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        {status === 'loading' ? (
          <Loader2 className="size-3.5 shrink-0 animate-spin" aria-hidden="true" />
        ) : (
          <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
        )}
        <span className="truncate">{attachment.original_name}</span>
      </button>
      {status === 'failed' ? (
        <span role="alert" className="text-xs text-destructive">
          {t('aiAssistant.messages.attachmentDownloadFailed')}
        </span>
      ) : null}
    </span>
  )
}
