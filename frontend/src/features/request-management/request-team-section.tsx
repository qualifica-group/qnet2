import { useTranslation } from 'react-i18next'
import { Info, Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { toManagerSlotLabels } from '@/lib/utils'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { FIELD_STACK_CLASS } from '@/components/record-form/layout'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ManagerLabels, RequestManagerRef, RequestRelationRef } from '@/features/request-management/types'

/** Hoisted: an inline `{}` fallback would rebuild the slots' label map on every render. */
const EMPTY_MANAGER_LABELS: ManagerLabels = {}

interface RequestTeamSectionProps {
  control: Control<RequestWorkFormValues>
  /** Spec 0097: the Offerta's team, for the slot triggers' hydrated labels. */
  managers: RequestManagerRef[]
  /** Spec 0097 rev-2 D-9: the Supervisore's hydrated `{id, name}` projection, for its picker's label. */
  supervisor: RequestRelationRef | null
  /**
   * Spec 0080: the panel's own resolved G.A. labels (string position keys), as
   * returned by `RequestWorkPanel.manager_labels`. Names each team slot when
   * the request's category defines one; `undefined` (or a position missing
   * from the map) keeps the editor's own default "G.A. n" denomination.
   */
  managerLabels?: ManagerLabels
  /**
   * The Sede half of the link the FORM owns
   * (`useRequestSiteOperatorLink`, spec 0097 rev-2 D-7): the Sede itself is
   * edited in "Attribuzione", one section away, so this one only consumes the
   * scoping it produces.
   */
  siteId: number | null
  slotParamsFor: (position: number) => Record<string, string | number> | undefined
  onSlotItemChange: (position: number, item: ForSelectItem | null) => void
}

/**
 * The team working the request: the Offerta's Supervisore plus its ordered
 * "G.A. n" slots, in the very editor the Offerte form uses
 * (`ManagerSlotsField`, gated on the `manager_slots` permission key).
 *
 * A section of its own since spec 0097 rev-2 D-7 (it was a block of
 * "Attribuzione"), built on `QuoteTeamSection`'s model so the two forms read
 * the same. Every control is metadata-driven, so its per-field gating comes
 * from the server-derived `permissions` block like every other field here.
 *
 * Changing the operator slot REASSIGNS the request: an actor without
 * `request-management.viewAll` loses access to it on the next read (D-3
 * scope), which is the intended semantics of handing a request over. The
 * Supervisore does none of that (AC-014): it is the Offerta's
 * commission-recipient role, coupled to nothing here.
 *
 * The scoping hint sits with the SLOTS, not with the Sede that produces it
 * (user directive 2026-08-04 moved across sections by rev-2): it describes
 * which users the operator slot lists, so it belongs next to the control it
 * describes. Its condition is unchanged — both fields must be really on
 * screen, `MetaField` hiding one from the inside would otherwise leave a
 * sentence about a control the actor cannot see.
 */
export function RequestTeamSection({
  control,
  managers,
  supervisor,
  managerLabels,
  siteId,
  slotParamsFor,
  onSlotItemChange,
}: RequestTeamSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const slotLabels = toManagerSlotLabels(managerLabels ?? EMPTY_MANAGER_LABELS)
  const selectedManagers = managers.map((manager) => ({ id: manager.id, label: manager.name }))

  const showOperatorScopeHint =
    siteId != null && fieldPermission('manager_slots').visible && fieldPermission('operational_site_id').visible

  const selectLabels = {
    placeholder: t('requestManagement.workPanel.team.selectPlaceholder'),
    emptyLabel: t('requestManagement.workPanel.team.selectEmpty'),
    errorLabel: t('requestManagement.workPanel.team.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Users}
      title={t('requestManagement.workPanel.team.title')}
      description={t('requestManagement.workPanel.team.description')}
    >
      <RelationSelectField
        control={control}
        name="supervisor_id"
        metaKey="supervisor_id"
        label={t('requestManagement.workPanel.team.supervisor')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('requestManagement.workPanel.team.supervisorSearch')}
        selected={supervisor}
        showAvatar
        {...selectLabels}
      />

      <div className={FIELD_STACK_CLASS}>
        <MetaField
          control={control}
          name="manager_slots"
          metaKey="manager_slots"
          label={t('requestManagement.workPanel.team.managers')}
        >
          {({ field, disabled }) => (
            <ManagerSlotsField
              value={field.value}
              onChange={field.onChange}
              selectedItems={selectedManagers}
              disabled={disabled}
              labels={slotLabels}
              paramsFor={slotParamsFor}
              onItemChange={onSlotItemChange}
            />
          )}
        </MetaField>
        {showOperatorScopeHint && (
          <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Info className="size-3.5 shrink-0" aria-hidden="true" />
            {t('requestManagement.workPanel.team.operatorFilteredBySite')}
          </p>
        )}
      </div>
    </FormSection>
  )
}
