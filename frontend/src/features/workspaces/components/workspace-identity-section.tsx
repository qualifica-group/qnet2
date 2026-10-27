import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { IdCard } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { DESCRIPTION_MAX_LENGTH, type WorkspaceFormValues } from '@/features/workspaces/workspace-schema'
import { WORKSPACE_PATH_PREFIX } from '@/features/workspaces/workspace-path'

interface WorkspaceIdentitySectionProps {
  form: UseFormReturn<WorkspaceFormValues>
  onNameChange: (name: string) => void
  onSlugChange: (slug: string) => void
}

/** Name, URL address and launcher description of the app. */
export function WorkspaceIdentitySection({ form, onNameChange, onSlugChange }: WorkspaceIdentitySectionProps) {
  const { t } = useTranslation('workspaces')

  return (
    <FormSection icon={IdCard} title={t('form.identitySection')} description={t('form.identitySectionHint')}>
      <FormField
        control={form.control}
        name="name"
        render={({ field }) => (
          <FormItem>
            <FormLabel required>{t('form.name')}</FormLabel>
            <FormControl>
              <Input
                autoComplete="off"
                autoFocus
                placeholder={t('form.namePlaceholder')}
                {...field}
                onChange={(event) => {
                  field.onChange(event)
                  onNameChange(event.target.value)
                }}
              />
            </FormControl>
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
            <div className="flex min-w-0">
              <span
                className="inline-flex shrink-0 items-center rounded-l-md border border-r-0 border-field-border bg-surface px-2.5 font-mono text-xs text-muted-foreground"
                aria-hidden
              >
                {WORKSPACE_PATH_PREFIX}/
              </span>
              <FormControl>
                <Input
                  autoComplete="off"
                  spellCheck={false}
                  className="rounded-l-none font-mono"
                  {...field}
                  onChange={(event) => {
                    field.onChange(event)
                    onSlugChange(event.target.value)
                  }}
                />
              </FormControl>
            </div>
            <FormDescription className="text-xs">{t('form.slugHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={form.control}
        name="description"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('form.descriptionLabel')}</FormLabel>
            <FormControl>
              <Textarea rows={2} placeholder={t('form.descriptionPlaceholder')} {...field} />
            </FormControl>
            <FormDescription className="text-xs">
              {t('form.descriptionHint', { count: field.value.length, max: DESCRIPTION_MAX_LENGTH })}
            </FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}
