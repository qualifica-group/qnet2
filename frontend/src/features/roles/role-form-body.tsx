import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Loader2, ShieldCheck, TriangleAlert } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PermissionsEditor } from '@/features/roles/permission-explorer/permissions-editor'
import { RoleIdentityRecordSection, RolePermissionsRecordSection } from '@/features/roles/role-record-sections'
import { useRoleForm } from '@/features/roles/use-role-form'
import type { RoleDetail } from '@/features/roles/types'

type RoleCustomFields = NonNullable<RoleDetail['custom_fields']>

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const ROLE_FORM_ID = 'role-form'

interface RoleFormBodyProps {
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * The role create form UI as a replica of the detail (spec 0230 D-4): the
 * record card with its identity band (monogram and title follow the name
 * being typed, Annulla/Salva), the "Dettagli ruolo" rows closed until clicked
 * (Fatto/Ripristina, nothing saved per row), the permissions explorer OPEN
 * (an editor rather than a field) and the custom fields (spec 0021). Every
 * field stays wrapped in `MetaField` (spec 0004): hidden fields are absent,
 * non-editable ones disabled. All non-render logic lives in `useRoleForm`.
 */
export function RoleFormBody({ onSuccess, onCancel }: RoleFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit, canManageFieldPermissions } = useRoleForm({ onSuccess })
  const draft = useDraftInlineEdit(form)
  const values = useWatch({ control: form.control })
  const { isSubmitting } = form.formState
  const permissionsVisible = fieldPermission('permissions').visible
  const heading = values.name?.trim() || t('roles.form.createTitle')

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={ROLE_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <RecordCardHeader
                media={<DetailMonogram name={heading} icon={<ShieldCheck />} />}
                title={heading}
                subtitle={t('roles.form.createSubtitle')}
                actions={
                  <>
                    <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                      {t('roles.form.cancel')}
                    </Button>
                    <Button type="submit" form={ROLE_FORM_ID} disabled={isSubmitting}>
                      {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      {isSubmitting ? t('roles.form.saving') : t('roles.form.save')}
                    </Button>
                  </>
                }
              />
              {serverError ? (
                <div
                  role="alert"
                  className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
                >
                  <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                  {serverError}
                </div>
              ) : null}
              <RecordSectionsGrid>
                <RoleIdentityRecordSection
                  values={{ name: values.name ?? '', users: values.users ?? [] }}
                  form={form}
                  inline={draft}
                  canEdit
                />
                {permissionsVisible ? (
                  <RolePermissionsRecordSection count={values.permissions?.length ?? 0}>
                    <PermissionsEditor control={form.control} canManageFieldPermissions={canManageFieldPermissions} />
                  </RolePermissionsRecordSection>
                ) : null}
                <CustomFieldRecordSections
                  resource="roles"
                  values={(values.custom_fields ?? {}) as RoleCustomFields}
                  inline={draft}
                  control={form.control}
                />
              </RecordSectionsGrid>
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes the permissions far from the top. */}
            <RecordFormActions
              formId={ROLE_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('roles.form.save')}
              submittingLabel={t('roles.form.saving')}
              cancel={{ label: t('roles.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
