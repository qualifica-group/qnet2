import { useEffect, useRef } from 'react'
import { useForm, useFormState } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import type { EmailOwnerRef } from '@/features/outbound-emails/types'
import {
  useDeleteOutboundEmailDraft,
  useSaveOutboundEmailDraft,
  useSendOutboundEmail,
  useOutboundEmail,
} from '@/features/outbound-emails/use-outbound-email-draft'
import { useOutboundEmailComposeContext } from '@/features/outbound-emails/use-outbound-email-compose-context'
import { applyComposerValidationErrors } from '@/features/outbound-emails/outbound-email-errors'
import {
  buildComposerSchema,
  buildSendSchema,
  EMPTY_COMPOSER_VALUES,
  isEmptyDraft,
  mapEmailToFormValues,
  mapFormValuesToPayload,
  type ComposerFormValues,
} from '@/features/outbound-emails/outbound-email-schema'

export interface UseOutboundEmailComposerOptions {
  owner: EmailOwnerRef
  emailId: number
  /** True only right after "Nuova email" created this draft: gates the silent-delete-on-empty-close (D-2). */
  justCreated: boolean
  /** Prefill To from `compose-context.default_to` on an empty draft; defaults to `justCreated` (the reminder flow sets it, D-13). */
  prefillDefaultTo?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Orchestrates the composer (AC-020): loads the draft + compose-context,
 * owns the RHF form and every mutation (save/send/delete), and gates every
 * close attempt — silent cleanup for an untouched fresh draft (D-2), a
 * confirm for anything else with unsaved changes. The dialog component stays
 * a pure render of what this hook returns.
 */
export function useOutboundEmailComposer({
  owner,
  emailId,
  justCreated,
  prefillDefaultTo = justCreated,
  open,
  onOpenChange,
}: UseOutboundEmailComposerOptions) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const initializedRef = useRef(false)

  const emailQuery = useOutboundEmail(owner, emailId)
  const composeContextQuery = useOutboundEmailComposeContext(owner, open)
  const save = useSaveOutboundEmailDraft(owner, emailId)
  const send = useSendOutboundEmail(owner, emailId)
  const remove = useDeleteOutboundEmailDraft(owner, emailId)

  const form = useForm<ComposerFormValues>({
    resolver: zodResolver(buildComposerSchema()),
    defaultValues: EMPTY_COMPOSER_VALUES,
  })
  // `formState.isDirty` is Proxy-tracked (RHF perf optimization): it is only
  // kept live once something actually READS it during render. Nothing in
  // `OutboundEmailComposerForm` does, so `handleOpenChange` below subscribes
  // explicitly instead of reading `form.formState.isDirty` from a callback,
  // which would otherwise see a stale `false`.
  const { isDirty } = useFormState({ control: form.control })

  // Loads the draft into the form exactly once: a later cache update from an
  // attachment mutation (same query key) must NOT fight whatever the user is
  // mid-typing into subject/body (same guard rationale as `RichTextEditor`'s
  // own `lastEmittedRef`).
  // A brand-new draft waits for the compose context so `default_to` (invoices:
  // customer PEC/email, D-12) can prefill the To field; any other draft keeps
  // its own recipients untouched.
  useEffect(() => {
    if (initializedRef.current || !emailQuery.data) {
      return
    }
    if (prefillDefaultTo && !composeContextQuery.data && !composeContextQuery.isError) {
      return
    }
    const values = mapEmailToFormValues(emailQuery.data)
    const defaultTo = composeContextQuery.data?.default_to ?? []
    // Not dirty on purpose: an untouched prefilled draft is still silently discarded on close.
    const shouldPrefill = prefillDefaultTo && values.to.length === 0 && defaultTo.length > 0
    form.reset(shouldPrefill ? { ...values, to: defaultTo } : values)
    initializedRef.current = true
  }, [emailQuery.data, composeContextQuery.data, composeContextQuery.isError, prefillDefaultTo, form])

  const handleSaveDraft = form.handleSubmit(async (values) => {
    try {
      const updated = await save.mutateAsync(mapFormValuesToPayload(values))
      form.reset(mapEmailToFormValues(updated))
      toast.success(t('outboundEmails.composer.draftSaved'))
    } catch (error) {
      if (!applyComposerValidationErrors(error, form.setError)) {
        toast.error(t('outboundEmails.composer.genericError'))
      }
    }
  })

  const handleSend = async () => {
    const values = form.getValues()
    const result = buildSendSchema(t).safeParse(values)
    if (!result.success) {
      for (const issue of result.error.issues) {
        const field = issue.path[0] as keyof ComposerFormValues
        form.setError(field, { message: issue.message })
      }
      return
    }

    try {
      // Step 1: persist whatever is in the form (the send endpoint sends the
      // saved state, D-2's data_contract note "il FE salva prima con PATCH")
      await save.mutateAsync(mapFormValuesToPayload(values))
      // Step 2: flip draft -> queued
      const sent = await send.mutateAsync()
      form.reset(mapEmailToFormValues(sent))
      toast.success(t('outboundEmails.composer.sent'))
      onOpenChange(false)
    } catch (error) {
      if (!applyComposerValidationErrors(error, form.setError)) {
        toast.error(t('outboundEmails.composer.genericError'))
      }
    }
  }

  const handleDeleteDraft = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('outboundEmails.composer.deleteDraft'),
      description: t('outboundEmails.composer.deleteDraftConfirm'),
      confirmLabel: t('outboundEmails.composer.deleteDraft'),
    })
    if (!confirmed) {
      return
    }
    try {
      await remove.mutateAsync()
      toast.success(t('outboundEmails.composer.deleted'))
      onOpenChange(false)
    } catch {
      toast.error(t('outboundEmails.composer.genericError'))
    }
  }

  const handleOpenChange = async (next: boolean) => {
    if (next) {
      onOpenChange(true)
      return
    }
    // "Never modified" checks BOTH the persisted draft and the in-progress
    // form: a keystroke not yet saved is still a modification the user made,
    // even though the server-side draft is still empty.
    if (justCreated && !isDirty && isEmptyDraft(emailQuery.data)) {
      await remove.mutateAsync().catch(() => undefined)
      onOpenChange(false)
      return
    }
    if (isDirty) {
      const confirmed = await confirm({
        tone: 'warning',
        title: t('outboundEmails.composer.unsavedChangesTitle'),
        description: t('outboundEmails.composer.unsavedChangesDescription'),
      })
      if (!confirmed) {
        return
      }
    }
    onOpenChange(false)
  }

  return {
    form,
    email: emailQuery.data,
    isLoading: emailQuery.isLoading,
    isError: emailQuery.isError,
    composeContext: composeContextQuery.data,
    isSaving: save.isPending,
    isSending: send.isPending,
    isDeleting: remove.isPending,
    handleSaveDraft,
    handleSend,
    handleDeleteDraft,
    handleOpenChange,
  }
}
