import { useTranslation } from 'react-i18next'
import type { ParsedParameter } from '@/features/api-integrations/openapi-operations'

interface ApiDocsParamsTableProps {
  parameters: ParsedParameter[]
}

export function ApiDocsParamsTable({ parameters }: ApiDocsParamsTableProps) {
  const { t } = useTranslation()

  return (
    <div className="max-w-full overflow-x-auto rounded-md border border-border">
      <table className="w-full text-left text-xs">
        <caption className="sr-only">{t('apiIntegrations.docs.parameters')}</caption>
        <thead className="bg-surface text-muted-foreground">
          <tr>
            <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.parameterName')}</th>
            <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.parameterIn')}</th>
            <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.parameterType')}</th>
            <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.parameterRequired')}</th>
            <th scope="col" className="px-2 py-1.5 font-medium">{t('apiIntegrations.docs.parameterDescription')}</th>
          </tr>
        </thead>
        <tbody>
          {parameters.map((parameter) => (
            <tr key={`${parameter.location}:${parameter.name}`} className="border-t border-border">
              <td className="px-2 py-1.5 font-mono">{parameter.name}</td>
              <td className="px-2 py-1.5">{parameter.location}</td>
              <td className="px-2 py-1.5 font-mono">{parameter.type}</td>
              <td className="px-2 py-1.5">
                {parameter.required ? t('apiIntegrations.docs.yes') : t('apiIntegrations.docs.no')}
              </td>
              <td className="px-2 py-1.5">{parameter.description}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
