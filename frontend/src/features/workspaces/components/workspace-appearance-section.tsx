import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { Palette } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { IconPickerControl } from '@/components/icon-picker-control'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { workspacePanelTextClass } from '@/features/workspaces/workspace-color'
import type { WorkspaceFormValues } from '@/features/workspaces/workspace-schema'
import { cn } from '@/lib/utils'

interface WorkspaceAppearanceSectionProps {
  form: UseFormReturn<WorkspaceFormValues>
  disabled: boolean
}

/** Icon, colour and launcher order of the app, with a live preview of its launcher row. */
export function WorkspaceAppearanceSection({ form, disabled }: WorkspaceAppearanceSectionProps) {
  const { t } = useTranslation('workspaces')

  return (
    <FormSection icon={Palette} title={t('form.appearanceSection')} description={t('form.appearanceSectionHint')}>
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField
          control={form.control}
          name="icon"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('form.icon')}</FormLabel>
              <IconPickerControl value={field.value} onChange={field.onChange} disabled={disabled} />
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
              <ColorTokenPicker value={field.value} onChange={field.onChange} disabled={disabled} />
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
                  min={0}
                  value={field.value ?? ''}
                  onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
                />
              </FormControl>
              <FormDescription className="text-xs">{t('form.sortOrderHint')}</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <LauncherPreview form={form} />
      </div>
    </FormSection>
  )
}

/** Mirrors `WorkspaceLauncherItem`: what users will see in the header launcher. */
function LauncherPreview({ form }: { form: UseFormReturn<WorkspaceFormValues> }) {
  const { t } = useTranslation('workspaces')
  const [name, description, icon, color] = useWatch({
    control: form.control,
    name: ['name', 'description', 'icon', 'color'],
  })

  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-medium">{t('form.preview')}</p>
      <div className="rounded-lg border border-border bg-popover p-2 shadow-sm">
        <div className={cn('flex items-start gap-2 rounded-md px-2 py-1.5 text-sm font-medium', workspacePanelTextClass(color))}>
          <WorkspaceIcon name={icon || null} className="mt-0.5 size-4 shrink-0" />
          <span className="min-w-0 flex-1">
            <span className="block truncate">{name.trim() || t('form.previewPlaceholder')}</span>
            {description.trim() ? (
              <span className="line-clamp-2 block text-xs font-normal break-words text-muted-foreground">
                {description}
              </span>
            ) : null}
          </span>
        </div>
      </div>
      <p className="text-xs text-muted-foreground">{t('form.colorHint')}</p>
    </div>
  )
}
