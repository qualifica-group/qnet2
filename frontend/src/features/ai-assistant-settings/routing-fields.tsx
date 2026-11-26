import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import {
  DATA_COLLECTIONS,
  ROUTING_SORTS,
  type SettingsFormValues,
} from '@/features/ai-assistant-settings/settings-form'

/** Routing sent to OpenRouter as the `provider` object of every request. */
export function RoutingFields({ control }: { control: Control<SettingsFormValues> }) {
  const { t } = useTranslation('aiAssistantSettings')

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <FormField
        control={control}
        name="routing.zdr"
        render={({ field }) => (
          <FormItem className="flex items-start justify-between gap-3">
            <div className="flex flex-col gap-0.5">
              <FormLabel>{t('routing.zdr')}</FormLabel>
              <FormDescription>{t('routing.zdrHint')}</FormDescription>
            </div>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="routing.require_parameters"
        render={({ field }) => (
          <FormItem className="flex items-start justify-between gap-3">
            <div className="flex flex-col gap-0.5">
              <FormLabel>{t('routing.requireParameters')}</FormLabel>
              <FormDescription>{t('routing.requireParametersHint')}</FormDescription>
            </div>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="routing.data_collection"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('routing.dataCollection')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {DATA_COLLECTIONS.map((option) => (
                  <SelectItem key={option} value={option}>
                    {t(`routing.dataCollectionOptions.${option}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="routing.sort"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('routing.sort')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {ROUTING_SORTS.map((option) => (
                  <SelectItem key={option} value={option}>
                    {t(`routing.sortOptions.${option}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </FormItem>
        )}
      />
    </div>
  )
}
