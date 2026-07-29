import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Download, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { downloadImportTemplate } from '@/features/imports/api'
import { useImport } from '@/features/imports/use-import'
import { useImportUploadForm } from '@/features/imports/use-import-upload-form'
import { ImportPreview } from '@/features/imports/import-preview'
import { ImportProgress } from '@/features/imports/import-progress'

export interface ImportDialogProps {
  /** Resource key that selects the backend `ImportDefinition` (`/imports/{domain}`). */
  domain: string
  /**
   * Permission prefix gating the action (`{resource}.import`). The caller is
   * responsible for gating visibility with `<Can permission={`${resource}.import`}>`
   * before rendering this dialog; kept here so the prop travels with the
   * dialog wiring even though the component itself does not re-check it.
   */
  resource: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Generic two-phase CSV import wizard (spec 0012), parametrized on `domain`.
 * Orchestration (upload/poll/confirm) lives in `useImport`; this component
 * only routes between the upload step, the progress view and the
 * preview/confirm step based on the current run's status.
 */
export function ImportDialog({ domain, open, onOpenChange }: ImportDialogProps) {
  const { t } = useTranslation()
  const importState = useImport({ domain })
  const uploadForm = useImportUploadForm({ onSubmitFile: importState.upload })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      importState.reset()
      uploadForm.form.reset()
    }
    onOpenChange(next)
  }

  const handleDownloadTemplate = () => {
    downloadImportTemplate(domain).catch(() => {
      toast.error(t('imports.errors.templateDownloadError'))
    })
  }

  const runDetail = importState.runDetail
  const importRun = runDetail?.import_run ?? null
  const status = importRun?.status ?? null

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent className="gap-0" storageKey="sheet-width:imports">
        <SheetHeader>
          <SheetTitle>{t('imports.title')}</SheetTitle>
          <SheetDescription>{t('imports.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-4 overflow-auto p-4">
          {status === null ? (
            <Form {...uploadForm.form}>
              <form onSubmit={uploadForm.onSubmit} className="flex flex-col gap-4" noValidate>
                <Button type="button" variant="outline" onClick={handleDownloadTemplate}>
                  <Download aria-hidden="true" />
                  {t('imports.buttons.downloadTemplate')}
                </Button>

                <FormField
                  control={uploadForm.form.control}
                  name="file"
                  render={({ field: { onChange, onBlur, name, ref } }) => (
                    <FormItem>
                      <FormLabel required>{t('imports.fields.file')}</FormLabel>
                      <FormControl>
                        <Input
                          ref={ref}
                          name={name}
                          onBlur={onBlur}
                          type="file"
                          accept=".csv,text/csv"
                          onChange={(event) => onChange(event.target.files?.[0])}
                        />
                      </FormControl>
                      <FormMessage role="alert" />
                    </FormItem>
                  )}
                />

                {importState.uploadError ? (
                  <p className="text-sm text-destructive" role="alert">
                    {importState.uploadError}
                  </p>
                ) : null}

                <div className="flex justify-end">
                  <Button type="submit" disabled={importState.isUploading}>
                    <Upload aria-hidden="true" />
                    {importState.isUploading
                      ? t('imports.buttons.uploading')
                      : t('imports.buttons.upload')}
                  </Button>
                </div>
              </form>
            </Form>
          ) : null}

          {importRun &&
          (status === 'validating' ||
            status === 'processing' ||
            status === 'completed' ||
            status === 'failed') ? (
            <ImportProgress
              domain={domain}
              importRun={importRun}
              onClose={() => handleOpenChange(false)}
              isPollingStalled={importState.isPollingStalled}
              onRetryPolling={importState.retryPolling}
            />
          ) : null}

          {importRun && status === 'awaiting_confirmation' && runDetail?.preview ? (
            <ImportPreview
              domain={domain}
              importRun={importRun}
              preview={runDetail.preview}
              onConfirm={importState.confirm}
              onCancel={() => handleOpenChange(false)}
              isConfirming={importState.isConfirming}
              confirmError={importState.confirmError}
            />
          ) : null}
        </div>
      </SheetContent>
    </Sheet>
  )
}
