import { Flag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { QUOTE_STATUS_GROUPS, type QuoteStatusGroupValue } from '@/features/status-reorder/types'
import { useQuoteStatusForm } from '@/features/quote-statuses/use-quote-status-form'
import type {
  QuoteStatusDetail,
  QuoteStatusFormMode,
} from '@/features/quote-statuses/types'

interface QuoteStatusFormBodyProps {
  mode: QuoteStatusFormMode
  onSuccess: (quoteStatus: QuoteStatusDetail) => void
  onCancel: () => void
}

/** i18n key per fixed group value, kept out of the JSX so the option list stays a plain map. */
const GROUP_LABEL_KEYS: Record<QuoteStatusGroupValue, string> = {
  open: 'quoteStatuses.form.group.open',
  pending: 'quoteStatuses.form.group.pending',
  closed_won: 'quoteStatuses.form.group.closed_won',
  closed_lost: 'quoteStatuses.form.group.closed_lost',
}

/**
 * The quote status create/edit form UI. `name`, `color` and `group` are each
 * wrapped in `MetaField` (spec 0004): hidden means absent, non-editable means
 * disabled, `required` comes from the resolved `ResourcePermissions` — no
 * hardcoded permission logic lives here. A system row ("Bozza"/"Accettata"/
 * "Rifiutata", D-2) forces the group picker disabled regardless of field
 * permissions: its group is fixed in migration and only `name`/`color` are
 * editable. `sort_order` has no form field (server-managed). All non-render
 * logic lives in `useQuoteStatusForm`.
 */
export function QuoteStatusFormBody({ mode, onSuccess, onCancel }: QuoteStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useQuoteStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.quoteStatus.system_key !== null

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('color').visible ||
    fieldPermission('group').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={Flag}
              title={t('quoteStatuses.form.sections.identity.title')}
              description={t('quoteStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('quoteStatuses.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('quoteStatuses.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker
                      value={field.value}
                      onChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="group"
                metaKey="group"
                label={t('quoteStatuses.form.group.label')}
                hint={isSystemRow ? t('quoteStatuses.form.hints.systemStatusGroup') : undefined}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value}
                    onValueChange={(next) => field.onChange(next as QuoteStatusGroupValue)}
                    disabled={disabled || isSystemRow}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {QUOTE_STATUS_GROUPS.map((group) => (
                        <SelectItem key={group} value={group}>
                          {t(GROUP_LABEL_KEYS[group])}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('quoteStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('quoteStatuses.form.saving')
                : t('quoteStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
