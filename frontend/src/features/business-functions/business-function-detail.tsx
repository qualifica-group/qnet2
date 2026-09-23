import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { GitBranch, MapPin, Network, UserCog, Users } from 'lucide-react'
import { RecordLink } from '@/components/detail/record-link'
import { Badge } from '@/components/ui/badge'
import { DetailEmpty, DetailMonogram, DetailPerson } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  BusinessFunctionDetail,
  BusinessFunctionDetailWithPermissions,
} from '@/features/business-functions/types'

interface BusinessFunctionDetailViewProps {
  businessFunction: BusinessFunctionDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single business function, rendered as an
 * enterprise-CRM record on the same kit Opportunita' uses: the identity/
 * fields card on the left, the activity card on the right, a metadata
 * footer.
 */
export function BusinessFunctionDetailView({ businessFunction, onEdit }: BusinessFunctionDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(businessFunction.created_at)
  const canEdit = businessFunction.permissions.resource.update
  const canViewActivity = businessFunction.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('business-functions', businessFunction.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={businessFunction.name} icon={<Network />} />}
            title={businessFunction.name}
            badges={<Badge variant="secondary">{typeLabel(t, businessFunction.type)}</Badge>}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            {businessFunction.parent ? (
              <RecordSection title={t('businessFunctions.detail.parent')} icon={<GitBranch />}>
                <span className="text-sm text-foreground">{businessFunction.parent.name}</span>
              </RecordSection>
            ) : null}

            <RecordSection title={t('businessFunctions.detail.manager')} icon={<UserCog />}>
              {businessFunction.manager ? (
                <DetailPerson name={businessFunction.manager.name} avatarUrl={businessFunction.manager.avatar_url} />
              ) : (
                <DetailEmpty />
              )}
            </RecordSection>

            <RecordSection
              title={t('businessFunctions.detail.users')}
              icon={<Users />}
              action={
                businessFunction.users.length > 0 ? (
                  <Badge variant="secondary">{businessFunction.users.length}</Badge>
                ) : null
              }
            >
              {businessFunction.users.length > 0 ? (
                <div className="flex flex-col gap-3">
                  {businessFunction.users.map((user) => (
                    <DetailPerson key={user.id} name={user.name} avatarUrl={user.avatar_url} />
                  ))}
                </div>
              ) : (
                <DetailEmpty />
              )}
            </RecordSection>

            <RecordSection
              title={t('businessFunctions.detail.operationalSites')}
              icon={<MapPin />}
              action={
                businessFunction.operational_sites.length > 0 ? (
                  <Badge variant="secondary">{businessFunction.operational_sites.length}</Badge>
                ) : null
              }
            >
              {businessFunction.operational_sites.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                  {businessFunction.operational_sites.map((site) => (
                    <Badge key={site.id} variant="outline" className="max-w-full">
                      <RecordLink domain="operational-sites" id={site.id}>
                        {site.label}
                      </RecordLink>
                    </Badge>
                  ))}
                </div>
              ) : (
                <DetailEmpty />
              )}
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('businessFunctions.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}

/** Maps the mutually-exclusive `type` to its localized label. */
function typeLabel(t: TFunction, type: BusinessFunctionDetail['type']): string {
  if (type === 'business_unit') {
    return t('businessFunctions.form.type.businessUnit')
  }
  if (type === 'business_service') {
    return t('businessFunctions.form.type.businessService')
  }
  return t('businessFunctions.form.type.none')
}
