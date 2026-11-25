import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Skeleton } from '@/components/ui/skeleton'
import { useAiModels } from '@/features/ai-assistant-settings/hooks'
import { formatPrice } from '@/features/ai-assistant-settings/format'
import type { AiModelOption } from '@/features/ai-assistant-settings/types'

const MODELS_PAGE_SIZE = 25

interface ModelPickerProps {
  value: string
  onChange: (modelId: string) => void
}

function ModelRow({ model }: { model: AiModelOption }) {
  const { t } = useTranslation('aiAssistantSettings')
  const inputId = `ai-model-${model.id}`

  return (
    <Label
      htmlFor={inputId}
      className="flex cursor-pointer items-start gap-2 rounded-md border border-border bg-card p-2 has-[[data-state=checked]]:border-primary"
    >
      <RadioGroupItem id={inputId} value={model.id} className="mt-0.5" />
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="truncate text-sm font-medium">{model.name}</span>
        <span className="truncate text-xs font-normal text-muted-foreground">{model.id}</span>
        <span className="flex flex-wrap gap-x-3 text-xs font-normal text-muted-foreground">
          <span>{t('model.input')}: {formatPrice(model.pricing.prompt, t('model.notAvailable'))}</span>
          <span>{t('model.output')}: {formatPrice(model.pricing.completion, t('model.notAvailable'))}</span>
          <span>{t('model.cacheRead')}: {formatPrice(model.pricing.input_cache_read, t('model.notAvailable'))}</span>
          <span>{t('model.context')}: {t('model.tokens', { count: model.context_length })}</span>
        </span>
      </span>
    </Label>
  )
}

/** Searchable list of the models the backend read from OpenRouter; the choice is a plain model id. */
export function ModelPicker({ value, onChange }: ModelPickerProps) {
  const { t } = useTranslation('aiAssistantSettings')
  const [search, setSearch] = useState('')
  const [limit, setLimit] = useState(MODELS_PAGE_SIZE)
  const { data, isLoading, isError } = useAiModels(search.trim(), limit)
  const total = data?.pagination.total ?? 0

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-col gap-1">
        <Label htmlFor="ai-model-search">{t('model.search')}</Label>
        <Input
          id="ai-model-search"
          value={search}
          placeholder={t('model.searchPlaceholder')}
          autoComplete="off"
          onChange={(event) => {
            setSearch(event.target.value)
            setLimit(MODELS_PAGE_SIZE)
          }}
        />
      </div>
      <p className="text-xs text-muted-foreground">{t('model.current', { id: value })}</p>

      {isLoading ? (
        <Skeleton className="h-40 w-full" aria-busy="true" />
      ) : isError ? (
        <p role="alert" className="text-sm text-destructive">{t('model.loadError')}</p>
      ) : data && data.items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('model.empty')}</p>
      ) : (
        <RadioGroup
          value={value}
          onValueChange={onChange}
          aria-label={t('model.list')}
          className="max-h-96 gap-1.5 overflow-y-auto"
        >
          {data?.items.map((model) => <ModelRow key={model.id} model={model} />)}
        </RadioGroup>
      )}

      {data && data.items.length < total ? (
        <Button type="button" size="sm" variant="secondary" className="self-start" onClick={() => setLimit(limit + MODELS_PAGE_SIZE)}>
          {t('model.showMore')}
        </Button>
      ) : null}
    </div>
  )
}
