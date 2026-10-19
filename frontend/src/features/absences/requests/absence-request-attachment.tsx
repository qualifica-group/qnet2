import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Download, Paperclip, Trash2, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { AbsenceRequestActions } from '@/features/absences/requests/use-absence-request-actions'
import type { AbsenceRequest } from '@/features/absences/types'

const ATTACHMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png'

interface AbsenceRequestAttachmentProps {
  request: AbsenceRequest
  actions: AbsenceRequestActions
}

/** The single optional attachment: download for who sees the request, upload/remove only when `can.attach`. */
export function AbsenceRequestAttachment({ request, actions }: AbsenceRequestAttachmentProps) {
  const { t } = useTranslation()
  const inputRef = useRef<HTMLInputElement>(null)
  const { attachment, can } = request

  return (
    <div className="flex flex-wrap items-center gap-2">
      {attachment ? (
        <span className="flex min-w-0 items-center gap-1.5 text-sm">
          <Paperclip aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate">{attachment.name}</span>
        </span>
      ) : (
        <span className="text-sm text-muted-foreground">{t('absences.requests.detail.noAttachment')}</span>
      )}
      {attachment ? (
        <Button
          type="button"
          size="xs"
          variant="outline"
          disabled={actions.download.isPending}
          onClick={() => actions.download.mutate(attachment.name)}
        >
          <Download aria-hidden="true" />
          {t('absences.requests.detail.download')}
        </Button>
      ) : null}
      {can.attach ? (
        <>
          <input
            ref={inputRef}
            type="file"
            hidden
            accept={ATTACHMENT_ACCEPT}
            aria-label={t('absences.requests.detail.upload')}
            onChange={(event) => {
              const file = event.target.files?.[0]
              if (file) {
                actions.upload.mutate(file)
              }
              event.target.value = ''
            }}
          />
          <Button
            type="button"
            size="xs"
            variant="outline"
            disabled={actions.upload.isPending}
            onClick={() => inputRef.current?.click()}
          >
            <Upload aria-hidden="true" />
            {t(attachment ? 'absences.requests.detail.replace' : 'absences.requests.detail.upload')}
          </Button>
          {attachment ? (
            <Button
              type="button"
              size="xs"
              variant="outline"
              disabled={actions.removeAttachment.isPending}
              onClick={() => actions.removeAttachment.mutate()}
            >
              <Trash2 aria-hidden="true" />
              {t('absences.requests.detail.remove')}
            </Button>
          ) : null}
        </>
      ) : null}
    </div>
  )
}
