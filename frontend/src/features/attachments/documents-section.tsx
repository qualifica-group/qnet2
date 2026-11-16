import { useState, type ChangeEvent, type DragEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertCircle, Download, FolderOpen, Loader2, Upload, UploadCloud } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useConfirm } from '@/components/confirm-dialog-context'
import { AttachmentTile } from '@/features/attachments/attachment-tile'
import { LegacyRecordNote } from '@/components/detail/legacy-record-note'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { useAttachments } from '@/features/attachments/use-attachments'
import { useAttachmentArchiveDownload } from '@/features/attachments/use-attachment-binary'
import type { Attachment } from '@/features/attachments/types'

export interface DocumentsSectionProps {
  /**
   * Polymorphic owner alias sent as `attachable_type` (e.g. `'opportunity'`,
   * per `config('attachments.attachable_types')`) — NOT the plural
   * table-registry resource key the activity log uses.
   */
  resource: string
  id: number
  collection?: string
  canUpload: boolean
  canDelete: boolean
}

const SKELETON_TILES = 4
/** "Scarica tutti" only pays off from two files up: one file has its own download. */
const MIN_DOCUMENTS_FOR_ARCHIVE = 2

/**
 * Self-contained document manager for a polymorphic owner: a compact
 * drag-and-drop upload strip, a grid of files (thumbnail/icon, preview,
 * download, delete), a "download all" ZIP from two files up and its own
 * loading/empty/error states. This component
 * owns no authorization gating of its own — the caller decides when it is
 * authorized to mount (mirrors `ActivityLogSection`).
 */
export function DocumentsSection({
  resource,
  id,
  collection = DOCUMENTS_COLLECTION,
  canUpload,
  canDelete,
}: DocumentsSectionProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { documents, legacyUrl, isLoading, isError, refetch, upload, isUploading, remove } = useAttachments(
    resource,
    id,
    collection,
  )
  const { downloadAll, isDownloadingAll } = useAttachmentArchiveDownload(resource, id, collection)
  const [isDragging, setIsDragging] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const handleFiles = (fileList: FileList | null) => {
    const files = Array.from(fileList ?? [])
    if (files.length === 0) {
      return
    }
    setActionError(null)
    upload(files)
      .then(({ failed }) => {
        if (failed.length > 0) {
          setActionError(
            t('attachments.errors.uploadSome', { count: failed.length, names: failed.join(', ') }),
          )
        }
      })
      .catch(() => setActionError(t('attachments.errors.upload')))
  }

  const handleInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    handleFiles(event.target.files)
    // Reset so re-selecting the same file still fires a change event.
    event.target.value = ''
  }

  const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
    event.preventDefault()
    setIsDragging(false)
    handleFiles(event.dataTransfer.files)
  }

  const handleDelete = async (attachment: Attachment) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('attachments.deleteAction'),
      description: t('attachments.deleteConfirm', { name: attachment.original_name }),
      confirmLabel: t('attachments.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    setActionError(null)
    try {
      await remove(attachment.id)
    } catch {
      setActionError(t('attachments.errors.delete'))
    }
  }

  return (
    <div className="flex flex-col gap-3">
      {canUpload ? (
        <label
          className={cn(
            'group relative flex cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors duration-200',
            'hover:border-primary/50 hover:bg-primary/5 focus-within:border-primary focus-within:ring-[3px] focus-within:ring-ring/50',
            isDragging ? 'border-primary bg-primary/10' : 'border-muted-foreground/25 bg-muted/30',
          )}
          onDragOver={(event) => {
            event.preventDefault()
            setIsDragging(true)
          }}
          onDragLeave={(event) => {
            if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
              setIsDragging(false)
            }
          }}
          onDrop={handleDrop}
        >
          <input
            type="file"
            multiple
            className="sr-only"
            aria-label={t('attachments.dropzoneHint')}
            onChange={handleInputChange}
            disabled={isUploading}
          />
          <span
            aria-hidden="true"
            className={cn(
              'flex size-10 items-center justify-center rounded-full text-primary transition-transform duration-200 group-hover:scale-105',
              isDragging ? 'bg-primary/20' : 'bg-primary/10',
            )}
          >
            {isUploading ? (
              <Loader2 className="size-5 animate-spin" aria-hidden="true" />
            ) : isDragging ? (
              <UploadCloud className="size-5" aria-hidden="true" />
            ) : (
              <Upload className="size-5" aria-hidden="true" />
            )}
          </span>
          <span className="text-xs font-medium text-foreground">
            {isUploading ? t('attachments.uploading') : t('attachments.dropzoneHint')}
          </span>
          {!isUploading ? (
            <span aria-hidden="true" className="text-[11px] text-muted-foreground">
              {t('attachments.dropzoneSubhint')}
            </span>
          ) : null}
        </label>
      ) : null}

      {actionError ? (
        <p
          role="alert"
          className="flex items-center gap-1.5 rounded-md border border-destructive/30 bg-destructive/5 px-2.5 py-1.5 text-xs text-destructive"
        >
          <AlertCircle className="size-3.5 shrink-0" aria-hidden="true" />
          {actionError}
        </p>
      ) : null}

      {isLoading ? (
        <ul className="flex flex-col gap-2">
          {Array.from({ length: SKELETON_TILES }).map((_, index) => (
            <li key={index} className="flex items-center gap-3 rounded-lg border bg-card p-2.5">
              <Skeleton className="size-11 shrink-0 rounded-md" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-3 w-2/3" />
                <Skeleton className="h-2.5 w-1/3" />
              </div>
            </li>
          ))}
        </ul>
      ) : isError ? (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-destructive/40 bg-destructive/5 px-4 py-8 text-center">
          <span className="flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
            <AlertCircle className="size-5" aria-hidden="true" />
          </span>
          <p className="text-xs text-destructive">{t('attachments.errors.load')}</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : documents.length === 0 ? (
        <div className="flex flex-col items-center gap-1.5 rounded-xl border border-dashed border-muted-foreground/25 bg-muted/20 px-4 py-8 text-center">
          <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <FolderOpen className="size-5" aria-hidden="true" />
          </span>
          <p className="text-sm font-medium text-foreground">{t('attachments.empty')}</p>
          <p className="max-w-[32ch] text-xs text-muted-foreground">{t('attachments.emptyHint')}</p>
        </div>
      ) : (
        <>
          {documents.length >= MIN_DOCUMENTS_FOR_ARCHIVE ? (
            <div className="flex justify-end">
              <Button
                type="button"
                variant="secondary"
                size="xs"
                className="cursor-pointer"
                onClick={downloadAll}
                disabled={isDownloadingAll}
              >
                {isDownloadingAll ? (
                  <Loader2 className="animate-spin" aria-hidden="true" />
                ) : (
                  <Download aria-hidden="true" />
                )}
                {isDownloadingAll ? t('attachments.downloadingAll') : t('attachments.downloadAll')}
              </Button>
            </div>
          ) : null}
          <ul className="flex flex-col gap-2">
            {documents.map((document) => (
              <AttachmentTile key={document.id} attachment={document} canDelete={canDelete} onDelete={handleDelete} />
            ))}
          </ul>
        </>
      )}

      <LegacyRecordNote url={legacyUrl} note={t('attachments.legacyNote')} />
    </div>
  )
}
