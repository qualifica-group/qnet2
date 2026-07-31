import type { LucideIcon } from 'lucide-react'
import {
  Download,
  Eye,
  File,
  FileArchive,
  FileImage,
  FileSpreadsheet,
  FileText,
  Presentation,
  Trash2,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { formatBytes } from '@/features/attachments/format-bytes'
import {
  useAttachmentBinaryActions,
  useAttachmentThumbnail,
} from '@/features/attachments/use-attachment-binary'
import type { Attachment } from '@/features/attachments/types'

type FileKind = 'image' | 'pdf' | 'spreadsheet' | 'word' | 'presentation' | 'archive' | 'text' | 'generic'

const SPREADSHEET_MIME_TYPES = new Set([
  'text/csv',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
])
const WORD_MIME_TYPES = new Set([
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
])
const PRESENTATION_MIME_TYPES = new Set([
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
])
const ARCHIVE_MIME_TYPES = new Set([
  'application/zip',
  'application/x-rar-compressed',
  'application/vnd.rar',
  'application/x-7z-compressed',
  'application/x-tar',
  'application/gzip',
])

/**
 * Per-kind presentation: the type icon and a theme-safe tinted accent (kept
 * as translucent tints so both light and dark themes stay legible). Colour is
 * the ONLY thing that varies per file type — the layout is identical.
 */
const KIND_META: Record<FileKind, { icon: LucideIcon; accent: string }> = {
  image: { icon: FileImage, accent: 'bg-violet-500/12 text-violet-600 dark:text-violet-300' },
  pdf: { icon: FileText, accent: 'bg-rose-500/12 text-rose-600 dark:text-rose-300' },
  spreadsheet: { icon: FileSpreadsheet, accent: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-300' },
  word: { icon: FileText, accent: 'bg-blue-500/12 text-blue-600 dark:text-blue-300' },
  presentation: { icon: Presentation, accent: 'bg-amber-500/12 text-amber-600 dark:text-amber-300' },
  archive: { icon: FileArchive, accent: 'bg-orange-500/12 text-orange-600 dark:text-orange-300' },
  text: { icon: FileText, accent: 'bg-slate-500/12 text-slate-600 dark:text-slate-300' },
  generic: { icon: File, accent: 'bg-slate-500/12 text-slate-600 dark:text-slate-300' },
}

function fileKind(mimeType: string): FileKind {
  if (mimeType.startsWith('image/')) {
    return 'image'
  }
  if (mimeType === 'application/pdf') {
    return 'pdf'
  }
  if (SPREADSHEET_MIME_TYPES.has(mimeType)) {
    return 'spreadsheet'
  }
  if (WORD_MIME_TYPES.has(mimeType)) {
    return 'word'
  }
  if (PRESENTATION_MIME_TYPES.has(mimeType)) {
    return 'presentation'
  }
  if (ARCHIVE_MIME_TYPES.has(mimeType)) {
    return 'archive'
  }
  if (mimeType.startsWith('text/')) {
    return 'text'
  }
  return 'generic'
}

/** Localized file date, blank when missing/invalid — display only. */
function formatDate(value: string): string | null {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

export interface AttachmentTileProps {
  attachment: Attachment
  canDelete: boolean
  onDelete: (attachment: Attachment) => void
}

/**
 * One document row: a type-tinted leading avatar (image thumbnail, otherwise a
 * coloured kind icon), the file name, a metadata strip (format · size · date)
 * and preview/download/delete affordances. Presentational only — the parent
 * owns the confirm + mutation flow behind `onDelete`.
 *
 * Thumbnail and preview/download are NOT plain anchors on the resource's
 * `view_url`/`download_url`: those endpoints sit behind `auth:sanctum` and the
 * browser never sends the Bearer token on a DOM-issued request, so the binary
 * is streamed through the axios client (`use-attachment-binary`).
 */
export function AttachmentTile({ attachment, canDelete, onDelete }: AttachmentTileProps) {
  const { t } = useTranslation()
  const kind = fileKind(attachment.mime_type)
  const isImage = kind === 'image'
  const Icon = KIND_META[kind].icon
  const uploadedOn = formatDate(attachment.created_at)
  const format = attachment.extension?.toUpperCase() ?? null
  const thumbnailUrl = useAttachmentThumbnail(attachment.id, isImage)
  const { openPreview, download } = useAttachmentBinaryActions(attachment)

  return (
    <li className="group relative flex items-center gap-3 rounded-lg border bg-card p-2.5 shadow-sm transition-all duration-200 hover:-translate-y-px hover:border-primary/40 hover:shadow-md">
      <div
        className={cn(
          'flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-md',
          thumbnailUrl ? 'border bg-muted' : KIND_META[kind].accent,
        )}
      >
        {thumbnailUrl ? (
          <img src={thumbnailUrl} alt={attachment.original_name} className="size-full object-cover" />
        ) : (
          <Icon className="size-5" aria-hidden="true" />
        )}
      </div>

      <div className="min-w-0 flex-1">
        <span
          className="block truncate text-xs font-semibold text-foreground"
          title={attachment.original_name}
        >
          {attachment.original_name}
        </span>
        <div className="mt-1 flex items-center gap-1.5 text-[11px] text-muted-foreground">
          {format ? (
            <Badge
              className={cn('border-transparent px-1.5 py-0 text-[10px] font-semibold tracking-wide', KIND_META[kind].accent)}
            >
              {format}
            </Badge>
          ) : null}
          <span>{formatBytes(attachment.size)}</span>
          {uploadedOn ? (
            <>
              <span aria-hidden="true" className="text-muted-foreground/50">
                &middot;
              </span>
              <span>{uploadedOn}</span>
            </>
          ) : null}
        </div>
      </div>

      <div className="flex items-center gap-0.5 opacity-80 transition-opacity group-hover:opacity-100">
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="text-muted-foreground hover:bg-primary/10 hover:text-primary"
          aria-label={t('attachments.preview')}
          onClick={openPreview}
        >
          <Eye aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="text-muted-foreground hover:bg-primary/10 hover:text-primary"
          aria-label={t('attachments.download')}
          onClick={download}
        >
          <Download aria-hidden="true" />
        </Button>
        {canDelete ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
            aria-label={t('attachments.deleteAction')}
            onClick={() => onDelete(attachment)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        ) : null}
      </div>
    </li>
  )
}
