import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { API_METHODS } from '@/features/api-integrations/openapi-operations'
import type { ApiReferenceState } from '@/features/api-integrations/use-api-reference'
import { useSlashFocus } from '@/features/api-integrations/use-slash-focus'

type ApiReferenceFiltersProps = Pick<ApiReferenceState, 'query' | 'setQuery' | 'methods' | 'toggleMethod' | 'methodCounts'>

/** Search box ("/" focuses it) and one toggle chip per HTTP method with its live count. */
export function ApiReferenceFilters({ query, setQuery, methods, toggleMethod, methodCounts }: ApiReferenceFiltersProps) {
  const { t } = useTranslation()
  const searchRef = useRef<HTMLInputElement>(null)
  useSlashFocus(searchRef)

  return (
    <div className="flex flex-col gap-2">
      <div className="relative">
        <Search
          className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          ref={searchRef}
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('apiIntegrations.docs.searchPlaceholder')}
          aria-label={t('apiIntegrations.docs.searchLabel')}
          aria-keyshortcuts="/"
          className="h-8 pl-8 text-xs md:text-xs"
        />
      </div>
      <div role="group" aria-label={t('apiIntegrations.docs.methodFilter')} className="flex flex-wrap gap-1">
        {API_METHODS.map((method) => (
          <Button
            key={method}
            type="button"
            size="xs"
            variant={methods.has(method) ? 'default' : 'outline'}
            aria-pressed={methods.has(method)}
            onClick={() => toggleMethod(method)}
            className="font-mono text-[11px]"
          >
            {method}
            <span className="font-sans opacity-70">{methodCounts[method]}</span>
          </Button>
        ))}
      </div>
    </div>
  )
}
