import { Gift } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { useRewardTypeForm } from '@/features/reward-types/use-reward-type-form'
import type { RewardTypeDetail, RewardTypeFormMode } from '@/features/reward-types/types'

interface RewardTypeFormBodyProps {
  mode: RewardTypeFormMode
  onSuccess: (rewardType: RewardTypeDetail) => void
  onCancel: () => void
}

/**
 * The reward type create/edit form UI. `name` and `color` are each wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in
 * `useRewardTypeForm`.
 */
export function RewardTypeFormBody({ mode, onSuccess, onCancel }: RewardTypeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useRewardTypeForm({ mode, onSuccess })

  const identityVisible = fieldPermission('name').visible || fieldPermission('color').visible

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
              icon={Gift}
              title={t('rewardTypes.form.sections.identity.title')}
              description={t('rewardTypes.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('rewardTypes.form.name')}
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
                label={t('rewardTypes.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker value={field.value} onChange={field.onChange} disabled={disabled} />
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
              {t('rewardTypes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('rewardTypes.form.saving') : t('rewardTypes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
