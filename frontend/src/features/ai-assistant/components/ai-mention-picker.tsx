import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { useAiMentionSearch } from '@/features/ai-assistant/hooks/use-ai-mention-search'
import {
  moduleOptionId,
  type AiMentionPickerState,
} from '@/features/ai-assistant/hooks/use-ai-mention-picker'
import {
  AI_MENTION_MIN_SEARCH_LENGTH,
  getAiMentionModule,
} from '@/features/ai-assistant/mention-modules'
import type { AiMentionInput, AiMentionModule } from '@/features/ai-assistant/types'

const OPTION_CLASS =
  'flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-left text-xs aria-selected:bg-accent aria-selected:text-accent-foreground'

interface PickerProps {
  picker: AiMentionPickerState
  /** Id of the module list, referenced by the textarea (aria-controls). */
  moduleListId: string
  onPick: (mention: AiMentionInput) => void
  onDismiss: () => void
}

/** Floating panel of the "@" picker: module list, then record search (AC-022). */
export function AiMentionPicker({ picker, moduleListId, onPick, onDismiss }: PickerProps) {
  const { stage } = picker
  if (stage.kind === 'closed') {
    return null
  }
  return (
    <div className="absolute inset-x-0 bottom-full z-20 mb-1 rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md">
      {stage.kind === 'module' ? (
        <ModuleList picker={picker} listId={moduleListId} />
      ) : (
        <RecordSearch module={stage.module} onPick={onPick} onDismiss={onDismiss} />
      )}
    </div>
  )
}

function ModuleList({ picker, listId }: { picker: AiMentionPickerState; listId: string }) {
  const { t } = useTranslation()
  const { modules, activeIndex, setActiveIndex, chooseModule } = picker

  if (modules.length === 0) {
    return <p className="px-2 py-1 text-xs text-muted-foreground">{t('aiAssistant.mentions.noModules')}</p>
  }
  return (
    <ul id={listId} role="listbox" aria-label={t('aiAssistant.mentions.moduleListLabel')} className="max-h-48 overflow-auto">
      {modules.map((definition, index) => (
        <li
          key={definition.module}
          id={moduleOptionId(listId, index)}
          role="option"
          aria-selected={index === activeIndex}
          className={OPTION_CLASS}
          onMouseEnter={() => setActiveIndex(index)}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => chooseModule(definition.module)}
        >
          <definition.icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
          {t(definition.labelKey)}
        </li>
      ))}
    </ul>
  )
}

interface SearchProps {
  module: AiMentionModule
  onPick: (mention: AiMentionInput) => void
  onDismiss: () => void
}

function RecordSearch({ module, onPick, onDismiss }: SearchProps) {
  const { t } = useTranslation()
  const listId = useId()
  const inputRef = useRef<HTMLInputElement | null>(null)
  const [term, setTerm] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)
  const search = useAiMentionSearch(module, term)
  const definition = getAiMentionModule(module)
  const items = search.data ?? []
  const moduleLabel = t(definition.labelKey)

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  const pick = (index: number) => {
    const item = items[index]
    if (item) {
      onPick({ module, id: item.id, label: item.label })
    }
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      const step = event.key === 'ArrowDown' ? 1 : -1
      setActiveIndex((index) => (items.length === 0 ? 0 : (index + step + items.length) % items.length))
    } else if (event.key === 'Enter') {
      pick(activeIndex)
    } else if (event.key === 'Escape') {
      event.stopPropagation()
      onDismiss()
    } else {
      return
    }
    event.preventDefault()
  }

  let status: string | null = null
  if (term.trim().length < AI_MENTION_MIN_SEARCH_LENGTH) {
    status = t('aiAssistant.mentions.minChars', { count: AI_MENTION_MIN_SEARCH_LENGTH })
  } else if (search.isError) {
    status = t('aiAssistant.mentions.searchFailed')
  } else if (search.isFetching && items.length === 0) {
    status = t('aiAssistant.mentions.searching')
  } else if (items.length === 0) {
    status = t('aiAssistant.mentions.noResults')
  }

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-1.5 px-1 pt-0.5">
        <definition.icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
        <Input
          ref={inputRef}
          role="combobox"
          aria-expanded={items.length > 0}
          aria-controls={listId}
          aria-activedescendant={items.length > 0 ? `${listId}-${activeIndex}` : undefined}
          aria-autocomplete="list"
          aria-label={t('aiAssistant.mentions.searchLabel', { module: moduleLabel })}
          placeholder={`${moduleLabel}: ${t('aiAssistant.mentions.searchPlaceholder')}`}
          value={term}
          onChange={(event) => {
            setTerm(event.target.value)
            setActiveIndex(0)
          }}
          onKeyDown={handleKeyDown}
          className="h-7 text-xs md:text-xs"
        />
        <button
          type="button"
          className="rounded-sm text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          aria-label={t('aiAssistant.mentions.close')}
          onClick={onDismiss}
        >
          <X className="size-3.5" aria-hidden="true" />
        </button>
      </div>
      {status ? (
        <p className="px-2 py-1 text-xs text-muted-foreground" aria-live="polite">
          {status}
        </p>
      ) : (
        <ul id={listId} role="listbox" aria-label={t('aiAssistant.mentions.resultListLabel')} className="max-h-48 overflow-auto">
          {items.map((item, index) => (
            <li
              key={item.id}
              id={`${listId}-${index}`}
              role="option"
              aria-selected={index === activeIndex}
              className={cn(OPTION_CLASS, 'flex-col items-start gap-0')}
              onMouseEnter={() => setActiveIndex(index)}
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => pick(index)}
            >
              <span className="w-full truncate">{item.label}</span>
              {item.subtitle ? (
                <span className="w-full truncate text-muted-foreground">{item.subtitle}</span>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
