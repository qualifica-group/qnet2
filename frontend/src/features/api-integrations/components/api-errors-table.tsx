import { useTranslation } from 'react-i18next'

/** Status codes every endpoint can answer with; the meaning text lives in i18n under `errors.<status>`. */
const COMMON_ERROR_STATUSES = [401, 403, 404, 422, 429] as const

/** Compact table of the common error responses and the envelope they share. */
export function ApiErrorsTable() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.errorsIntro')}</p>
      <pre
        role="region"
        aria-label={t('apiIntegrations.docs.errorEnvelope')}
        tabIndex={0}
        className="max-w-full overflow-auto rounded-md border border-border bg-surface p-2.5 font-mono text-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        {'{ "success": false, "message": "..." }'}
      </pre>
      <div className="max-w-full overflow-x-auto rounded-md border border-border">
        <table className="w-full text-left text-xs">
          <caption className="sr-only">{t('apiIntegrations.docs.errorsTitle')}</caption>
          <thead className="bg-surface text-muted-foreground">
            <tr>
              <th scope="col" className="w-16 px-2 py-1.5 font-medium">{t('apiIntegrations.docs.errorStatus')}</th>
              <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.errorMeaning')}</th>
            </tr>
          </thead>
          <tbody>
            {COMMON_ERROR_STATUSES.map((status) => (
              <tr key={status} className="border-t border-border align-top">
                <td className="px-2 py-1.5 font-mono font-medium">{status}</td>
                <td className="px-2 py-1.5">{t(`apiIntegrations.docs.errors.${status}`)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.rateLimitNote')}</p>
    </div>
  )
}
