import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FolderKanban, Loader2, Megaphone, Tags } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FieldHint } from '@/components/field-hint'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { cn } from '@/lib/utils'
import { MetaField } from '@/features/authorization/MetaField'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { useCampaignForm } from '@/features/campaigns/use-campaign-form'
import { CampaignProjectField } from '@/features/campaigns/campaign-project-field'
import { CampaignRelationField } from '@/features/campaigns/campaign-relation-field'
import { CampaignGeoSection } from '@/features/campaigns/campaign-geo-section'
import { CampaignPlanningSection } from '@/features/campaigns/campaign-planning-section'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'

interface CampaignFormBodyProps {
  mode: CampaignFormMode
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
  /** Create-only: the sequential code suggestion prefilled into the `code` field (spec 0025). */
  initialCode?: string
}

/** Placeholder shown for the manual `code` field in create, declaring the server-generation fallback (spec 0025 AC-010). */
const CODE_PLACEHOLDER_KEY = 'campaigns.form.codePlaceholder'

/** Motion-safe staggered entrance shared by every top-level section. */
const SECTION_REVEAL_CLASS =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300'

/** Staggers a section's entrance by 50ms per index via an arbitrary Tailwind property, so no `style` prop is needed on `FormSection`. */
function sectionRevealClassName(index: number): string {
  return cn(SECTION_REVEAL_CLASS, `[animation-delay:${index * 50}ms]`)
}

/** Banner for the `product_lines` 422 (collection-level or per-row, spec 0094): mirrors `request-create-form.tsx`'s own local constant. */
const ERROR_BANNER_CLASS =
  'flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive'

/**
 * The campaign create/edit form UI: the manual/read-only `code` (spec 0025,
 * editable only in create — gated by the `code` field permission, editable
 * in create and read-only in update), identity (name, description), the
 * optional Project link driving the AC-042/AC-043 derivation, the always-own
 * relations, the `pipeline_status_id` + `product_lines` BR-2 classification
 * fields (forced read-only while linked, spec 0094: `product_lines` reuses
 * `ProductLinesField`, AC-045/AC-046), the geo cascade (BR-4/BR-5, spec 0027
 * — some levels forced read-only while the linked project fills them) and
 * planning/budget — all wrapped in `MetaField` (spec 0004). All non-render
 * logic lives in `useCampaignForm`.
 */
