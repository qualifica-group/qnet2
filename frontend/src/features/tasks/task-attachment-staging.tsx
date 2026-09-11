import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Paperclip, Upload, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button, buttonVariants } from '@/components/ui/button'
import { FormSection } from '@/components/form-section'
import { formatBytes } from '@/features/attachments/format-bytes'

export interface TaskAttachmentStagingProps {
  /** Files picked so far, kept in memory only — nothing is uploaded from here (D-7). */
  files: File[]
  onAdd: (files: File[]) => void
  onRemove: (index: number) => void
}

/**
 * Create-only staging area for the task's future attachments (spec 0118
 * D-7/D-8): the task does not exist yet, so there is nothing to upload
 * against — `useTaskForm` uploads these one by one only after the `POST
 * /api/tasks` that returns an id. Mounted exclusively by `TaskFormBody` on
 * create (AC-027): in edit mode the same job belongs to the Documenti tab of
 * the detail (`DocumentsSection`), and running both here would duplicate it.
 */
export function TaskAttachmentStaging({ files, onAdd, onRemove }: TaskAttachmentStagingProps) {
  const { t } = useTranslation()

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(event.target.files ?? [])
    if (picked.length > 0) {
      onAdd(picked)
    }
    // Reset so re-selecting the same file still fires a change event.
    event.target.value = ''
  }

  return (
    <FormSection
      icon={Paperclip}
      title={t('tasks.form.attachments.title')}
      description={t('tasks.form.attachments.description')}
    >
      <div className="flex flex-col gap-2">
        <label
          className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), 'w-fit cursor-pointer')}
        >
          <Upload aria-hidden="true" />
          {t('tasks.form.attachments.add')}
          <input type="file" multiple className="sr-only" onChange={handleChange} />
        </label>

        {files.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t('tasks.form.attachments.empty')}</p>
        ) : (
          <ul className="flex flex-col gap-1.5">
            {files.map((file, index) => (
              <li
                key={`${file.name}-${index}`}
                className="flex items-center gap-2 rounded-md border bg-card px-2.5 py-1.5 text-xs"
              >
                <span className="min-w-0 flex-1 truncate" title={file.name}>
                  {file.name}
                </span>
                <span className="shrink-0 text-muted-foreground">{formatBytes(file.size)}</span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                  aria-label={t('tasks.form.attachments.remove')}
                  onClick={() => onRemove(index)}
                >
                  <X aria-hidden="true" />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </FormSection>
  )
}
