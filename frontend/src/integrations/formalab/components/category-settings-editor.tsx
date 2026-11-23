import { Controller, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import {
  attributeOptions,
  toPayload,
  useCategorySettingsForm,
  type CategorySettingsFormValues,
} from '@/integrations/formalab/category-settings-form'
import type { FormalabCategorySettings } from '@/integrations/formalab/types'
import { useUpdateFormalabCategorySettings } from '@/integrations/formalab/use-formalab-category-settings'

/** Radix Select refuses an empty item value: this stands for "not mapped". */
const NOT_MAPPED = '__none__'

interface CategorySettingsEditorProps {
  categoryId: number
  settings: FormalabCategorySettings
  onClose: () => void
}

/**
 * Edits the category's Formalab settings: the link (root only, a child sees
 * it inherited) and the field map, each Formalab field offering only the
 * work order attributes of a compatible type.
 */
export function CategorySettingsEditor({ categoryId, settings, onClose }: CategorySettingsEditorProps) {
  const { t } = useTranslation('formalab')
  const form = useCategorySettingsForm(settings)
  const save = useUpdateFormalabCategorySettings(categoryId, onClose)
  const inherit = useWatch({ control: form.control, name: 'inherit' })

  const submit = form.handleSubmit((values: CategorySettingsFormValues) => save.mutate(toPayload(settings, values)))

  return (
    <form className="flex flex-col gap-3" onSubmit={(event) => void submit(event)} noValidate>
      {settings.is_root ? (
        <Controller
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <div className="flex items-start gap-2">
              <Switch id="formalab-enabled" checked={field.value} onCheckedChange={field.onChange} />
              <div className="flex flex-col gap-0.5">
                <Label htmlFor="formalab-enabled" className="text-sm">
                  {t('category.enabled')}
                </Label>
                <p className="text-xs text-muted-foreground">{t('category.enabledHelp')}</p>
              </div>
            </div>
          )}
        />
      ) : (
        <Controller
          control={form.control}
          name="inherit"
          render={({ field }) => (
            <div className="flex items-start gap-2">
              <Switch id="formalab-own-map" checked={!field.value} onCheckedChange={(own) => field.onChange(!own)} />
              <div className="flex flex-col gap-0.5">
                <Label htmlFor="formalab-own-map" className="text-sm">
                  {t('category.ownMap')}
                </Label>
                <p className="text-xs text-muted-foreground">{t('category.ownMapHelp')}</p>
              </div>
            </div>
          )}
        />
      )}

      <div className="flex flex-col gap-2">
        <p className="text-xs text-muted-foreground">{t('category.fieldMapHelp')}</p>
        {settings.fields.map((formalabField) => {
          const options = attributeOptions(formalabField, settings.attributes)
          const fieldId = `formalab-map-${formalabField.key}`
          const errorId = `${fieldId}-error`
          return (
            <Controller
              key={formalabField.key}
              control={form.control}
              name={`map.${formalabField.key}`}
              render={({ field, fieldState }) => (
                <div className="grid grid-cols-1 items-center gap-1 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] sm:gap-3">
                  <Label htmlFor={fieldId} className="text-xs">
                    {t(`category.fields.${formalabField.key}`)}
                    {formalabField.is_key ? (
                      <span className="text-muted-foreground"> ({t('category.keyField')})</span>
                    ) : null}
                  </Label>
                  <div className="flex min-w-0 flex-col gap-1">
                    <Select
                      value={field.value === '' ? NOT_MAPPED : field.value}
                      onValueChange={(value) => field.onChange(value === NOT_MAPPED ? '' : value)}
                      disabled={inherit}
                    >
                      <SelectTrigger
                        id={fieldId}
                        size="sm"
                        className="w-full max-w-sm text-xs"
                        aria-invalid={fieldState.error ? true : undefined}
                        aria-describedby={fieldState.error ? errorId : undefined}
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={NOT_MAPPED}>{t('category.notMapped')}</SelectItem>
                        {options.map((attribute) => (
                          <SelectItem key={attribute.code} value={attribute.code}>
                            {attribute.name} ({attribute.code})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {fieldState.error ? (
                      <span id={errorId} role="alert" className="text-xs text-destructive">
                        {fieldState.error.message}
                      </span>
                    ) : null}
                  </div>
                </div>
              )}
            />
          )
        })}
      </div>

      <div className="flex justify-end gap-2">
        <Button type="button" size="sm" variant="outline" onClick={onClose} disabled={save.isPending}>
          {t('category.cancel')}
        </Button>
        <Button type="submit" size="sm" disabled={save.isPending}>
          {t('category.save')}
        </Button>
      </div>
    </form>
  )
}
