import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildArchivioSmartSettingsSchema,
  type ArchivioSmartSettingsFormValues,
} from '@/features/document-checklists/archivio-smart-settings-schema'
import { ArchivioSmartConnectionTestResult } from '@/features/document-checklists/archivio-smart-connection-test-result'
import { ArchivioSmartWebhookFields } from '@/features/document-checklists/archivio-smart-webhook-fields'
import type { useArchivioSmartConnection } from '@/features/document-checklists/use-archivio-smart-connection'
import type { ArchivioSmartSettings } from '@/features/document-checklists/types'

interface ArchivioSmartConnectionFormProps {
  settings: ArchivioSmartSettings
  connection: ReturnType<typeof useArchivioSmartConnection>
  onClose: () => void
}

/** Base URL + write-only token and webhook secret (spec 0428). The saved token is never read back: `has_token` only drives the badge. */
export function ArchivioSmartConnectionForm({ settings, connection, onClose }: ArchivioSmartConnectionFormProps) {
  const { t } = useTranslation()
  const { save, test } = connection
  const form = useForm<ArchivioSmartSettingsFormValues>({
    resolver: zodResolver(buildArchivioSmartSettingsSchema(t)),
    defaultValues: { base_url: settings.base_url ?? '', token: '', webhook_secret: '' },
  })
  const canTest = settings.has_token && Boolean(settings.base_url)

  function onSubmit(values: ArchivioSmartSettingsFormValues) {
    save.mutate(values, {
      onSuccess: () => {
        form.resetField('token', { defaultValue: '' })
        form.resetField('webhook_secret', { defaultValue: '' })
        test.reset()
        toast.success(t('documentChecklists.connection.saved'))
      },
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, ['base_url', 'token', 'webhook_secret'])) {
          toast.error(t('documentChecklists.connection.saveError'))
        }
      },
    })
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-3" noValidate>
        <FormField
          control={form.control}
          name="base_url"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('documentChecklists.connection.baseUrl')}</FormLabel>
              <FormControl>
                <Input {...field} type="url" inputMode="url" autoComplete="off" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="token"
          render={({ field }) => (
            <FormItem>
              <div className="flex items-center justify-between gap-2">
                <FormLabel>{t('documentChecklists.connection.token')}</FormLabel>
                <Badge variant={settings.has_token ? 'default' : 'secondary'}>
                  {t(
                    settings.has_token
                      ? 'documentChecklists.connection.tokenSet'
                      : 'documentChecklists.connection.tokenNotSet',
                  )}
                </Badge>
              </div>
              <FormControl>
                <Input
                  {...field}
                  type="password"
                  autoComplete="new-password"
                  placeholder={t('documentChecklists.connection.tokenPlaceholder')}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <ArchivioSmartWebhookFields settings={settings} />
        <ArchivioSmartConnectionTestResult test={test} />
        <DialogFooter>
          <Button type="button" variant="secondary" disabled={!canTest || test.isPending} onClick={() => test.mutate()}>
            {t('documentChecklists.actions.testConnection')}
          </Button>
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('documentChecklists.connection.close')}
          </Button>
          <Button type="submit" disabled={save.isPending}>
            {t(save.isPending ? 'documentChecklists.connection.saving' : 'documentChecklists.connection.save')}
          </Button>
        </DialogFooter>
      </form>
    </Form>
  )
}
