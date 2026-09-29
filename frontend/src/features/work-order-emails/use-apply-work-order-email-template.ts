import { useMutation } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { renderWorkOrderEmailTemplate } from '@/features/work-order-emails/api'
import type { ComposerFormValues } from '@/features/work-order-emails/work-order-email-schema'
import type { RenderTemplateResult } from '@/features/work-order-emails/types'

/**
 * Wires the composer's template picker (D-4, AC-020): render-template
 * resolves the placeholders server-side, then overwrites subject/body — but
 * only after confirming when either field already holds text, so a
 * half-written email is never silently discarded.
 */
export function useApplyWorkOrderEmailTemplate(workOrderId: number, form: UseFormReturn<ComposerFormValues>) {
  const { t } = useTranslation()
  const confirm = useConfirm()

  const render = useMutation<RenderTemplateResult, AxiosError, number>({
    mutationFn: (emailTemplateId) => renderWorkOrderEmailTemplate(workOrderId, emailTemplateId),
  })

  const applyTemplate = async (emailTemplateId: number) => {
    const { subject, body } = form.getValues()
    const hasContent = subject.trim() !== '' || (body ?? '').trim() !== ''

    if (hasContent) {
      const confirmed = await confirm({
        tone: 'warning',
        title: t('workOrderEmails.composer.templateOverwriteTitle'),
        description: t('workOrderEmails.composer.templateOverwriteDescription'),
        confirmLabel: t('workOrderEmails.composer.templateOverwriteConfirm'),
      })
      if (!confirmed) {
        return
      }
    }

    try {
      const rendered = await render.mutateAsync(emailTemplateId)
      form.setValue('email_template_id', emailTemplateId, { shouldDirty: true })
      form.setValue('subject', rendered.subject, { shouldDirty: true })
      form.setValue('body', rendered.body, { shouldDirty: true })
    } catch {
      toast.error(t('workOrderEmails.composer.templateError'))
    }
  }

  return { applyTemplate, isRendering: render.isPending }
}
