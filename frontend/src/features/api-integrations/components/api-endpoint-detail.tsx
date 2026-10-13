import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ApiEndpointCode } from '@/features/api-integrations/components/api-endpoint-code'
import { ApiFieldsTable, ApiSchemaPanel } from '@/features/api-integrations/components/api-schema-fields'
import { CopyButton } from '@/features/api-integrations/components/copy-button'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import { parametersToFields } from '@/features/api-integrations/schema-fields'
import { buildOperationLink } from '@/features/api-integrations/use-api-reference'

const TAB_CLASS = 'px-2.5 py-1 text-xs'

interface ApiEndpointDetailProps {
  operation: ParsedOperation
  baseUrl: string
}

/** Expanded endpoint: description and field tables on the left, compiled examples on the right. */
export function ApiEndpointDetail({ operation, baseUrl }: ApiEndpointDetailProps) {
  const { t } = useTranslation()
  const parameterFields = useMemo(() => parametersToFields(operation.parameters), [operation.parameters])
  const responseLabel = t('apiIntegrations.docs.response', { status: operation.responseStatus })

  const tabs = [
    operation.parameters.length > 0 ? 'parameters' : null,
    operation.requestSchema ? 'body' : null,
    operation.responseSchema ? 'response' : null,
  ].filter((tab) => tab !== null)

  return (
    <div className="grid min-w-0 gap-3 border-t border-border p-3 xl:grid-cols-2">
      <div className="flex min-w-0 flex-col gap-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <p className="min-w-0 text-sm font-medium">{operation.summary ?? operation.path}</p>
          <CopyButton
            value={buildOperationLink(operation.id)}
            label={t('apiIntegrations.docs.copyLink')}
            successMessage={t('apiIntegrations.docs.linkCopied')}
          >
            <Link aria-hidden />
            {t('apiIntegrations.docs.copyLink')}
          </CopyButton>
        </div>
        {operation.description ? (
          <p className="text-xs whitespace-pre-line text-muted-foreground">{operation.description}</p>
        ) : null}
        {tabs.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t('apiIntegrations.docs.noDetails')}</p>
        ) : (
          <Tabs defaultValue={tabs[0]} className="min-w-0 gap-2">
            <TabsList className="w-fit">
              {tabs.includes('parameters') ? (
                <TabsTrigger value="parameters" className={TAB_CLASS}>{t('apiIntegrations.docs.parameters')}</TabsTrigger>
              ) : null}
              {tabs.includes('body') ? (
                <TabsTrigger value="body" className={TAB_CLASS}>{t('apiIntegrations.docs.body')}</TabsTrigger>
              ) : null}
              {tabs.includes('response') ? (
                <TabsTrigger value="response" className={TAB_CLASS}>{responseLabel}</TabsTrigger>
              ) : null}
            </TabsList>
            <TabsContent value="parameters">
              <ApiFieldsTable fields={parameterFields} caption={t('apiIntegrations.docs.parameters')} />
            </TabsContent>
            <TabsContent value="body">
              {operation.requestSchema ? (
                <ApiSchemaPanel schema={operation.requestSchema} caption={t('apiIntegrations.docs.body')} />
              ) : null}
            </TabsContent>
            <TabsContent value="response">
              {operation.responseSchema ? (
                <ApiSchemaPanel schema={operation.responseSchema} caption={responseLabel} />
              ) : null}
            </TabsContent>
          </Tabs>
        )}
      </div>
      <ApiEndpointCode operation={operation} baseUrl={baseUrl} />
    </div>
  )
}
