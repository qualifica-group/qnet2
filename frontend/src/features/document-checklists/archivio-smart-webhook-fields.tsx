import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { Copy } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { ArchivioSmartSettingsFormValues } from '@/features/document-checklists/archivio-smart-settings-schema'
import type { ArchivioSmartSettings } from '@/features/document-checklists/types'

/**
 * Inbound webhook half of the connection (spec 0428): the copyable endpoint URL and the write-only signing
 * secret. Like the token, the saved secret is never read back: `has_webhook_secret` only drives the badge.
 */
export function ArchivioSmartWebhookFields({ settings }: { settings: ArchivioSmartSettings }) {
  const { t } = useTranslation()
  const form = useFormContext<ArchivioSmartSettingsFormValues>()

  async function copyUrl() {
    try {
      await navigator.clipboard.writeText(settings.webhook_url)
      toast.success(t('documentChecklists.connection.webhookUrlCopied'))
    } catch {
      toast.error(t('documentChecklists.connection.webhookUrlCopyError'))
    }
  }

  return (
    <>
      <div className="flex flex-col gap-1">
        <label htmlFor="archivio-smart-webhook-url" className="text-sm font-medium">
          {t('documentChecklists.connection.webhookUrl')}
        </label>
        <div className="flex items-center gap-1.5">
          <Input id="archivio-smart-webhook-url" value={settings.webhook_url} readOnly />
          <Button type="button" variant="secondary" size="sm" onClick={() => void copyUrl()}>
            <Copy aria-hidden="true" />
            {t('documentChecklists.connection.webhookUrlCopy')}
          </Button>
        </div>
      </div>
      <FormField
        control={form.control}
        name="webhook_secret"
        render={({ field }) => (
          <FormItem>
            <div className="flex items-center justify-between gap-2">
              <FormLabel>{t('documentChecklists.connection.webhookSecret')}</FormLabel>
              <Badge variant={settings.has_webhook_secret ? 'default' : 'secondary'}>
                {t(
                  settings.has_webhook_secret
                    ? 'documentChecklists.connection.webhookSecretSet'
                    : 'documentChecklists.connection.webhookSecretNotSet',
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
            <FormDescription>{t('documentChecklists.connection.webhookSecretHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
    </>
  )
}
