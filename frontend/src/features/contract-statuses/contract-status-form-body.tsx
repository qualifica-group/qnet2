import { Flag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
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
import {
  CONTRACT_STATUS_GROUPS,
  type ContractStatusGroupValue,
} from '@/features/status-reorder/types'
import { useContractStatusForm } from '@/features/contract-statuses/use-contract-status-form'
import type {
  ContractStatusDetail,
  ContractStatusFormMode,
} from '@/features/contract-statuses/types'

interface ContractStatusFormBodyProps {
  mode: ContractStatusFormMode
  onSuccess: (contractStatus: ContractStatusDetail) => void
  onCancel: () => void
}

/** i18n key per fixed group value, kept out of the JSX so the option list stays a plain map. */
const GROUP_LABEL_KEYS: Record<ContractStatusGroupValue, string> = {
  open: 'contractStatuses.form.group.open',
  pending: 'contractStatuses.form.group.pending',
  closed_won: 'contractStatuses.form.group.closed_won',
  closed_lost: 'contractStatuses.form.group.closed_lost',
}

/**
 * The contract status create/edit form UI. `name`, `description`, `color`,
 * `group`, `is_active` and `is_default` are each wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions` — no hardcoded permission
 * logic lives here. A system row (D-2, BR-5) forces every control but
 * `name`/`color` disabled regardless of field permissions, mirroring
 * `SystemStatusGuard::assertUpdatable` server-side. The currently-default row
 * additionally locks `is_default` itself: BR-5 forbids unsetting it with a
 * direct PATCH, it can only be reassigned by making another row default. The
 * `is_default = true` + `is_active = false` invariant is enforced by the
 * schema (`requireActiveWhenDefault`) before any request leaves the client.
 * `sort_order` and `system_key` have no form field (server-managed / not
 * user-settable). All non-render logic lives in `useContractStatusForm`.
 */
export function ContractStatusFormBody({ mode, onSuccess, onCancel }: ContractStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useContractStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.contractStatus.system_key !== null
  const isCurrentDefault = mode.type === 'edit' && mode.contractStatus.is_default
  const systemFieldsHint = isSystemRow
    ? t('contractStatuses.form.hints.systemStatusFields')
    : undefined

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('color').visible ||
    fieldPermission('group').visible ||
    fieldPermission('is_active').visible ||
    fieldPermission('is_default').visible

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
              title={t('contractStatuses.form.sections.identity.title')}
              description={t('contractStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('contractStatuses.form.name')}
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
                label={t('contractStatuses.form.description')}
                hint={systemFieldsHint}
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
                label={t('contractStatuses.form.color')}
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
                label={t('contractStatuses.form.group.label')}
                hint={systemFieldsHint}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value}
                    onValueChange={(next) => field.onChange(next as ContractStatusGroupValue)}
                    disabled={disabled || isSystemRow}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {CONTRACT_STATUS_GROUPS.map((group) => (
                        <SelectItem key={group} value={group}>
                          {t(GROUP_LABEL_KEYS[group])}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('contractStatuses.form.isActive')}
                hint={systemFieldsHint}
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

              <MetaField
                control={form.control}
                name="is_default"
                metaKey="is_default"
                label={t('contractStatuses.form.isDefault')}
                hint={isCurrentDefault ? t('contractStatuses.form.hints.cannotUnsetDefault') : systemFieldsHint}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled || isSystemRow || isCurrentDefault}
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
              {t('contractStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('contractStatuses.form.saving')
                : t('contractStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
