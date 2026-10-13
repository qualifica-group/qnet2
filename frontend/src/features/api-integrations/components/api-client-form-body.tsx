import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
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
import { Can } from '@/features/auth/can'
import { useApiClientForm } from '@/features/api-integrations/use-api-client-form'
import type { ApiKeyReveal } from '@/features/api-integrations/components/api-key-dialog'
import type { ApiClient } from '@/features/api-integrations/types'

interface ApiClientFormBodyProps {
  client: ApiClient | null
  readOnly: boolean
  onKeyIssued: (reveal: ApiKeyReveal) => void
  onSaved: () => void
  onCancel: () => void
  onRotate: () => void
  onRevoke: () => void
}

/** Create/edit form of an API client; rotate and revoke live in the edit footer, gated by permission. */
export function ApiClientFormBody({
  client,
  readOnly,
  onKeyIssued,
  onSaved,
  onCancel,
  onRotate,
  onRevoke,
}: ApiClientFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useApiClientForm({ client, onKeyIssued, onSaved })
  const isEdit = client !== null
  const { isSubmitting } = form.formState

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
        <fieldset disabled={readOnly || isSubmitting} className="flex max-h-[60vh] flex-col gap-4 overflow-y-auto pr-1">
          <FormField
            control={form.control}
            name="name"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('apiIntegrations.form.name')}</FormLabel>
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
              <FormItem>
                <FormLabel>{t('apiIntegrations.form.description')}</FormLabel>
                <FormControl>
                  <Textarea rows={2} {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="rate_limit_per_minute"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('apiIntegrations.form.rateLimit')}</FormLabel>
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
                  <FormDescription className="text-xs">{t('apiIntegrations.form.rateLimitHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="expires_at"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('apiIntegrations.form.expiresAt')}</FormLabel>
                  <FormControl>
                    <Input
                      type="datetime-local"
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : event.target.value)}
                    />
                  </FormControl>
                  <FormDescription className="text-xs">{t('apiIntegrations.form.expiresAtHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          {client ? (
            <div className="grid gap-0.5">
              <p className="text-sm font-medium">{t('apiIntegrations.form.serviceUser')}</p>
              <p className="text-sm">{client.service_user.name}</p>
              <p className="text-xs text-muted-foreground">{t('apiIntegrations.form.serviceUserHint')}</p>
            </div>
          ) : null}
          {isEdit ? (
            <FormField
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <FormItem className="flex-row items-center gap-3">
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                  <div className="grid gap-0.5">
                    <FormLabel>{t('apiIntegrations.form.isActive')}</FormLabel>
                    <FormDescription className="text-xs">{t('apiIntegrations.form.isActiveHint')}</FormDescription>
                  </div>
                </FormItem>
              )}
            />
          ) : null}
        </fieldset>

        {serverError ? (
          <p role="alert" className="text-sm text-destructive">
            {serverError}
          </p>
        ) : null}

        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex flex-wrap gap-2">
            {isEdit ? (
              <>
                <Can permission="api-clients.update">
                  <Button type="button" size="sm" variant="secondary" onClick={onRotate}>
                    {t('apiIntegrations.actions.rotate')}
                  </Button>
                </Can>
                <Can permission="api-clients.delete">
                  <Button type="button" size="sm" variant="destructive" onClick={onRevoke}>
                    {t('apiIntegrations.actions.revoke')}
                  </Button>
                </Can>
              </>
            ) : null}
          </div>
          <div className="flex gap-2">
            <Button type="button" size="sm" variant="secondary" onClick={onCancel}>
              {t('apiIntegrations.form.cancel')}
            </Button>
            {readOnly ? null : (
              <Button type="submit" size="sm" disabled={isSubmitting}>
                {isSubmitting
                  ? t('apiIntegrations.form.saving')
                  : isEdit
                    ? t('apiIntegrations.form.save')
                    : t('apiIntegrations.form.create')}
              </Button>
            )}
          </div>
        </div>
      </form>
    </Form>
  )
}
