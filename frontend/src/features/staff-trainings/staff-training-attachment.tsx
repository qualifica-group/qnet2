import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Download, Paperclip, Trash2, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { StaffTrainingActions } from '@/features/staff-trainings/use-staff-training-actions'
import type { StaffTraining } from '@/features/staff-trainings/types'

const ATTACHMENT_ACCEPT = '.pdf,.jpg,.jpeg,.png'

interface StaffTrainingAttachmentProps {
  training: StaffTraining
  actions: StaffTrainingActions
  /** `staff-trainings.manage`: upload, replace and remove; download is open to whoever sees the training. */
  canManage: boolean
}

/** The single certificate: download for who sees the training, upload/replace/remove only with `manage`. */
export function StaffTrainingAttachment({ training, actions, canManage }: StaffTrainingAttachmentProps) {
  const { t } = useTranslation()
  const inputRef = useRef<HTMLInputElement>(null)
  const { attachment } = training

  return (
    <div className="flex flex-wrap items-center gap-2">
      {attachment ? (
        <span className="flex min-w-0 items-center gap-1.5 text-sm">
          <Paperclip aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate">{attachment.name}</span>
        </span>
      ) : (
        <span className="text-sm text-muted-foreground">{t('staffTrainings.detail.noAttachment')}</span>
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
          {t('staffTrainings.detail.download')}
        </Button>
      ) : null}
      {canManage ? (
        <>
          <input
            ref={inputRef}
            type="file"
            hidden
            accept={ATTACHMENT_ACCEPT}
            aria-label={t('staffTrainings.detail.upload')}
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
            {t(attachment ? 'staffTrainings.detail.replace' : 'staffTrainings.detail.upload')}
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
              {t('staffTrainings.detail.remove')}
            </Button>
          ) : null}
        </>
      ) : null}
    </div>
  )
}
