import type { KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListChecks, Loader2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Form, FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { useConfirm } from '@/components/confirm-dialog-context'
import { WorkspaceAppearanceSection } from '@/features/workspaces/components/workspace-appearance-section'
import { WorkspaceEntriesPicker } from '@/features/workspaces/components/workspace-entries-picker'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { WorkspaceIdentitySection } from '@/features/workspaces/components/workspace-identity-section'
import { useWorkspaceForm } from '@/features/workspaces/use-workspace-form'
import { workspacePanelTextClass } from '@/features/workspaces/workspace-color'
import type { Workspace } from '@/features/workspaces/types'
import { cn } from '@/lib/utils'

const SHEET_WIDTH = 720

interface WorkspaceFormSheetProps {
  /** The app being edited, or null to create one. */
  workspace: Workspace | null
  onClose: () => void
}

/** Create/edit side panel of an app: identity, appearance and menu entries, with a sticky action bar. */
export function WorkspaceFormSheet({ workspace, onClose }: WorkspaceFormSheetProps) {
  const { t } = useTranslation('workspaces')
  const confirm = useConfirm()
  const { form, serverError, onSubmit, followName, markSlugEdited } = useWorkspaceForm({ workspace, onSaved: onClose })
  const { isSubmitting, isDirty } = form.formState
  const [icon, color, selectedKeys] = useWatch({ control: form.control, name: ['icon', 'color', 'navigation_keys'] })
  const submit = form.handleSubmit(onSubmit)

  const requestClose = async () => {
    if (isDirty && !isSubmitting) {
      const confirmed = await confirm({
        title: t('form.unsavedTitle'),
        description: t('form.unsavedDescription'),
        confirmLabel: t('form.unsavedConfirm'),
        tone: 'warning',
      })
      if (!confirmed) {
        return
      }
    }
    onClose()
  }

  // Ctrl/Cmd+Enter saves from any field, as in the main CRMs.
  const onKeyDown = (event: KeyboardEvent<HTMLFormElement>) => {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
      event.preventDefault()
      void submit()
    }
  }

  return (
    <Sheet open onOpenChange={(open) => (open ? undefined : void requestClose())}>
      <SheetContent className="gap-0 p-0" defaultWidth={SHEET_WIDTH} storageKey="sheet-width:workspaces-form">
        <SheetHeader className="flex-row items-center gap-3 border-b bg-surface py-3 pr-12">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card shadow-xs">
            <WorkspaceIcon name={icon || null} className={cn('size-[18px]', workspacePanelTextClass(color))} />
          </span>
          <div className="min-w-0">
            <SheetTitle className="text-base">{t(workspace ? 'form.editTitle' : 'form.newTitle')}</SheetTitle>
            <SheetDescription className="truncate text-xs">{t('form.description')}</SheetDescription>
          </div>
        </SheetHeader>

        <Form {...form}>
          <form onSubmit={submit} onKeyDown={onKeyDown} noValidate className="flex min-h-0 flex-1 flex-col">
            <fieldset disabled={isSubmitting} className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4">
              <WorkspaceIdentitySection form={form} onNameChange={followName} onSlugChange={markSlugEdited} />
              <WorkspaceAppearanceSection form={form} disabled={isSubmitting} />
              <FormField
                control={form.control}
                name="navigation_keys"
                render={({ field }) => (
                  <FormSection
                    icon={ListChecks}
                    title={t('form.entries')}
                    description={t('form.entriesHint')}
                    aside={<Badge variant="secondary">{t('form.entriesSelected', { count: field.value.length })}</Badge>}
                  >
                    <FormItem>
                      <FormControl>
                        <div>
                          <WorkspaceEntriesPicker value={field.value} onChange={field.onChange} />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  </FormSection>
                )}
              />
            </fieldset>

            <div className="flex shrink-0 flex-wrap items-center gap-2 border-t bg-surface px-4 py-2.5">
              {serverError ? (
                <p role="alert" className="min-w-0 flex-1 text-sm text-destructive">
                  {serverError}
                </p>
              ) : (
                <p className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                  {t('form.footerSummary', { count: selectedKeys.length })}
                </p>
              )}
              <Button type="button" size="sm" variant="secondary" onClick={() => void requestClose()}>
                {t('form.cancel')}
              </Button>
              <Button type="submit" size="sm" disabled={isSubmitting} title={t('form.saveShortcut')}>
                {isSubmitting ? <Loader2 className="size-3.5 animate-spin" aria-hidden /> : null}
                {t('form.save')}
              </Button>
            </div>
          </form>
        </Form>
      </SheetContent>
    </Sheet>
  )
}
