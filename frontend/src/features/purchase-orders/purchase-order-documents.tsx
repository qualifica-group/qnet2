import { useId, type ChangeEvent } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { FormField } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { PURCHASE_ORDER_ATTACHABLE } from '@/features/purchase-orders/types'

interface PendingFilesPickerProps {
  files: File[]
  onChange: (files: File[]) => void
  label: string
}

/**
 * Files chosen before the ODA exists: attachments need a saved owner, so they
 * wait here and are uploaded right after a successful save (on the first ODA
 * when copies are created, D-16).
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
      <p className="text-xs text-muted-foreground">{t('purchaseOrderForm.documents.pendingHint')}</p>
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
                aria-label={t('purchaseOrderForm.documents.removePending', { name: file.name })}
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

interface PurchaseOrderDocumentsProps {
  control: Control<PurchaseOrderFormValues>
  /** Saved ODA id; absent while creating (files are queued instead). */
  orderId?: number
}

/** ODA documents: the live attachment manager once saved, the upload queue before. */
export function PurchaseOrderDocuments({ control, orderId }: PurchaseOrderDocumentsProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()

  if (orderId !== undefined) {
    return (
      <DocumentsSection
        resource={PURCHASE_ORDER_ATTACHABLE}
        id={orderId}
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
          label={t('purchaseOrderForm.documents.orderLabel')}
        />
      )}
    />
  )
}
