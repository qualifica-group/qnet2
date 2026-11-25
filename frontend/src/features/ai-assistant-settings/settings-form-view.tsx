import { useTranslation } from 'react-i18next'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useUpdateAiAssistantSettings } from '@/features/ai-assistant-settings/hooks'
import { ModelPicker } from '@/features/ai-assistant-settings/model-picker'
import { ProviderTable } from '@/features/ai-assistant-settings/provider-table'
import { RoutingFields } from '@/features/ai-assistant-settings/routing-fields'
import {
  buildSettingsSchema,
  toFormValues,
  toPayload,
  type SettingsFormValues,
} from '@/features/ai-assistant-settings/settings-form'
import type { AiAssistantSettings } from '@/features/ai-assistant-settings/types'

const SERVER_FIELDS = [
  'enabled',
  'model_id',
  'instructions',
  'spend_cap_person_usd',
  'spend_cap_total_usd',
  'routing.zdr',
  'routing.data_collection',
  'routing.require_parameters',
  'routing.sort',
] as const

/** The settings of the assistant as one form: the super-admin edits and saves them together (spec 0433). */
export function SettingsFormView({ settings }: { settings: AiAssistantSettings }) {
  const { t, i18n } = useTranslation('aiAssistantSettings')
  const update = useUpdateAiAssistantSettings()
  const form = useForm<SettingsFormValues>({
    resolver: zodResolver(buildSettingsSchema(t, settings.instructions_max_chars)),
    defaultValues: toFormValues(settings),
  })
  const modelId = useWatch({ control: form.control, name: 'model_id' })
  const instructionsLength = useWatch({ control: form.control, name: 'instructions' }).length

  function onSubmit(values: SettingsFormValues) {
    update.mutate(toPayload(values), {
      onSuccess: (saved) => {
        form.reset(toFormValues(saved))
        toast.success(t('saved'))
      },
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, [...SERVER_FIELDS])) {
          toast.error(t('saveError'))
        }
      },
    })
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
        <Card className="gap-2 p-3">
          <CardHeader className="p-0">
            <CardTitle className="text-base">{t('general.title')}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-3 p-0">
            <FormField
              control={form.control}
              name="enabled"
              render={({ field }) => (
                <FormItem className="flex items-start justify-between gap-3">
                  <div className="flex flex-col gap-0.5">
                    <FormLabel>{t('general.enabled')}</FormLabel>
                    <FormDescription>{t('general.enabledHint')}</FormDescription>
                  </div>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-medium">{t('general.keyLabel')}</span>
              <Badge variant={settings.openrouter_key_present ? 'default' : 'destructive'}>
                {t(settings.openrouter_key_present ? 'general.keyPresent' : 'general.keyMissing')}
              </Badge>
              <span className="text-xs text-muted-foreground">{t('general.keyHint')}</span>
            </div>
          </CardContent>
        </Card>

        <Card className="gap-2 p-3">
          <CardHeader className="p-0">
            <CardTitle className="text-base">{t('model.title')}</CardTitle>
            <CardDescription>{t('model.description')}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-3 p-0">
            <FormField
              control={form.control}
              name="model_id"
              render={({ field }) => (
                <FormItem>
                  <ModelPicker value={field.value} onChange={field.onChange} />
                  <FormMessage />
                </FormItem>
              )}
            />
            <div className="flex flex-col gap-1">
              <h3 className="text-sm font-semibold">{t('providers.title')}</h3>
              <p className="text-xs text-muted-foreground">{t('providers.description')}</p>
              <ProviderTable modelId={modelId} />
            </div>
          </CardContent>
        </Card>

        <Card className="gap-2 p-3">
          <CardHeader className="p-0">
            <CardTitle className="text-base">{t('routing.title')}</CardTitle>
            <CardDescription>{t('routing.description')}</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            <RoutingFields control={form.control} />
          </CardContent>
        </Card>

        <Card className="gap-2 p-3">
          <CardHeader className="p-0">
            <CardTitle className="text-base">{t('instructions.title')}</CardTitle>
            <CardDescription>{t('instructions.description')}</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            <FormField
              control={form.control}
              name="instructions"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('instructions.label')}</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={16} className="font-mono text-xs" />
                  </FormControl>
                  <FormDescription>
                    {t('instructions.counter', { count: instructionsLength, max: settings.instructions_max_chars })}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Card className="gap-2 p-3">
          <CardHeader className="p-0">
            <CardTitle className="text-base">{t('caps.title')}</CardTitle>
            <CardDescription>{t('caps.description')}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 p-0 sm:grid-cols-2">
            {(['spend_cap_person_usd', 'spend_cap_total_usd'] as const).map((name) => (
              <FormField
                key={name}
                control={form.control}
                name={name}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t(name === 'spend_cap_person_usd' ? 'caps.person' : 'caps.total')}</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="decimal" autoComplete="off" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            ))}
          </CardContent>
        </Card>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-muted-foreground">
            {settings.updated_by && settings.updated_at
              ? t('lastUpdate', {
                  name: settings.updated_by.name,
                  date: new Date(settings.updated_at).toLocaleString(i18n.language),
                })
              : t('neverUpdated')}
          </p>
          <Button type="submit" disabled={update.isPending}>
            {t(update.isPending ? 'actions.saving' : 'actions.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}
