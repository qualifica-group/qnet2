import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { IconPickerControl } from '@/components/icon-picker-control'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { WorkspaceEntriesPicker } from '@/features/workspaces/components/workspace-entries-picker'
import { DESCRIPTION_MAX_LENGTH } from '@/features/workspaces/workspace-schema'
import { useWorkspaceForm } from '@/features/workspaces/use-workspace-form'
import type { Workspace } from '@/features/workspaces/types'

interface WorkspaceFormDialogProps {
  /** The app being edited, or null to create one. */
  workspace: Workspace | null
  onClose: () => void
}

export function WorkspaceFormDialog({ workspace, onClose }: WorkspaceFormDialogProps) {
  const { t } = useTranslation('workspaces')
  const { form, serverError, onSubmit } = useWorkspaceForm({ workspace, onSaved: onClose })
  const { isSubmitting } = form.formState

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle>{t(workspace ? 'form.editTitle' : 'form.newTitle')}</DialogTitle>
          <DialogDescription>{t('form.description')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
            <fieldset disabled={isSubmitting} className="flex max-h-[60vh] flex-col gap-4 overflow-y-auto pr-1">
              <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t('form.name')}</FormLabel>
                      <FormControl>
                        <Input autoComplete="off" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="description"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>{t('form.descriptionLabel')}</FormLabel>
                      <FormControl>
                        <Textarea rows={2} {...field} />
                      </FormControl>
                      <FormDescription className="text-xs">
                        {t('form.descriptionHint', { count: field.value.length, max: DESCRIPTION_MAX_LENGTH })}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="slug"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t('form.slug')}</FormLabel>
                      <FormControl>
                        <Input autoComplete="off" {...field} />
                      </FormControl>
                      <FormDescription className="text-xs">{t('form.slugHint')}</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="icon"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('form.icon')}</FormLabel>
                      <IconPickerControl value={field.value} onChange={field.onChange} disabled={isSubmitting} />
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="color"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('form.color')}</FormLabel>
                      <ColorTokenPicker value={field.value} onChange={field.onChange} disabled={isSubmitting} />
                      <FormDescription className="text-xs">{t('form.colorHint')}</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="sort_order"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('form.sortOrder')}</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="numeric"
                          value={field.value ?? ''}
                          onChange={(event) =>
                            field.onChange(event.target.value === '' ? null : Number(event.target.value))
                          }
                        />
                      </FormControl>
                      <FormDescription className="text-xs">{t('form.sortOrderHint')}</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="navigation_keys"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required>{t('form.entries')}</FormLabel>
                    <FormControl>
                      <div>
                        <WorkspaceEntriesPicker value={field.value} onChange={field.onChange} />
                      </div>
                    </FormControl>
                    <FormDescription className="text-xs">{t('form.entriesHint')}</FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </fieldset>

            {serverError ? (
              <p role="alert" className="text-sm text-destructive">
                {serverError}
              </p>
            ) : null}

            <div className="flex justify-end gap-2">
              <Button type="button" size="sm" variant="secondary" onClick={onClose}>
                {t('form.cancel')}
              </Button>
              <Button type="submit" size="sm" disabled={isSubmitting}>
                {t('form.save')}
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
