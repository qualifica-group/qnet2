import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Card } from '@/components/ui/card'
import { CodeBlock, LanguageTabs } from '@/features/api-integrations/components/api-code-samples'
import { ApiErrorsTable } from '@/features/api-integrations/components/api-errors-table'
import { buildOperationUrl, buildRequestSamples } from '@/features/api-integrations/code-samples'

/** Generic read call used in the key example (the document covers every API). */
const EXAMPLE_PATH = '/leads/1'
const LOGIN_PATH = '/auth/client-login'
const LOGOUT_PATH = '/auth/logout'

interface AuthCardProps {
  step: number
  title: string
  text: string
  children: ReactNode
}

function AuthCard({ step, title, text, children }: AuthCardProps) {
  return (
    <Card className="min-w-0 gap-2 p-3">
      <div className="flex items-center gap-2">
        <span
          aria-hidden
          className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
        >
          {step}
        </span>
        <h4 className="text-sm font-semibold">{title}</h4>
      </div>
      <p className="text-xs text-muted-foreground">{text}</p>
      {children}
    </Card>
  )
}

interface ApiReferenceIntroProps {
  baseUrl: string
}

/** "Introduction" view: the two ways to authenticate with ready examples, common errors, rate limit. */
export function ApiReferenceIntro({ baseUrl }: ApiReferenceIntroProps) {
  const { t } = useTranslation()
  const clientAuth = `Bearer ${t('apiIntegrations.docs.keyPlaceholder')}`
  const userAuth = `Bearer ${t('apiIntegrations.docs.userTokenPlaceholder')}`
  const accept = 'application/json'

  const keyCall = buildRequestSamples({
    method: 'GET',
    url: buildOperationUrl(baseUrl, EXAMPLE_PATH),
    headers: { Authorization: clientAuth, Accept: accept },
    body: null,
  })
  const login = buildRequestSamples({
    method: 'POST',
    url: buildOperationUrl(baseUrl, LOGIN_PATH),
    headers: { Authorization: clientAuth, Accept: accept, 'Content-Type': accept },
    body: { email: 'user@example.com', password: t('apiIntegrations.docs.loginPasswordPlaceholder') },
  })
  const userCall = buildRequestSamples({
    method: 'GET',
    url: buildOperationUrl(baseUrl, EXAMPLE_PATH),
    headers: { Authorization: userAuth, Accept: accept },
    body: null,
  })
  const logout = buildRequestSamples({
    method: 'POST',
    url: buildOperationUrl(baseUrl, LOGOUT_PATH),
    headers: { Authorization: userAuth, Accept: accept },
    body: null,
  })

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <section aria-labelledby="api-docs-auth" className="flex flex-col gap-2">
        <div>
          <h3 id="api-docs-auth" className="text-base font-semibold">{t('apiIntegrations.docs.authTitle')}</h3>
          <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.authIntro')}</p>
        </div>
        <div className="grid min-w-0 gap-3 xl:grid-cols-2">
          <AuthCard
            step={1}
            title={t('apiIntegrations.docs.modeClientTitle')}
            text={t('apiIntegrations.docs.modeClientText')}
          >
            <LanguageTabs>
              {(language) => <CodeBlock label={t('apiIntegrations.docs.curlExample')} code={keyCall[language]} />}
            </LanguageTabs>
          </AuthCard>
          <AuthCard
            step={2}
            title={t('apiIntegrations.docs.modeUserTitle')}
            text={t('apiIntegrations.docs.modeUserText')}
          >
            <LanguageTabs>
              {(language) => (
                <>
                  <CodeBlock label={t('apiIntegrations.docs.loginCurlExample')} code={login[language]} />
                  <CodeBlock label={t('apiIntegrations.docs.userCurlExample')} code={userCall[language]} />
                  <CodeBlock label={t('apiIntegrations.docs.logoutCurlExample')} code={logout[language]} />
                </>
              )}
            </LanguageTabs>
          </AuthCard>
        </div>
      </section>
      <section aria-labelledby="api-docs-errors" className="flex flex-col gap-2">
        <h3 id="api-docs-errors" className="text-base font-semibold">{t('apiIntegrations.docs.errorsTitle')}</h3>
        <ApiErrorsTable />
      </section>
    </div>
  )
}
