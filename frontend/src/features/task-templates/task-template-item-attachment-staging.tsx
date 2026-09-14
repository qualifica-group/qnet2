import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button, buttonVariants } from '@/components/ui/button'
import { formatBytes } from '@/features/attachments/format-bytes'

export interface TaskTemplateItemAttachmentStagingProps {
  /** Files picked so far for this row, kept in memory only (spec 0124 D-9): nothing uploads until the template — and this row — is saved. */
  files: File[]
  onAdd: (files: File[]) => void
  onRemove: (index: number) => void
  disabled?: boolean
}

/**
 * Row-scoped attachment staging for a NEW template item (spec 0124 D-9): its
 * own component, not a reuse of `features/tasks/task-attachment-staging.tsx`
 * (owned by the parallel, uncommitted 0123 build) — same idea, compact
 * enough to embed inside a `<SortableList>` row instead of behind a
 * top-level `<FormSection>`.
 */
export function TaskTemplateItemAttachmentStaging({
  files,
  onAdd,
  onRemove,
  disabled = false,
}: TaskTemplateItemAttachmentStagingProps) {
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
    <div className="flex flex-col gap-1.5">
      <label
        className={cn(
          buttonVariants({ variant: 'outline', size: 'sm' }),
          'w-fit cursor-pointer',
          disabled && 'pointer-events-none opacity-50',
        )}
      >
        <Upload aria-hidden="true" />
        {t('taskTemplates.form.items.attachmentsAdd')}
        <input type="file" multiple className="sr-only" disabled={disabled} onChange={handleChange} />
      </label>

      {files.length > 0 ? (
        <ul className="flex flex-col gap-1">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${index}`}
              className="flex items-center gap-2 rounded-md border bg-card px-2 py-1 text-xs"
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
                aria-label={t('taskTemplates.form.items.attachmentsRemove')}
                disabled={disabled}
                onClick={() => onRemove(index)}
              >
                <X aria-hidden="true" />
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}
