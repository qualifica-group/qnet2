import { useId, useState, type ChangeEvent } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Paperclip, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { FormField } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import {
  PURCHASE_REQUEST_ATTACHABLE,
  PURCHASE_REQUEST_LINE_ATTACHABLE,
} from '@/features/purchase-requests/types'

interface PendingFilesPickerProps {
  files: File[]
  onChange: (files: File[]) => void
  label: string
}

/**
 * Files chosen before the record exists: attachments need a saved owner, so
 * they wait here and are uploaded right after a successful save (D-15).
 */
function PendingFilesPicker({ files, onChange, label }: PendingFilesPickerProps) {
  const { t } = useTranslation()
  const inputId = useId()

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    onChange([...files, ...Array.from(event.target.files ?? [])])
    event.target.value = ''
  }

  return (
    <div className="flex flex-col gap-2">
      <p className="text-xs text-muted-foreground">{t('purchaseRequests.documents.pendingHint')}</p>
      <label htmlFor={inputId} className="sr-only">
        {label}
      </label>
      <Input id={inputId} type="file" multiple className="h-8 text-xs" onChange={handleChange} />
      {files.length > 0 ? (
        <ul className="flex flex-col gap-1">
          {files.map((file, position) => (
            <li
              key={`${file.name}-${position}`}
              className="flex items-center justify-between gap-2 rounded-md border bg-card px-2 py-1 text-xs"
            >
              <span className="truncate">{file.name}</span>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={t('purchaseRequests.documents.removePending', { name: file.name })}
                onClick={() => onChange(files.filter((_, current) => current !== position))}
              >
                <X />
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

interface RequestDocumentsProps {
  control: Control<PurchaseRequestFormValues>
  /** Saved RDA id; absent while creating (files are queued instead). */
  requestId?: number
}

/** Header documents: the live attachment manager once saved, the upload queue before. */
export function RequestDocuments({ control, requestId }: RequestDocumentsProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  if (requestId !== undefined) {
    return (
      <DocumentsSection
        resource={PURCHASE_REQUEST_ATTACHABLE}
        id={requestId}
        canUpload={can('attachments.create')}
        canDelete={can('attachments.delete')}
      />
    )
  }
  return (
    <FormField
      control={control}
      name="pending_files"
      render={({ field }) => (
        <PendingFilesPicker
          files={field.value}
          onChange={field.onChange}
          label={t('purchaseRequests.documents.requestLabel')}
        />
      )}
    />
  )
}

interface LineDocumentsButtonProps {
  control: Control<PurchaseRequestFormValues>
  index: number
  /** Saved line id; absent for a new line (files are queued instead). */
  lineId?: number
  pendingCount: number
}

/** Paperclip of a line that opens its documents (saved line) or its upload queue (new line). */
export function LineDocumentsButton({ control, index, lineId, pendingCount }: LineDocumentsButtonProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const label = `${t('purchaseRequests.lines.documents')} ${index + 1}`

  return (
    <>
      <Button type="button" variant="outline" size="icon-xs" className="relative bg-card" aria-label={label} onClick={() => setOpen(true)}>
        <Paperclip />
        {pendingCount > 0 ? (
          <Badge className="absolute -top-1.5 -right-1.5 h-3.5 min-w-3.5 px-1 text-[9px]">{pendingCount}</Badge>
        ) : null}
      </Button>
      {lineId !== undefined ? (
        <DocumentsDialog resource={PURCHASE_REQUEST_LINE_ATTACHABLE} id={open ? lineId : null} onOpenChange={setOpen} />
      ) : (
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogContent size="sm">
            <DialogHeader>
              <DialogTitle>{label}</DialogTitle>
              <DialogDescription>{t('purchaseRequests.documents.lineDescription')}</DialogDescription>
            </DialogHeader>
            <FormField
              control={control}
              name={`lines.${index}.pending_files`}
              render={({ field }) => (
                <PendingFilesPicker files={field.value} onChange={field.onChange} label={label} />
              )}
            />
          </DialogContent>
        </Dialog>
      )}
    </>
  )
}
