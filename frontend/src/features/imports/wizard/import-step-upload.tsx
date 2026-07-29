import { useState, type DragEvent } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { FileSpreadsheet, FileUp, Loader2, Upload } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Separator } from '@/components/ui/separator'
import {
  buildImportWizardUploadSchema,
  type ImportWizardUploadFormValues,
} from '@/features/imports/wizard/import-upload-schema'
import { ImportBackgroundNotice } from '@/features/imports/wizard/import-background-notice'
import { StatTile, StepAlert, StepSectionHeader } from '@/features/imports/wizard/wizard-ui'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

export interface ImportStepUploadProps {
  /** `null` before any file has been uploaded in this session. */
  run: ImportRunDetail | null
  isUploading: boolean
  uploadError: string | null
  onUpload: (file: File) => void
  onContinue: () => void
  /** True once the analysis poll gave up (see `useImportWizard`). */
  isPollingStalled?: boolean
  onRetryPolling?: () => void
}

const FILE_ACCEPT =
  '.csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel'

const BYTES_PER_KB = 1024
const BYTES_PER_MB = BYTES_PER_KB * 1024

/** Human-readable file size for the selected-file chip (display only). */
function formatFileSize(bytes: number): string {
  if (bytes >= BYTES_PER_MB) return `${(bytes / BYTES_PER_MB).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / BYTES_PER_KB))} KB`
}

/**
 * Upload step (AC-020): a drag-and-drop dropzone around the same file form
 * while no run exists yet (drop/browse only sets the form value — validation
 * and submit are unchanged); once the server-side `AnalyzeImportJob` finishes
 * (`status` moves past `analyzing`), shows the detected columns/rows/
 * duplicate-columns summary and an explicit "continue" action — the user
 * reviews the analysis before proceeding.
 */
export function ImportStepUpload({
  run,
  isUploading,
  uploadError,
  onUpload,
  onContinue,
  isPollingStalled = false,
  onRetryPolling,
}: ImportStepUploadProps) {
  const { t } = useTranslation('importWizard')
  const [isDragging, setIsDragging] = useState(false)
  const form = useForm<ImportWizardUploadFormValues>({
    resolver: zodResolver(buildImportWizardUploadSchema(t)),
  })

  const onSubmit = form.handleSubmit((values) => onUpload(values.file))
  const selectedFile = form.watch('file')

  if (run === null) {
    const handleDrop = (event: DragEvent<HTMLLabelElement>, onChange: (file: File | undefined) => void) => {
      event.preventDefault()
      setIsDragging(false)
      const file = event.dataTransfer.files?.[0]
      if (file) onChange(file)
    }

    return (
      <Form {...form}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField
            control={form.control}
            name="file"
            render={({ field: { onChange, onBlur, name, ref } }) => (
              <FormItem>
                <FormLabel required>{t('upload.fileLabel')}</FormLabel>
                {/* The dropzone is a <label> wrapping the (visually hidden) input:
                    clicking opens the native picker, dropping sets the same form value. */}
                <label
                  className={cn(
                    'group flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors',
                    'focus-within:ring-[3px] focus-within:ring-ring/50 hover:border-primary/50 hover:bg-primary/5',
                    isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25',
                  )}
                  onDragOver={(event) => {
                    event.preventDefault()
                    setIsDragging(true)
                  }}
                  onDragLeave={(event) => {
                    if (!event.currentTarget.contains(event.relatedTarget as Node | null)) setIsDragging(false)
                  }}
                  onDrop={(event) => handleDrop(event, onChange)}
                >
                  <FormControl>
                    <input
                      ref={ref}
                      name={name}
                      onBlur={onBlur}
                      type="file"
                      className="sr-only"
                      accept={FILE_ACCEPT}
                      onChange={(event) => onChange(event.target.files?.[0])}
                    />
                  </FormControl>
                  <span
                    className={cn(
                      'mb-1.5 flex size-12 items-center justify-center rounded-full transition-colors',
                      selectedFile
                        ? 'bg-primary/10 text-primary'
                        : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary',
                    )}
                  >
                    {selectedFile ? (
                      <FileSpreadsheet className="size-6" aria-hidden="true" />
                    ) : (
                      <FileUp className="size-6" aria-hidden="true" />
                    )}
                  </span>
                  {selectedFile ? (
                    <>
                      <span className="max-w-full truncate text-sm font-medium">{selectedFile.name}</span>
                      <span className="text-xs text-muted-foreground">
                        {formatFileSize(selectedFile.size)} · {t('upload.dropzone.replace')}
                      </span>
                    </>
                  ) : (
                    <>
                      <span className="text-sm font-medium">{t('upload.dropzone.title')}</span>
                      <span className="text-xs text-muted-foreground">{t('upload.dropzone.browse')}</span>
                      <span className="mt-1 text-[11px] text-muted-foreground/80">{t('upload.dropzone.formats')}</span>
                    </>
                  )}
                </label>
                <FormMessage role="alert" />
              </FormItem>
            )}
          />

          {uploadError ? <StepAlert>{uploadError}</StepAlert> : null}

          <div className="flex justify-end">
            <Button type="submit" disabled={isUploading}>
              {isUploading ? (
                <Loader2 className="animate-spin" aria-hidden="true" />
              ) : (
                <Upload aria-hidden="true" />
              )}
              {isUploading ? t('upload.uploading') : t('upload.submit')}
            </Button>
          </div>
        </form>
      </Form>
    )
  }

  if (run.status === 'analyzing') {
    return (
      <ImportBackgroundNotice
        label={t('upload.analyzing')}
        isStalled={isPollingStalled}
        onRetry={onRetryPolling}
      />
    )
  }

  const columnsCount = run.detected_columns?.length ?? 0
  const duplicateCount = run.detected_columns?.filter((column) => column.duplicate).length ?? 0

  return (
    <div className="flex flex-col gap-4">
      <StepSectionHeader
        icon={FileSpreadsheet}
        title={t('upload.summaryTitle')}
        description={run.original_filename}
      />
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <StatTile label={t('upload.columnsLabel')} value={columnsCount} />
        <StatTile label={t('upload.rowsLabel')} value={run.total_rows} />
        <StatTile
          label={t('upload.duplicateColumnsLabel')}
          value={duplicateCount > 0 ? duplicateCount : t('upload.noDuplicateColumns')}
          tone={duplicateCount > 0 ? 'destructive' : 'default'}
        />
      </div>

      <Separator />

      <div className="flex justify-end">
        <Button type="button" onClick={onContinue}>
          {t('upload.continue')}
        </Button>
      </div>
    </div>
  )
}
