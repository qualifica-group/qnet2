import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CircleAlert, ClipboardList, Contact, Handshake, Loader2, StickyNote } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { sectionRevealClassName } from '@/components/form-section-reveal'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Form, FormControl, FormDescription } from '@/components/ui/form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { cn } from '@/lib/utils'
import { Can } from '@/features/auth/can'
import { MetaField } from '@/features/authorization/MetaField'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { CAMPAIGNS_FOR_SELECT_RESOURCE, type CampaignForSelectItem } from '@/features/campaigns/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE, type UserForSelectItem } from '@/features/users/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'
import { useLeadForm } from '@/features/leads/use-lead-form'
import { useLeadCampaignProductInterest } from '@/features/leads/use-lead-campaign-product-interest'
import { LeadProductInterestSection } from '@/features/leads/lead-product-interest-section'
import { NOTES_MAX_LENGTH } from '@/features/leads/lead-schema'
import { ExtraFieldsEditor } from '@/features/leads/extra-fields-editor'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'
import type { ForSelectItem } from '@/features/for-select/types'

interface LeadFormBodyProps {
  mode: LeadFormMode
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * The lead create/edit form UI, grouped by prominence: the required
 * Contact/Campaign pair (BR-1, D-1) leads, the optional
 * relations sit in a secondary "Details" section, and notes/extra fields
 * collapse out of the way (auto-reopening on validation errors) — all
 * wrapped in `MetaField` (spec 0004). All non-render logic lives in
 * `useLeadForm`.
 */
export function LeadFormBody({ mode, onSuccess, onCancel }: LeadFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useLeadForm({ mode, onSuccess })
  const original = mode.type === 'edit' ? mode.lead : null

  const { errors, isSubmitting } = form.formState
  const [notesOpen, setNotesOpen] = useState(true)
  const notesHasError = Boolean(errors.notes)
  const [extraOpen, setExtraOpen] = useState(true)
  const extraHasError = Boolean(errors.extra_fields)

  const notesValue = useWatch({ control: form.control, name: 'notes' })
  const notesLength = notesValue?.length ?? 0

  // Project -> campaign -> lead prefill chain: the just-picked Campaign's Sede,
  // hydrated instantly from its `meta` (no extra fetch) so the trigger shows
  // the right label the moment it auto-fills `operational_site_id` — mirrors
  // the (removed, directive 2026-07-21) Sede->Regione `quickCreated`-style
  // pattern. Wired as the Campaign select's `onItemChange` (event handler,
  // not a derived-state effect); a campaign with no Sede leaves the current
  // value untouched, and the user can always override/clear it (prefill, not
  // a lock). Unlike the superseded Sede->Regione auto-fill, this does NOT
  // touch `state_id`: directive 2026-07-21 made the Regione a free,
  // never-inherited field.
  // Sede <-> Operatore, reciprocally filtering/linked (spec 0048 AC-060..062).
  // `previousSiteIdRef` is the baseline every auto-fill/clear below reasons
  // against: it starts at the loaded lead's Sede (edit) or null (create) and
  // only ever moves inside an event handler (never read/written during
  // render), so a real Sede pick can be told apart from the programmatic
  // auto-fills (which never go through the Sede field's own `onItemChange`).
  const previousSiteIdRef = useRef<number | null>(original?.operational_site_id ?? null)
  const siteId = useWatch({ control: form.control, name: 'operational_site_id' })

  const [autoFilledSite, setAutoFilledSite] = useState<RelationFieldRef | null>(null)
  const applyCampaignSitePrefill = (item: ForSelectItem | null) => {
    const campaign = item as CampaignForSelectItem | null
    const site = campaign?.meta?.operational_site
    if (site == null) return
    form.setValue('operational_site_id', site.id, { shouldDirty: true, shouldValidate: true })
    setAutoFilledSite({ id: site.id, name: site.label })
    previousSiteIdRef.current = site.id
  }

  // Prodotti di interesse <-> Campagna coherence (spec 0094, D-5): a
  // Campaign pick applies the Sede prefill above immediately, or — when
  // products already chosen fall outside the new campaign's categories —
  // only after the operator's explicit confirmation (AC-042).
  const campaignId = useWatch({ control: form.control, name: 'campaign_id' })
  const { campaignCategoryIds, isCampaignChangePending, handleCampaignItemChange } =
    useLeadCampaignProductInterest({
      form,
      original,
      onCampaignApplied: applyCampaignSitePrefill,
    })

  // Operatore -> Sede (AC-061, gated by D-6/AC-026/AC-027 since spec 0103):
  // picking an Operatore hydrates its own Sede from `meta` (no extra fetch),
  // mirroring the Campaign->Sede chain above, but ONLY while the Sede field
  // is still empty. With remote memberships (spec 0103) the Operatore list
  // scoped to a Sede can include people whose PHYSICAL Sede differs, so once
  // a Sede is chosen it is never overwritten by this fill — `previousSiteIdRef`
  // then needs no separate update here: it is only ever written in this
  // branch, which now only runs when the field was empty, so it always stays
  // equal to the field's actual value (see the skipped branch below).
  const handleOperatorItemChange = (item: ForSelectItem | null) => {
    if (siteId != null) return
    const operator = item as UserForSelectItem | null
    const site = operator?.meta
    if (site?.operational_site_id == null) return
    form.setValue('operational_site_id', site.operational_site_id, {
      shouldDirty: true,
      shouldValidate: true,
    })
    setAutoFilledSite({
      id: site.operational_site_id,
      name: site.operational_site_label ?? `#${site.operational_site_id}`,
    })
    previousSiteIdRef.current = site.operational_site_id
  }

  // Sede -> Operatore (AC-062): a real pick/clear on the Sede field re-scopes
  // the Operatore list, so a stale Operatore from another Sede can no longer
  // be assumed valid and is cleared — but only on an ACTUAL change, not the
  // programmatic auto-fills above, and never on mount (`onItemChange` is only
  // ever invoked from a user pick/clear, see `AsyncPaginatedSelect`).
  const handleSiteItemChange = (item: ForSelectItem | null) => {
    const nextSiteId = item?.id ?? null
    if (nextSiteId !== previousSiteIdRef.current) {
      form.setValue('operator_id', null, { shouldDirty: true })
    }
    previousSiteIdRef.current = nextSiteId
  }

  const selectLabels = {
    placeholder: t('leads.form.selectPlaceholder'),
    emptyLabel: t('leads.form.selectEmpty'),
    errorLabel: t('leads.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {mode.type === 'create' && (
            <Can permission="opportunities.create">
              <FormSection
                icon={Handshake}
                title={t('leads.form.sections.conversion.title')}
                description={t('leads.form.sections.conversion.description')}
                className={sectionRevealClassName(0)}
              >
                <MetaField
                  control={form.control}
                  name="convert_to_opportunity"
                  metaKey="convert_to_opportunity"
                  label={t('leads.form.convertToOpportunity')}
                  description={
                    <FormDescription>{t('leads.form.convertToOpportunityHint')}</FormDescription>
                  }
                >
                  {({ field, disabled }) => (
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={disabled}
                      />
                    </FormControl>
                  )}
                </MetaField>
              </FormSection>
            </Can>
          )}

          <FormSection
            icon={Contact}
            title={t('leads.form.sections.contact.title')}
            description={t('leads.form.sections.contact.description')}
            className={sectionRevealClassName(0)}
          >
            <RelationSelectField
              control={form.control}
              name="registry_id"
              metaKey="registry_id"
              label={t('leads.form.registry')}
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.registrySearch')}
              selected={original?.registry ?? null}
              required
              {...selectLabels}
            />

            <RelationSelectField
              control={form.control}
              name="campaign_id"
              metaKey="campaign_id"
              label={t('leads.form.campaign')}
              resource={CAMPAIGNS_FOR_SELECT_RESOURCE}
              searchPlaceholder={t('leads.form.campaignSearch')}
              selected={
                original?.campaign ? { id: original.campaign.id, name: original.campaign.name } : null
              }
              required
              onItemChange={handleCampaignItemChange}
              {...selectLabels}
            />
          </FormSection>

          <FormSection
            icon={ClipboardList}
            title={t('leads.form.sections.details.title')}
            description={t('leads.form.sections.details.description')}
            className={sectionRevealClassName(1)}
          >
            <div className="grid gap-3 sm:grid-cols-2">
              <RelationSelectField
                control={form.control}
                name="operational_site_id"
                metaKey="operational_site_id"
                label={t('leads.form.operationalSite')}
                hint={t('leads.form.hints.operationalSite')}
                resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.operationalSiteSearch')}
                selected={
                  autoFilledSite ??
                  (original?.operational_site
                    ? { id: original.operational_site.id, name: original.operational_site.label }
                    : null)
                }
                onItemChange={handleSiteItemChange}
                {...selectLabels}
              />

              <RelationSelectField
                control={form.control}
                name="state_id"
                metaKey="state_id"
                label={t('leads.form.state')}
                resource={STATES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.stateSearch')}
                selected={original?.state ?? null}
                {...selectLabels}
              />

              <RelationSelectField
                control={form.control}
                name="source_id"
                metaKey="source_id"
                label={t('leads.form.source')}
                hint={t('leads.form.hints.source')}
                resource={SOURCES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('leads.form.sourceSearch')}
                selected={original?.source ?? null}
                {...selectLabels}
              />

              <div className="space-y-1.5">
                <RelationSelectField
                  control={form.control}
                  name="operator_id"
                  metaKey="operator_id"
                  label={t('leads.form.operator')}
                  hint={t('leads.form.hints.operator')}
                  resource={USERS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('leads.form.operatorSearch')}
                  selected={original?.operator ?? null}
                  onItemChange={handleOperatorItemChange}
                  params={siteId != null ? { operational_site_id: siteId } : undefined}
                  showAvatar
                  {...selectLabels}
                />
                {siteId != null && (
                  <p className="text-xs text-muted-foreground">
                    {t('leads.form.hints.operatorFilteredBySite')}
                  </p>
                )}
              </div>
            </div>
          </FormSection>

          <LeadProductInterestSection
            control={form.control}
            campaignId={campaignId}
            categoryIds={campaignCategoryIds}
            knownProducts={original?.products_of_interest ?? []}
            className={sectionRevealClassName(2)}
          />

          <FormSection
            icon={StickyNote}
            title={t('leads.form.sections.notes.title')}
            description={t('leads.form.sections.notes.description')}
            className={sectionRevealClassName(3)}
            collapsible
            open={notesOpen || notesHasError}
            onOpenChange={setNotesOpen}
          >
            <MetaField control={form.control} name="notes" metaKey="notes" label={t('leads.form.notes')}>
              {({ field, disabled, readOnly }) => (
                <>
                  <FormControl>
                    <Textarea
                      className="min-h-24"
                      placeholder={t('leads.form.notesPlaceholder')}
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <span
                    aria-hidden="true"
                    className={cn(
                      'justify-self-end text-xs tabular-nums',
                      notesLength > NOTES_MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
                    )}
                  >
                    {notesLength}/{NOTES_MAX_LENGTH}
                  </span>
                </>
              )}
            </MetaField>
          </FormSection>

          <ExtraFieldsEditor
            control={form.control}
            className={sectionRevealClassName(4)}
            collapsible
            open={extraOpen || extraHasError}
            onOpenChange={setExtraOpen}
          />

          {serverError && (
            <div
              role="alert"
              className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
            >
              <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
              {serverError}
            </div>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
              {t('leads.form.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting || isCampaignChangePending}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting ? t('leads.form.saving') : t('leads.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
