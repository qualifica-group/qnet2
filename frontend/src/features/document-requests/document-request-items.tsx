import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import type { DocumentRequest, DocumentRequestItem } from '@/features/document-requests/types'

const OK_STATUS = 'ok'
const KO_STATUS = 'ko'

interface ItemGroup {
  key: string
  name: string | null
  items: DocumentRequestItem[]
}

/** Items under their group; an item without a (valid) group goes last under no heading. */
function groupItems(request: DocumentRequest): ItemGroup[] {
  const groups: ItemGroup[] = request.sections.map((section, index) => ({
    key: `section-${index}`,
    name: section.name,
    items: request.items.filter((item) => item.section_index === index),
  }))
  const ungrouped = request.items.filter(
    (item) => item.section_index === null || request.sections[item.section_index] === undefined,
  )
  groups.push({ key: 'ungrouped', name: null, items: ungrouped })

  return groups.filter((group) => group.items.length > 0)
}

function ItemRow({ item }: { item: DocumentRequestItem }) {
  const { t } = useTranslation()
  const variant = item.status === OK_STATUS ? 'default' : item.status === KO_STATUS ? 'destructive' : 'secondary'

  return (
    <li className="flex flex-col gap-0.5 px-3 py-1.5 text-xs">
      <div className="flex flex-wrap items-center gap-2">
        <span className="min-w-0 flex-1 break-words font-medium">
          {item.name}
          {item.is_required ? null : (
            <span className="ml-1 font-normal text-muted-foreground">({t('documentRequests.items.optional')})</span>
          )}
        </span>
        <span className="text-muted-foreground">{t('documentRequests.items.files', { count: item.documents_count })}</span>
        <Badge variant={variant}>{t(`documentRequests.itemStatus.${item.status}`, { defaultValue: item.status })}</Badge>
      </div>
      {item.status_reason ? (
        <p className="break-words text-muted-foreground">
          {t('documentRequests.items.reason')}: {item.status_reason}
        </p>
      ) : null}
    </li>
  )
}

/** Items of a request grouped by section, each with status, reason and number of files (spec 0428 AC-017). */
export function DocumentRequestItems({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const groups = groupItems(request)

  if (groups.length === 0) {
    return <p className="px-1 text-xs text-muted-foreground">{t('documentRequests.items.empty')}</p>
  }

  return (
    <div className="flex flex-col gap-2">
      {groups.map((group) => (
        <section key={group.key} aria-label={group.name ?? t('documentRequests.items.ungrouped')} className="rounded-md border bg-card">
          <h4 className="border-b bg-muted/40 px-3 py-1 text-xs font-semibold">
            {group.name ?? t('documentRequests.items.ungrouped')}
          </h4>
          <ul className="divide-y divide-border/60">
            {group.items.map((item) => (
              <ItemRow key={item.id} item={item} />
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
