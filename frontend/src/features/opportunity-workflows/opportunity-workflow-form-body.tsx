import { useTranslation } from 'react-i18next'
import { Info, Waypoints } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { OpportunityWorkflowCriteriaEditor } from '@/features/opportunity-workflows/opportunity-workflow-criteria-editor'
import { WorkflowStatusesEditor } from '@/features/opportunity-workflows/workflow-statuses-editor'
import { useOpportunityWorkflowForm } from '@/features/opportunity-workflows/use-opportunity-workflow-form'
import type {
  OpportunityWorkflowDetail,
  OpportunityWorkflowFormMode,
} from '@/features/opportunity-workflows/types'

interface OpportunityWorkflowFormBodyProps {
  mode: OpportunityWorkflowFormMode
  onSuccess: (opportunityWorkflow: OpportunityWorkflowDetail) => void
  onCancel: () => void
}

/**
 * The opportunity workflow create/edit form UI, in the 3 sections the spec
 * requires (AC-024): (a) general (`name`/`is_active`, `MetaField`-driven per
 * the field-permission catalogue), (b) criteria (a real RHF field array,
 * `OpportunityWorkflowCriteriaEditor`), (c) workflow statuses
 * (`WorkflowStatusesEditor`, AC-025 — local state, not RHF). All non-render
 * logic lives in `useOpportunityWorkflowForm`.
 */
export function OpportunityWorkflowFormBody({ mode, onSuccess, onCancel }: OpportunityWorkflowFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    serverError,
    statusesError,
    onSubmit,
    criterionFields,
    criteria,
    statusRows,
    addCustomStatus,
    removeCustomStatus,
    updateStatusRow,
    markValidatedStatus,
    reorderStatusRows,
  } = useOpportunityWorkflowForm({ mode, onSuccess })

  const identityVisible = fieldPermission('name').visible || fieldPermission('is_active').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Info}
              title={t('opportunityWorkflows.form.sections.identity.title')}
              description={t('opportunityWorkflows.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('opportunityWorkflows.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('opportunityWorkflows.form.isActive')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          <OpportunityWorkflowCriteriaEditor
            control={form.control}
            setValue={form.setValue}
            criteria={criteria}
            criterionFields={criterionFields}
          />

          <FormSection
            icon={Waypoints}
            title={t('opportunityWorkflows.form.sections.statuses.title')}
            description={t('opportunityWorkflows.form.sections.statuses.description')}
          >
            <WorkflowStatusesEditor
              rows={statusRows}
              onReorder={reorderStatusRows}
              onAddCustom={addCustomStatus}
              onRemoveCustom={removeCustomStatus}
              onUpdateRow={updateStatusRow}
              onMarkValidated={markValidatedStatus}
              error={statusesError}
            />
          </FormSection>

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
              {t('opportunityWorkflows.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('opportunityWorkflows.form.saving')
                : t('opportunityWorkflows.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
