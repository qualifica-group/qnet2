import { useTranslation } from 'react-i18next'
import { Card } from '@/components/ui/card'

/** Generic read call used in the key example (the document covers every API). */
const EXAMPLE_PATH = '/leads/1'
const LOGIN_PATH = '/auth/client-login'
const LOGOUT_PATH = '/auth/logout'

interface ApiDocsAuthSectionProps {
  baseUrl: string
}

function CodeLine({ label, children }: { label: string; children: string }) {
  return (
    <div className="flex flex-col gap-1">
      <p className="text-xs font-medium">{label}</p>
      <pre
        role="region"
        aria-label={label}
        tabIndex={0}
        className="max-w-full overflow-x-auto rounded-md border border-border bg-surface p-2 font-mono text-xs"
      >
        {children}
      </pre>
    </div>
  )
}

/** "Authentication" section: the two ways a client calls the API, with ready-made curl examples. */
export function ApiDocsAuthSection({ baseUrl }: ApiDocsAuthSectionProps) {
  const { t } = useTranslation()
  const keyHeader = `Authorization: Bearer ${t('apiIntegrations.docs.keyPlaceholder')}`
  const userHeader = `Authorization: Bearer ${t('apiIntegrations.docs.userTokenPlaceholder')}`
  const keyCurl = `curl -H "${keyHeader}" -H "Accept: application/json" "${baseUrl}${EXAMPLE_PATH}"`
  const loginBody = JSON.stringify({
    email: 'user@example.com',
    password: t('apiIntegrations.docs.loginPasswordPlaceholder'),
  })
  const loginCurl = `curl -X POST -H "${keyHeader}" -H "Content-Type: application/json" -H "Accept: application/json" -d '${loginBody}' "${baseUrl}${LOGIN_PATH}"`
  const userCurl = `curl -H "${userHeader}" -H "Accept: application/json" "${baseUrl}${EXAMPLE_PATH}"`
  const logoutCurl = `curl -X POST -H "${userHeader}" -H "Accept: application/json" "${baseUrl}${LOGOUT_PATH}"`

  return (
    <Card className="gap-3 p-3">
      <h3 className="text-base font-semibold">{t('apiIntegrations.docs.authTitle')}</h3>
      <p className="text-sm text-muted-foreground">{t('apiIntegrations.docs.authIntro')}</p>
      <CodeLine label={t('apiIntegrations.docs.baseUrl')}>{baseUrl}</CodeLine>
      <section aria-labelledby="api-docs-mode-client" className="flex flex-col gap-2">
        <h4 id="api-docs-mode-client" className="text-sm font-semibold">
          {t('apiIntegrations.docs.modeClientTitle')}
        </h4>
        <p className="text-sm text-muted-foreground">{t('apiIntegrations.docs.modeClientText')}</p>
        <CodeLine label={t('apiIntegrations.docs.headerExample')}>{keyHeader}</CodeLine>
        <CodeLine label={t('apiIntegrations.docs.curlExample')}>{keyCurl}</CodeLine>
      </section>
      <section aria-labelledby="api-docs-mode-user" className="flex flex-col gap-2">
        <h4 id="api-docs-mode-user" className="text-sm font-semibold">
          {t('apiIntegrations.docs.modeUserTitle')}
        </h4>
        <p className="text-sm text-muted-foreground">{t('apiIntegrations.docs.modeUserText')}</p>
        <CodeLine label={t('apiIntegrations.docs.loginCurlExample')}>{loginCurl}</CodeLine>
        <CodeLine label={t('apiIntegrations.docs.userCurlExample')}>{userCurl}</CodeLine>
        <CodeLine label={t('apiIntegrations.docs.logoutCurlExample')}>{logoutCurl}</CodeLine>
      </section>
    </Card>
  )
}
