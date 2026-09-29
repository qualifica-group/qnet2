import { useEffect, useRef } from 'react'
import { useForm, useFormState } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import {
  useDeleteWorkOrderEmailDraft,
  useSaveWorkOrderEmailDraft,
  useSendWorkOrderEmail,
  useWorkOrderEmail,
} from '@/features/work-order-emails/use-work-order-email-draft'
import { useWorkOrderEmailComposeContext } from '@/features/work-order-emails/use-work-order-email-compose-context'
import { applyComposerValidationErrors } from '@/features/work-order-emails/work-order-email-errors'
import {
  buildComposerSchema,
  buildSendSchema,
  EMPTY_COMPOSER_VALUES,
  isEmptyDraft,
  mapEmailToFormValues,
  mapFormValuesToPayload,
  type ComposerFormValues,
} from '@/features/work-order-emails/work-order-email-schema'

export interface UseWorkOrderEmailComposerOptions {
  workOrderId: number
  emailId: number
  /** True only right after "Nuova email" created this draft: gates the silent-delete-on-empty-close (D-2). */
  justCreated: boolean
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
export function useWorkOrderEmailComposer({
  workOrderId,
  emailId,
  justCreated,
  open,
  onOpenChange,
}: UseWorkOrderEmailComposerOptions) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const initializedRef = useRef(false)

  const emailQuery = useWorkOrderEmail(workOrderId, emailId)
  const composeContextQuery = useWorkOrderEmailComposeContext(workOrderId, open)
  const save = useSaveWorkOrderEmailDraft(workOrderId, emailId)
  const send = useSendWorkOrderEmail(workOrderId, emailId)
  const remove = useDeleteWorkOrderEmailDraft(workOrderId, emailId)

  const form = useForm<ComposerFormValues>({
    resolver: zodResolver(buildComposerSchema()),
    defaultValues: EMPTY_COMPOSER_VALUES,
  })
  // `formState.isDirty` is Proxy-tracked (RHF perf optimization): it is only
  // kept live once something actually READS it during render. Nothing in
  // `WorkOrderEmailComposerForm` does, so `handleOpenChange` below subscribes
  // explicitly instead of reading `form.formState.isDirty` from a callback,
  // which would otherwise see a stale `false`.
  const { isDirty } = useFormState({ control: form.control })

  // Loads the draft into the form exactly once: a later cache update from an
  // attachment mutation (same query key) must NOT fight whatever the user is
  // mid-typing into subject/body (same guard rationale as `RichTextEditor`'s
  // own `lastEmittedRef`).
  useEffect(() => {
    if (!initializedRef.current && emailQuery.data) {
      form.reset(mapEmailToFormValues(emailQuery.data))
      initializedRef.current = true
    }
  }, [emailQuery.data, form])

  const handleSaveDraft = form.handleSubmit(async (values) => {
    try {
      const updated = await save.mutateAsync(mapFormValuesToPayload(values))
      form.reset(mapEmailToFormValues(updated))
      toast.success(t('workOrderEmails.composer.draftSaved'))
    } catch (error) {
      if (!applyComposerValidationErrors(error, form.setError)) {
        toast.error(t('workOrderEmails.composer.genericError'))
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
      toast.success(t('workOrderEmails.composer.sent'))
      onOpenChange(false)
    } catch (error) {
      if (!applyComposerValidationErrors(error, form.setError)) {
        toast.error(t('workOrderEmails.composer.genericError'))
      }
    }
  }

  const handleDeleteDraft = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('workOrderEmails.composer.deleteDraft'),
      description: t('workOrderEmails.composer.deleteDraftConfirm'),
      confirmLabel: t('workOrderEmails.composer.deleteDraft'),
    })
    if (!confirmed) {
      return
    }
    try {
      await remove.mutateAsync()
      toast.success(t('workOrderEmails.composer.deleted'))
      onOpenChange(false)
    } catch {
      toast.error(t('workOrderEmails.composer.genericError'))
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
        title: t('workOrderEmails.composer.unsavedChangesTitle'),
        description: t('workOrderEmails.composer.unsavedChangesDescription'),
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
