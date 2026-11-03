import { useCallback, useMemo, useState, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import {
  AI_MENTION_MODULES,
  type AiMentionModuleDefinition,
} from '@/features/ai-assistant/mention-modules'
import type { AiMentionModule } from '@/features/ai-assistant/types'

/** "@" opens the picker only at the start of the text or after whitespace. */
const TRIGGER_PATTERN = /(?:^|\s)@([^\s@]*)$/

/** DOM id of the n-th option of the module list, for `aria-activedescendant`. */
export function moduleOptionId(listId: string, index: number): string {
  return `${listId}-option-${index}`
}

export interface MentionToken {
  start: number
  end: number
}

type PickerStage =
  | { kind: 'closed' }
  | { kind: 'module'; query: string; token: MentionToken | null }
  | { kind: 'search'; module: AiMentionModule }

interface Options {
  /** `mention_modules` of /config: only these can be picked (AC-022). */
  allowedModules: readonly AiMentionModule[]
  /** False once `max_mentions` is reached. */
  canAddMention: boolean
  /** Strips the typed "@query" from the draft when a module is chosen. */
  onRemoveToken: (token: MentionToken) => void
}

/**
 * State machine of the "@" picker: closed -> module list (driven from the
 * textarea) -> record search (own input). Pure UI state; the server lookup
 * lives in `useAiMentionSearch`.
 */
export function useAiMentionPicker({ allowedModules, canAddMention, onRemoveToken }: Options) {
  const { t } = useTranslation()
  const [stage, setStage] = useState<PickerStage>({ kind: 'closed' })
  const [activeIndex, setActiveIndex] = useState(0)

  const modules = useMemo<AiMentionModuleDefinition[]>(() => {
    const query = stage.kind === 'module' ? stage.query.toLowerCase() : ''
    return AI_MENTION_MODULES.filter(
      (definition) =>
        allowedModules.includes(definition.module) &&
        (query === '' || t(definition.labelKey).toLowerCase().includes(query)),
    )
  }, [allowedModules, stage, t])

  const close = useCallback(() => {
    setStage({ kind: 'closed' })
    setActiveIndex(0)
  }, [])

  /** Called on every textarea change with the text and caret position. */
  const detectTrigger = useCallback(
    (value: string, caret: number) => {
      const match = canAddMention ? TRIGGER_PATTERN.exec(value.slice(0, caret)) : null
      if (match) {
        setStage({ kind: 'module', query: match[1], token: { start: caret - match[1].length - 1, end: caret } })
        setActiveIndex(0)
      } else {
        setStage((current) => (current.kind === 'module' ? { kind: 'closed' } : current))
      }
    },
    [canAddMention],
  )

  const openFromButton = useCallback(() => {
    if (canAddMention) {
      setStage({ kind: 'module', query: '', token: null })
      setActiveIndex(0)
    }
  }, [canAddMention])

  const chooseModule = useCallback(
    (module: AiMentionModule) => {
      if (stage.kind === 'module' && stage.token) {
        onRemoveToken(stage.token)
      }
      setStage({ kind: 'search', module })
      setActiveIndex(0)
    },
    [onRemoveToken, stage],
  )

  /** Returns true when the key was consumed by the module list. */
  const handleTextareaKeyDown = useCallback(
    (event: KeyboardEvent<HTMLTextAreaElement>): boolean => {
      if (stage.kind !== 'module') {
        return false
      }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        const step = event.key === 'ArrowDown' ? 1 : -1
        setActiveIndex((index) => (modules.length === 0 ? 0 : (index + step + modules.length) % modules.length))
      } else if ((event.key === 'Enter' || event.key === 'Tab') && modules[activeIndex]) {
        chooseModule(modules[activeIndex].module)
      } else if (event.key === 'Escape') {
        close()
      } else {
        return false
      }
      event.preventDefault()
      return true
    },
    [activeIndex, chooseModule, close, modules, stage.kind],
  )

  return {
    stage,
    modules,
    activeIndex,
    setActiveIndex,
    close,
    detectTrigger,
    openFromButton,
    chooseModule,
    handleTextareaKeyDown,
  }
}

export type AiMentionPickerState = ReturnType<typeof useAiMentionPicker>
