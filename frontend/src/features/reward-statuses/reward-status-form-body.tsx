import { Flag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { useRewardStatusForm } from '@/features/reward-statuses/use-reward-status-form'
import type {
  RewardStatusDetail,
  RewardStatusFormMode,
} from '@/features/reward-statuses/types'

interface RewardStatusFormBodyProps {
  mode: RewardStatusFormMode
  onSuccess: (rewardStatus: RewardStatusDetail) => void
  onCancel: () => void
}

/**
 * The reward status create/edit form UI. `name`, `description`, `color` and
 * `is_active` are each wrapped in `MetaField` (spec 0004): hidden means
 * absent, non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. The
 * system row ("In attesa", `pending`, D-2) forces `description`/`is_active`
 * disabled regardless of field permissions: only `name`/`color` are editable
 * for it. `sort_order` has no form field (D-3, server-managed). All
 * non-render logic lives in `useRewardStatusForm`.
 */
export function RewardStatusFormBody({ mode, onSuccess, onCancel }: RewardStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useRewardStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.rewardStatus.system_key !== null

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('color').visible ||
    fieldPermission('is_active').visible

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
              title={t('rewardStatuses.form.sections.identity.title')}
              description={t('rewardStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('rewardStatuses.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('rewardStatuses.form.description')}
                hint={isSystemRow ? t('rewardStatuses.form.hints.systemStatusLocked') : undefined}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled || isSystemRow}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('rewardStatuses.form.color')}
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
                name="is_active"
                metaKey="is_active"
                label={t('rewardStatuses.form.isActive')}
                hint={isSystemRow ? t('rewardStatuses.form.hints.systemStatusLocked') : undefined}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled || isSystemRow}
                    />
                  </FormControl>
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
              {t('rewardStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('rewardStatuses.form.saving')
                : t('rewardStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
