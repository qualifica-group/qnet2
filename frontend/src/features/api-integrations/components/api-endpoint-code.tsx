import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { CodeBlock, LanguageTabs } from '@/features/api-integrations/components/api-code-samples'
import { buildOperationSamples } from '@/features/api-integrations/code-samples'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import { buildExampleFromSchema } from '@/features/api-integrations/schema-example'

interface ApiEndpointCodeProps {
  operation: ParsedOperation
  baseUrl: string
}

/** Right column of an endpoint: the compiled request in cURL / JavaScript and the example response. */
export function ApiEndpointCode({ operation, baseUrl }: ApiEndpointCodeProps) {
  const { t } = useTranslation()
  const keyPlaceholder = t('apiIntegrations.docs.keyPlaceholder')
  const samples = useMemo(
    () => buildOperationSamples(operation, baseUrl, keyPlaceholder),
    [operation, baseUrl, keyPlaceholder],
  )
  const responseExample = useMemo(
    () =>
      operation.responseSchema ? JSON.stringify(buildExampleFromSchema(operation.responseSchema), null, 2) : null,
    [operation.responseSchema],
  )

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <LanguageTabs>
        {(language) => <CodeBlock label={t('apiIntegrations.docs.requestExample')} code={samples[language]} />}
      </LanguageTabs>
      {responseExample ? (
        <CodeBlock
          label={t('apiIntegrations.docs.responseExample', { status: operation.responseStatus })}
          code={responseExample}
        />
      ) : null}
    </div>
  )
}
