import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { useDocumentLayoutCloneForm } from '@/features/document-layouts/use-document-layout-clone-form'
import type { DocumentLayoutCloneSource } from '@/features/document-layouts/types'

interface DocumentLayoutCloneDialogProps {
  /** The layout to clone; `null` keeps the dialog closed. */
  source: DocumentLayoutCloneSource | null
  onClose: () => void
}

interface CloneFormProps {
  source: DocumentLayoutCloneSource
  onClose: () => void
}

function CloneForm({ source, onClose }: CloneFormProps) {
  const { t } = useTranslation()
  const { form, onSubmit, serverError } = useDocumentLayoutCloneForm(source)
  const isSubmitting = form.formState.isSubmitting

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} noValidate className="flex flex-col gap-3 p-4">
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">{t('documentLayouts.form.name')}</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="code"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">{t('documentLayouts.form.code')}</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        {serverError ? (
          <p className="text-xs text-destructive" role="alert">
            {serverError}
          </p>
        ) : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isSubmitting}>
            {isSubmitting ? t('documentLayouts.clone.submitting') : t('documentLayouts.clone.submit')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

/**
 * "Clona layout" (spec 0339): name and code of the copy, pre-filled. The form
 * mounts per opening, so every opening starts from the current source.
 */
export function DocumentLayoutCloneDialog({ source, onClose }: DocumentLayoutCloneDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={source !== null} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="sm" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('documentLayouts.clone.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('documentLayouts.clone.description')}</DialogDescription>
        </DialogHeader>
        {source ? <CloneForm source={source} onClose={onClose} /> : null}
      </DialogContent>
    </Dialog>
  )
}
