import { useCallback, useMemo, useRef, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import {
  createComposerSchema,
  EMPTY_COMPOSER_VALUES,
  type AiComposerValues,
} from '@/features/ai-assistant/composer-schema'
import { useAiPageGuide } from '@/features/ai-assistant/hooks/use-ai-page-guide'
import { useSendAiMessage } from '@/features/ai-assistant/hooks/use-send-ai-message'
import { classifyAiSendError, type AiSendErrorKind } from '@/features/ai-assistant/send-error'
import type {
  AiAssistantConfig,
  AiConversation,
  AiMentionInput,
  AiTurn,
} from '@/features/ai-assistant/types'

export interface UseAiComposerOptions {
  config: AiAssistantConfig
  conversationId: number | null
  onConversationCreated: (conversation: AiConversation) => void
  /** Receives the queued turn so the chat can follow it by polling. */
  onTurnStarted: (turn: AiTurn) => void
  /** A turn of this conversation is not closed yet: one turn at a time (D-16). */
  turnActive: boolean
  /** Pins the `general` guide (the /ai-assistant page, AC-024). */
  forceGeneralGuide?: boolean
}

/**
 * Orchestrates the composer: validated draft (RHF + Zod from the /config
 * limits), page guide, send (message saved, turn queued) and restoration of
 * draft, files and mentions when the POST is refused and nothing was saved.
 */
export function useAiComposer({
  config,
  conversationId,
  onConversationCreated,
  onTurnStarted,
  turnActive,
  forceGeneralGuide = false,
}: UseAiComposerOptions) {
  const { t } = useTranslation()
  const { limits } = config
  const schema = useMemo(() => createComposerSchema(limits, t), [limits, t])
  const form = useForm<AiComposerValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY_COMPOSER_VALUES,
  })
  const guide = useAiPageGuide(limits.guide_max_chars, forceGeneralGuide)
  const sender = useSendAiMessage()
  const textareaRef = useRef<HTMLTextAreaElement | null>(null)
  const [failure, setFailure] = useState<AiSendErrorKind | null>(null)

  const files = useWatch({ control: form.control, name: 'files' })
  const mentions = useWatch({ control: form.control, name: 'mentions' })
  const content = useWatch({ control: form.control, name: 'content' })

  const isBusy = sender.isSending || turnActive

  const submit = form.handleSubmit(async (values) => {
    // Step 0: one turn at a time; the draft stays for when the assistant is done
    if (isBusy) {
      return
    }
    // Step 1: clear the draft so the sent message leaves the composer
    setFailure(null)
    form.reset(EMPTY_COMPOSER_VALUES)

    // Step 2: save the message, queue the turn and follow it
    try {
      const { turn } = await sender.send({
        input: {
          content: values.content,
          mentions: values.mentions,
          files: values.files,
          pageGuideKey: guide.guideKey,
          pageGuideText: guide.guideText,
          locale: guide.locale,
        },
        conversationId,
        onConversationCreated,
      })
      onTurnStarted(turn)
    } catch (error) {
      // Step 3: the POST was refused, nothing was saved: give everything back
      const result = classifyAiSendError(error)
      form.reset(values)
      for (const [field, message] of Object.entries(result.fieldErrors)) {
        form.setError(field as keyof AiComposerValues, { message })
      }
      setFailure(result.kind)
    }
  })

  const addFiles = useCallback(
    (picked: File[]) => {
      form.setValue('files', [...form.getValues('files'), ...picked], { shouldValidate: true })
    },
    [form],
  )

  const removeFile = useCallback(
    (index: number) => {
      const next = form.getValues('files').filter((_, position) => position !== index)
      form.setValue('files', next, { shouldValidate: next.length > 0 || form.formState.isSubmitted })
      if (next.length === 0) {
        form.clearErrors('files')
      }
    },
    [form],
  )

  const addMention = useCallback(
    (mention: AiMentionInput) => {
      const current = form.getValues('mentions')
      const duplicate = current.some((item) => item.module === mention.module && item.id === mention.id)
      if (!duplicate) {
        form.setValue('mentions', [...current, mention], { shouldValidate: true })
      }
    },
    [form],
  )

  const removeMention = useCallback(
    (index: number) => {
      form.setValue(
        'mentions',
        form.getValues('mentions').filter((_, position) => position !== index),
      )
      form.clearErrors('mentions')
    },
    [form],
  )

  const setDraft = useCallback(
    (text: string) => {
      form.setValue('content', text, { shouldDirty: true })
      textareaRef.current?.focus()
    },
    [form],
  )

  return {
    form,
    textareaRef,
    limits,
    content,
    files,
    mentions,
    failure,
    submit,
    addFiles,
    removeFile,
    addMention,
    removeMention,
    setDraft,
    /** The POST is in flight or a turn is still being worked on: the composer cannot send. */
    isBusy,
  }
}

export type AiComposerController = ReturnType<typeof useAiComposer>
