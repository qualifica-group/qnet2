import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REFERENT_TYPES_FOR_SELECT_RESOURCE } from '@/features/referent-types/for-select-api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { REWARD_TYPES_FOR_SELECT_RESOURCE } from '@/features/reward-types/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { CAMPAIGNS_FOR_SELECT_RESOURCE } from '@/features/campaigns/for-select-api'
import { TAGS_FOR_SELECT_RESOURCE } from '@/features/tags/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'

/**
 * Quick-create entries for the modules whose create form takes plain
 * `{type: 'create'}` and whose detail exposes the select label directly on
 * one field (`name`, except companies' `denomination`). Grouped in one file
 * to keep `quick-create-registry.tsx` a thin resource -> entry map (spec
 * 0028 §contract).
 */

const sources: QuickCreateEntry = {
  titleKey: 'sources.form.createTitle',
  descriptionKey: 'sources.form.createSubtitle',
  permission: 'sources.create',
  form: lazy(async () => {
    const { SourceForm } = await import('@/features/sources/source-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <SourceForm
          mode={{ type: 'create' }}
          onSuccess={(source) => onSuccess({ id: source.id, name: source.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const referents: QuickCreateEntry = {
  titleKey: 'referents.form.createTitle',
  descriptionKey: 'referents.form.createSubtitle',
  permission: 'referents.create',
  form: lazy(async () => {
    const { ReferentForm } = await import('@/features/referents/referent-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ReferentForm
          mode={{ type: 'create' }}
          onSuccess={(referent) => onSuccess({ id: referent.id, name: referent.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const referentTypes: QuickCreateEntry = {
  titleKey: 'referentTypes.form.createTitle',
  descriptionKey: 'referentTypes.form.createSubtitle',
  permission: 'referent-types.create',
  form: lazy(async () => {
    const { ReferentTypeForm } = await import('@/features/referent-types/referent-type-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ReferentTypeForm
          mode={{ type: 'create' }}
          onSuccess={(referentType) => onSuccess({ id: referentType.id, name: referentType.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const businessFunctions: QuickCreateEntry = {
  titleKey: 'businessFunctions.form.createTitle',
  descriptionKey: 'businessFunctions.form.createSubtitle',
  permission: 'business-functions.create',
  form: lazy(async () => {
    const { BusinessFunctionForm } = await import('@/features/business-functions/business-function-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <BusinessFunctionForm
          mode={{ type: 'create' }}
          onSuccess={(businessFunction) => onSuccess({ id: businessFunction.id, name: businessFunction.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const pipelineStatuses: QuickCreateEntry = {
  titleKey: 'pipelineStatuses.form.createTitle',
  descriptionKey: 'pipelineStatuses.form.createSubtitle',
  permission: 'pipeline-statuses.create',
  form: lazy(async () => {
    const { PipelineStatusForm } = await import('@/features/pipeline-statuses/pipeline-status-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <PipelineStatusForm
          mode={{ type: 'create' }}
          onSuccess={(pipelineStatus) => onSuccess({ id: pipelineStatus.id, name: pipelineStatus.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const rewardTypes: QuickCreateEntry = {
  titleKey: 'rewardTypes.form.createTitle',
  descriptionKey: 'rewardTypes.form.createSubtitle',
  permission: 'reward-types.create',
  form: lazy(async () => {
    const { RewardTypeForm } = await import('@/features/reward-types/reward-type-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <RewardTypeForm
          mode={{ type: 'create' }}
          onSuccess={(rewardType) => onSuccess({ id: rewardType.id, name: rewardType.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const registries: QuickCreateEntry = {
  titleKey: 'registries.form.createTitle',
  descriptionKey: 'registries.form.createSubtitle',
  permission: 'registries.create',
  form: lazy(async () => {
    const { RegistryForm } = await import('@/features/registries/registry-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <RegistryForm
          mode={{ type: 'create' }}
          onSuccess={(registry) => onSuccess({ id: registry.id, name: registry.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const campaigns: QuickCreateEntry = {
  titleKey: 'campaigns.form.createTitle',
  descriptionKey: 'campaigns.form.createSubtitle',
  permission: 'campaigns.create',
  form: lazy(async () => {
    const { CampaignForm } = await import('@/features/campaigns/campaign-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <CampaignForm
          mode={{ type: 'create' }}
          onSuccess={(campaign) => onSuccess({ id: campaign.id, name: campaign.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const tags: QuickCreateEntry = {
  titleKey: 'tags.form.createTitle',
  descriptionKey: 'tags.form.createSubtitle',
  permission: 'tags.create',
  form: lazy(async () => {
    const { TagForm } = await import('@/features/tags/tag-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TagForm
          mode={{ type: 'create' }}
          onSuccess={(tag) => onSuccess({ id: tag.id, name: tag.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const users: QuickCreateEntry = {
  titleKey: 'users.form.createTitle',
  descriptionKey: 'users.form.createSubtitle',
  permission: 'users.create',
  form: lazy(async () => {
    const { UserForm } = await import('@/features/users/user-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <UserForm
          mode={{ type: 'create' }}
          onSuccess={(user) => onSuccess({ id: user.id, name: user.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const companies: QuickCreateEntry = {
  titleKey: 'companies.form.createTitle',
  descriptionKey: 'companies.form.createSubtitle',
  permission: 'companies.create',
  form: lazy(async () => {
    const { CompanyForm } = await import('@/features/companies/company-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <CompanyForm
          mode={{ type: 'create' }}
          onSuccess={(company) => onSuccess({ id: company.id, name: company.denomination })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

/** resource -> entry, for the modules covered by this file. */
export const moduleEntries: Record<string, QuickCreateEntry> = {
  [SOURCES_FOR_SELECT_RESOURCE]: sources,
  [REFERENTS_FOR_SELECT_RESOURCE]: referents,
  [REFERENT_TYPES_FOR_SELECT_RESOURCE]: referentTypes,
  [BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE]: businessFunctions,
  [PROJECT_STATUSES_FOR_SELECT_RESOURCE]: pipelineStatuses,
  [REWARD_TYPES_FOR_SELECT_RESOURCE]: rewardTypes,
  [REGISTRIES_FOR_SELECT_RESOURCE]: registries,
  [CAMPAIGNS_FOR_SELECT_RESOURCE]: campaigns,
  [TAGS_FOR_SELECT_RESOURCE]: tags,
  [USERS_FOR_SELECT_RESOURCE]: users,
  [COMPANIES_FOR_SELECT_RESOURCE]: companies,
}
