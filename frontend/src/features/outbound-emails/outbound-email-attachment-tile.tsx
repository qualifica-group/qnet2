import type { ReactNode } from 'react'
import { File, FileArchive, FileImage, FileSpreadsheet, FileText } from 'lucide-react'
import { formatBytes } from '@/features/attachments/format-bytes'
import type { OutboundEmailAttachment } from '@/features/outbound-emails/types'

type FileKind = 'image' | 'spreadsheet' | 'archive' | 'document' | 'other'

/** First match wins: MIME fragment -> file kind. */
const MIME_KINDS: Array<[string, FileKind]> = [
  ['image/', 'image'],
  ['spreadsheet', 'spreadsheet'],
  ['excel', 'spreadsheet'],
  ['csv', 'spreadsheet'],
  ['zip', 'archive'],
  ['compressed', 'archive'],
  ['pdf', 'document'],
  ['word', 'document'],
  ['text/', 'document'],
]

const ICON_CLASS = 'size-4'

/** Static elements (not components picked at render time): one icon per file kind. */
const KIND_ICONS: Record<FileKind, ReactNode> = {
  image: <FileImage className={ICON_CLASS} aria-hidden="true" />,
  spreadsheet: <FileSpreadsheet className={ICON_CLASS} aria-hidden="true" />,
  archive: <FileArchive className={ICON_CLASS} aria-hidden="true" />,
  document: <FileText className={ICON_CLASS} aria-hidden="true" />,
  other: <File className={ICON_CLASS} aria-hidden="true" />,
}

function kindOf(mimeType: string): FileKind {
  return MIME_KINDS.find(([fragment]) => mimeType.includes(fragment))?.[1] ?? 'other'
}

function extensionOf(fileName: string): string | null {
  const dot = fileName.lastIndexOf('.')
  return dot > 0 && dot < fileName.length - 1 ? fileName.slice(dot + 1).toUpperCase() : null
}

interface OutboundEmailAttachmentTileProps {
  attachment: OutboundEmailAttachment
  /** Trailing icon button (remove in the composer, download in the detail). */
  action: ReactNode
}

/** File card shared by the composer and the read-only detail: type icon, name, extension and size. */
export function OutboundEmailAttachmentTile({ attachment, action }: OutboundEmailAttachmentTileProps) {
  const extension = extensionOf(attachment.original_name)

  return (
    <li className="flex min-w-0 items-center gap-2.5 rounded-lg border bg-card p-2 shadow-xs">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
        {KIND_ICONS[kindOf(attachment.mime_type)]}
      </span>
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="truncate text-xs font-medium" title={attachment.original_name}>
          {attachment.original_name}
        </span>
        <span className="text-[11px] text-muted-foreground">
          {extension ? `${extension} · ` : null}
          {formatBytes(attachment.size)}
        </span>
      </span>
      {action}
    </li>
  )
}
