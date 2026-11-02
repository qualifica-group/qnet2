import { useId, useRef, type ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Paperclip, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { SUPPORT_REQUEST_MAX_ATTACHMENTS } from '@/features/support-requests/support-request-schema'

interface SupportRequestAttachmentsProps {
  files: File[]
  error: string | undefined
  onAdd: (files: File[]) => void
  onRemove: (index: number) => void
}

/** Optional files (screenshots): a file input that appends, plus the chosen list with a remove button each. */
export function SupportRequestAttachments({ files, error, onAdd, onRemove }: SupportRequestAttachmentsProps) {
  const { t } = useTranslation()
  const inputId = useId()
  const hintId = useId()
  const errorId = useId()
  const inputRef = useRef<HTMLInputElement>(null)

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    onAdd(Array.from(event.target.files ?? []))
    // Resetting lets the same file be picked again after a removal.
    event.target.value = ''
  }

  return (
    <div className="grid gap-2">
      <Label htmlFor={inputId}>{t('supportRequests.attachmentsLabel')}</Label>
      <input
        ref={inputRef}
        id={inputId}
        type="file"
        multiple
        className="sr-only"
        aria-describedby={error ? `${hintId} ${errorId}` : hintId}
        aria-invalid={error ? true : undefined}
        onChange={handleChange}
      />
      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          disabled={files.length >= SUPPORT_REQUEST_MAX_ATTACHMENTS}
          onClick={() => inputRef.current?.click()}
        >
          <Paperclip aria-hidden="true" />
          {t('supportRequests.attachmentsAdd')}
        </Button>
        <span id={hintId} className="text-xs text-muted-foreground">
          {t('supportRequests.attachmentsHint', { max: SUPPORT_REQUEST_MAX_ATTACHMENTS })}
        </span>
      </div>
      {files.length > 0 ? (
        <ul className="grid gap-1">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${index}`}
              className="flex items-center justify-between gap-2 rounded-md border border-input bg-card px-2 py-1 text-xs"
            >
              <span className="min-w-0 truncate">{file.name}</span>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0"
                aria-label={t('supportRequests.removeAttachment', { name: file.name })}
                onClick={() => onRemove(index)}
              >
                <X className="size-3.5" aria-hidden="true" />
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
    </div>
  )
}