export function CampaignFormBody({ mode, onSuccess, onCancel, initialCode }: CampaignFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, productLinesError, onSubmit } = useCampaignForm({ mode, onSuccess, initialCode })
  const original = mode.type === 'edit' ? mode.campaign : mode.type === 'duplicate' ? mode.source : null

  const projectId = useWatch({ control: form.control, name: 'project_id' })
  const isLinked = projectId !== null

  const { errors, isSubmitting } = form.formState
  const [planningOpen, setPlanningOpen] = useState(false)
  const planningHasError = Boolean(errors.start_date || errors.end_date || errors.total_budget || errors.target_lead)
  const [customOpen, setCustomOpen] = useState(false)
  const customHasError = Boolean(errors.custom_fields)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={Megaphone}
            title={t('campaigns.form.sections.identity.title')}
            description={t('campaigns.form.sections.identity.description')}
            className={sectionRevealClassName(0)}
          >
            <MetaField
              control={form.control}
              name="code"
              metaKey="code"
              label={t('campaigns.form.code')}
              hint={t('campaigns.form.hints.code')}
              hintLabel={t('campaigns.form.hints.moreInfoLabel', { field: t('campaigns.form.code') })}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input
                    autoComplete="off"
                    disabled={disabled}
                    readOnly={readOnly}
                    placeholder={t(CODE_PLACEHOLDER_KEY)}
                    {...field}
                    value={field.value ?? ''}
                  />
                </FormControl>
              )}
            </MetaField>

            <MetaField control={form.control} name="name" metaKey="name" label={t('campaigns.form.name')}>
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
              label={t('campaigns.form.description')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Textarea
                    disabled={disabled}
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
          </FormSection>

          <FormSection
            icon={FolderKanban}
            title={t('campaigns.form.sections.project.title')}
            description={t('campaigns.form.sections.project.description')}
            aside={
              <FieldHint
                text={t('campaigns.form.hints.project')}
                label={t('campaigns.form.hints.moreInfoLabel', {
                  field: t('campaigns.form.sections.project.title'),
                })}
              />
            }
            className={sectionRevealClassName(1)}
          >
            <CampaignProjectField
              control={form.control}
              setValue={form.setValue}
              selected={original?.project ?? null}
            />

            <div className="grid gap-3 sm:grid-cols-2">
              <CampaignRelationField
                control={form.control}
                name="partner_id"
                metaKey="partner_id"
                label={t('campaigns.form.partner')}
                hint={t('campaigns.form.hints.partner')}
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('campaigns.form.partnerSearch')}
                selected={original?.partner ?? null}
              />

              <CampaignRelationField
                control={form.control}
                name="operational_site_id"
                metaKey="operational_site_id"
                label={t('campaigns.form.operationalSite')}
                hint={t('campaigns.form.hints.operationalSite')}
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('campaigns.form.operationalSiteSearch')}
                selected={
                  original?.operational_site
                    ? { id: original.operational_site.id, name: original.operational_site.label }
                    : null
                }
              />
            </div>
          </FormSection>

          <FormSection
            icon={Tags}
            title={t('campaigns.form.sections.classification.title')}
            description={t(
              isLinked
                ? 'campaigns.form.sections.classification.descriptionLinked'
                : 'campaigns.form.sections.classification.description',
            )}
            aside={
              isLinked ? (
                <FieldHint
                  text={t('campaigns.form.hints.classification')}
                  label={t('campaigns.form.hints.moreInfoLabel', {
                    field: t('campaigns.form.sections.classification.title'),
                  })}
                />
              ) : undefined
            }
            className={sectionRevealClassName(2)}
          >
            <div className="grid gap-3 sm:grid-cols-2">
              <CampaignRelationField
                control={form.control}
                name="pipeline_status_id"
                metaKey="pipeline_status_id"
                label={t('campaigns.form.status')}
                resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('campaigns.form.statusSearch')}
                selected={original?.pipeline_status ?? null}
                forceDisabled={isLinked}
              />
            </div>

            {/* Spec 0094: replaces the former single business function +
                product category selects with the shared row editor
                (`ProductLinesField`, spec 0057). BR-2 read-only lock while
                linked (AC-046): shows the linked project's EFFECTIVE rows,
                never editable, never sent (payload builder omits it). */}
            <MetaField
              control={form.control}
              name="product_lines"
              metaKey="product_lines"
              label={t('campaigns.form.productLines')}
              required={!isLinked}
            >
              {({ field, disabled }) => (
                <ProductLinesField
                  value={field.value}
                  onChange={field.onChange}
                  knownLines={original?.product_lines}
                  disabled={disabled || isLinked}
                />
              )}
            </MetaField>

            {productLinesError && (
              <div role="alert" className={ERROR_BANNER_CLASS}>
                {productLinesError}
              </div>
            )}
          </FormSection>

          <CampaignGeoSection
            control={form.control}
            setValue={form.setValue}
            className={sectionRevealClassName(3)}
          />

          <CampaignPlanningSection
            control={form.control}
            projectId={projectId}
            collapsible
            open={planningOpen || planningHasError}
            onOpenChange={setPlanningOpen}
            className={sectionRevealClassName(4)}
          />

          <div className={sectionRevealClassName(5)}>
            <CustomFieldsSection
              resource="campaigns"
              control={form.control}
              collapsible
              open={customOpen || customHasError}
              onOpenChange={setCustomOpen}
            />
          </div>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
              {t('campaigns.form.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="mr-2 size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('campaigns.form.saving') : t('campaigns.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
