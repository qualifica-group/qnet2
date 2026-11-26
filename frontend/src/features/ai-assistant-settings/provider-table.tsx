import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import { formatPrice } from '@/features/ai-assistant-settings/format'
import { useAiModelProviders } from '@/features/ai-assistant-settings/hooks'

/** Providers of the chosen model that keep no data, with availability, speed and latency when published. */
export function ProviderTable({ modelId }: { modelId: string }) {
  const { t } = useTranslation('aiAssistantSettings')
  const { data, isLoading, isError } = useAiModelProviders(modelId)
  const none = t('providers.notAvailable')

  if (isLoading) {
    return <Skeleton className="h-20 w-full" aria-busy="true" />
  }

  if (isError) {
    return <p role="alert" className="text-sm text-destructive">{t('providers.loadError')}</p>
  }

  if (!data || data.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('providers.empty')}</p>
  }

  return (
    <div className="max-w-full overflow-x-auto rounded-md border border-border">
      <table className="w-full min-w-[34rem] text-left text-xs">
        <thead className="text-muted-foreground">
          <tr>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.provider')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.uptime')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.throughput')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.latency')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.input')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.output')}</th>
            <th scope="col" className="px-2 py-1 font-medium">{t('providers.cacheRead')}</th>
          </tr>
        </thead>
        <tbody>
          {data.map((provider) => (
            <tr key={provider.tag} className="border-t border-border">
              <th scope="row" className="px-2 py-1 font-medium">{provider.name}</th>
              <td className="px-2 py-1">{provider.uptime_pct === null ? none : `${provider.uptime_pct}%`}</td>
              <td className="px-2 py-1">
                {provider.throughput_tps === null ? none : t('providers.throughputUnit', { value: provider.throughput_tps })}
              </td>
              <td className="px-2 py-1">
                {provider.latency_ms === null ? none : t('providers.latencyUnit', { value: provider.latency_ms })}
              </td>
              <td className="px-2 py-1">{formatPrice(provider.pricing.prompt, none)}</td>
              <td className="px-2 py-1">{formatPrice(provider.pricing.completion, none)}</td>
              <td className="px-2 py-1">{formatPrice(provider.pricing.input_cache_read, none)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
