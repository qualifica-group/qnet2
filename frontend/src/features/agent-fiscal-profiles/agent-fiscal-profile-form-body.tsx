import { IdCard, Landmark, Percent } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Button } from '@/components/ui/button'
import { Form, FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { useAgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/use-agent-fiscal-profile-form'
import {
  EnasarcoFields,
  WithholdingFields,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-tax-fields'
import type { AgentFiscalProfileDetail, AgentFiscalProfileFormMode } from '@/features/agent-fiscal-profiles/types'

interface AgentFiscalProfileFormBodyProps {
  mode: AgentFiscalProfileFormMode
  onSuccess: (profile: AgentFiscalProfileDetail) => void
  onCancel: () => void
}

/**
 * The agent fiscal profile create/edit form UI. Every field is wrapped in
 * `MetaField` (hidden means absent, non-editable means disabled); the
 * withholding rates and the mandate type only render while their switch is
 * on. All non-render logic lives in `useAgentFiscalProfileForm`.
 */
export function AgentFiscalProfileFormBody({ mode, onSuccess, onCancel }: AgentFiscalProfileFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useAgentFiscalProfileForm({ mode, onSuccess })
  const profile = mode.type === 'edit' ? mode.profile : null
  const control = form.control

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={IdCard}
            title={t('agentFiscalProfiles.form.sections.validity.title')}
            description={t('agentFiscalProfiles.form.sections.validity.description')}
          >
            <RelationSelectField
              control={control}
              name="referent_id"
              metaKey="referent_id"
              label={t('agentFiscalProfiles.form.referent')}
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('agentFiscalProfiles.form.referentSearch')}
              selected={profile?.referent ?? null}
              placeholder={t('agentFiscalProfiles.form.referentPlaceholder')}
              emptyLabel={t('agentFiscalProfiles.form.referentEmpty')}
              errorLabel={t('agentFiscalProfiles.form.referentError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
              required
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <MetaField control={control} name="valid_from" metaKey="valid_from" label={t('agentFiscalProfiles.form.validFrom')} required>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>
              <MetaField control={control} name="valid_until" metaKey="valid_until" label={t('agentFiscalProfiles.form.validUntil')}>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>
            </div>
          </FormSection>

          <FormSection
            icon={Percent}
            title={t('agentFiscalProfiles.form.sections.withholding.title')}
            description={t('agentFiscalProfiles.form.sections.withholding.description')}
          >
            <WithholdingFields control={control} />
          </FormSection>

          <FormSection
            icon={Landmark}
            title={t('agentFiscalProfiles.form.sections.enasarco.title')}
            description={t('agentFiscalProfiles.form.sections.enasarco.description')}
          >
            <EnasarcoFields control={control} />
          </FormSection>

          <MetaField control={control} name="notes" metaKey="notes" label={t('agentFiscalProfiles.form.notes')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Textarea disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('agentFiscalProfiles.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('agentFiscalProfiles.form.saving') : t('agentFiscalProfiles.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
