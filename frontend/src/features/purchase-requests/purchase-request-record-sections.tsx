import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, ClipboardList, NotebookText } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { formatDate } from '@/lib/formatting/date-display'
import { PurchaseRequestApproverField } from '@/features/purchase-requests/purchase-request-approver-field'
import {
  PrioritySelectField,
  PurchaseRelationField,
  PurchaseTextField,
  type TextFieldName,
} from '@/features/purchase-requests/purchase-request-fields'
import { requestRefs } from '@/features/purchase-requests/purchase-request-refs'
import type { PurchaseRequest } from '@/features/purchase-requests/types'
import type { PurchaseRequestDetailEditor } from '@/features/purchase-requests/use-purchase-request-inline-edit'

const FOOTER_FIELDS: readonly TextFieldName[] = [
  'notes',
  'delivery_terms',
  'procurement_plan',
  'technical_requirements',
  'special_conditions',
]

/** A coupled row opens only when every field its editor writes is editable (spec 0256 D-6). */
const COMPANY_CASCADE_FIELDS = ['company_id', 'company_site_id']
const REQUESTER_CASCADE_FIELDS = ['requester_id', 'function_manager_id']
const FUNCTION_CASCADE_FIELDS = ['business_function_id', 'function_manager_id']

interface SectionProps {
  request: PurchaseRequest
  editor: PurchaseRequestDetailEditor
}

function TextValue({ value }: { value: string | null | undefined }) {
  return value && value.trim() !== '' ? (
    <span className="break-words whitespace-pre-wrap">{value}</span>
  ) : (
    <DetailEmpty />
  )
}

function NameValue({ named }: { named: { name: string } | null }) {
  return named ? <span>{named.name}</span> : <DetailEmpty />
}

function Editors({ children }: { children: ReactNode }) {
  return <div className="flex min-w-0 flex-col gap-3">{children}</div>
}

/** "Richiesta": subject, date, priority and the people of the approval flow. */
function RequestSection({ request, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control, setValue } = form
  const refs = requestRefs(request)
  const requesterEditable = useCascadeEditable(REQUESTER_CASCADE_FIELDS)
  const label = (field: string) => t(`purchaseRequests.fields.${field}`)
  const approver = <PurchaseRequestApproverField control={control} setValue={setValue} refs={refs} />

  return (
    <RecordSection title={t('purchaseRequests.sections.header')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordInlineField field="subject" label={label('subject')} inline={inline} editor={<PurchaseTextField control={control} name="subject" required />}>
          <TextValue value={request.subject} />
        </RecordInlineField>
        <RecordInlineField
          field="requested_at"
          label={label('requested_at')}
          inline={inline}
          editor={<PurchaseTextField control={control} name="requested_at" type="date" required />}
        >
          <span>{formatDate(request.requested_at) || '—'}</span>
        </RecordInlineField>
        <RecordInlineField
          field="purchase_request_priority_id"
          label={label('purchase_request_priority_id')}
          inline={inline}
          editor={<PrioritySelectField control={control} selected={refs.purchase_request_priority_id} />}
        >
          {request.priority ? <TaskLookupBadge value={request.priority} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="requester_id"
          label={label('requester_id')}
          inline={inline}
          canEdit={requesterEditable}
          editor={
            <Editors>
              <PurchaseRelationField control={control} name="requester_id" resource={USERS_FOR_SELECT_RESOURCE} selected={refs.requester_id} required />
              {approver}
            </Editors>
          }
        >
          <NameValue named={request.requester} />
        </RecordInlineField>
        <RecordInlineField field="function_manager_id" label={label('function_manager_id')} inline={inline} editor={approver}>
          <NameValue named={request.function_manager} />
        </RecordInlineField>
        <RecordField label={label('created_by')}>
          <NameValue named={request.created_by} />
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Organizzazione e riferimenti": company and site together, sites, function, customer and work order. */
function OrganizationSection({ request, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline, companyId } = editor
  const { control, setValue } = form
  const refs = requestRefs(request)
  const companyEditable = useCascadeEditable(COMPANY_CASCADE_FIELDS)
  const functionEditable = useCascadeEditable(FUNCTION_CASCADE_FIELDS)
  const label = (field: string) => t(`purchaseRequests.fields.${field}`)

  return (
    <RecordSection title={t('purchaseRequests.sections.organization')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="company_id"
          label={label('company_id')}
          inline={inline}
          canEdit={companyEditable}
          editor={
            <Editors>
              <PurchaseRelationField
                control={control}
                name="company_id"
                resource={COMPANIES_FOR_SELECT_RESOURCE}
                selected={refs.company_id}
                required
                onValueChange={() => setValue('company_site_id', null, { shouldDirty: true })}
              />
              <PurchaseRelationField
                control={control}
                name="company_site_id"
                resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
                selected={refs.company_site_id}
                params={companyId === null ? undefined : { company_id: companyId }}
                forceDisabled={companyId === null}
                required
              />
            </Editors>
          }
        >
          <span>
            {request.company.name}
            <span className="text-muted-foreground"> · {request.company_site.name}</span>
          </span>
        </RecordInlineField>
        <RecordInlineField
          field="operational_site_id"
          label={label('operational_site_id')}
          inline={inline}
          editor={<PurchaseRelationField control={control} name="operational_site_id" resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE} selected={refs.operational_site_id} required />}
        >
          <NameValue named={request.operational_site} />
        </RecordInlineField>
        <RecordInlineField
          field="business_function_id"
          label={label('business_function_id')}
          inline={inline}
          canEdit={functionEditable}
          editor={
            <Editors>
              <PurchaseRelationField control={control} name="business_function_id" resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE} selected={refs.business_function_id} required />
              <PurchaseRequestApproverField control={control} setValue={setValue} refs={refs} />
            </Editors>
          }
        >
          <NameValue named={request.business_function} />
        </RecordInlineField>
        <RecordInlineField
          field="customer_id"
          label={label('customer_id')}
          inline={inline}
          editor={<PurchaseRelationField control={control} name="customer_id" resource={REGISTRIES_FOR_SELECT_RESOURCE} selected={refs.customer_id} />}
        >
          {request.customer ? (
            <RecordLink domain="registries" id={request.customer.id}>
              {request.customer.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="work_order_id"
          label={label('work_order_id')}
          inline={inline}
          editor={<PurchaseRelationField control={control} name="work_order_id" resource={WORK_ORDERS_FOR_SELECT_RESOURCE} selected={refs.work_order_id} />}
        >
          <NameValue named={refs.work_order_id} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Note e condizioni": the five footer texts, each a long-text row. */
function FooterSection({ request, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSection title={t('purchaseRequests.sections.footer')} icon={<NotebookText />} full>
      <RecordFieldList>
        {FOOTER_FIELDS.map((name) => (
          <RecordInlineField
            key={name}
            field={name}
            label={t(`purchaseRequests.fields.${name}`)}
            inline={inline}
            editor={<PurchaseTextField control={form.control} name={name} multiline />}
          >
            <TextValue value={request[name]} />
          </RecordInlineField>
        ))}
      </RecordFieldList>
    </RecordSection>
  )
}

/** The RDA detail's header and footer, every user-written field editable in place (spec 0256 D-6). */
export function PurchaseRequestRecordSections(props: SectionProps) {
  return (
    <>
      <RequestSection {...props} />
      <OrganizationSection {...props} />
      <FooterSection {...props} />
    </>
  )
}
