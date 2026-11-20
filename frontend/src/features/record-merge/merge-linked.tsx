import { useMemo } from 'react'
import { ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { movingLinks } from '@/features/record-merge/merge-selection'
import type { MergeLinked as MergeLinkedEntry } from '@/features/record-merge/types'

interface MergeLinkedProps {
  linked: MergeLinkedEntry[]
  survivorId: number
}

/**
 * Read-only summary of what always moves to the survivor: translated label, count and a collapsible sample.
 * Only the duplicates' links move, so the survivor's own count and samples are left out.
 */
export function MergeLinked({ linked, survivorId }: MergeLinkedProps) {
  const { t } = useTranslation()
  const moving = useMemo(() => movingLinks(linked, survivorId), [linked, survivorId])

  if (moving.length === 0) {
    return null
  }

  return (
    <section className="flex flex-col gap-2" aria-labelledby="merge-linked-title">
      <div>
        <h3 id="merge-linked-title" className="text-sm font-semibold">
          {t('recordMerge.linked.title')}
        </h3>
        <p className="text-xs text-muted-foreground">{t('recordMerge.linked.hint')}</p>
      </div>
      <ul className="grid gap-2 md:grid-cols-2">
        {moving.map((entry) => (
          <li key={entry.key} className="min-w-0 rounded-lg border bg-card px-3 py-1.5">
            <LinkedEntry entry={entry} />
          </li>
        ))}
      </ul>
    </section>
  )
}

function LinkedEntry({ entry }: { entry: MergeLinkedEntry }) {
  const { t } = useTranslation()
  const label = t(`recordMerge.linked.labels.${entry.key}`, { defaultValue: entry.key })
  const remaining = entry.count - entry.items.length

  return (
    <Collapsible className="group/linked">
      <CollapsibleTrigger className="flex w-full items-center gap-1.5 text-left text-xs font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
        <ChevronRight aria-hidden="true" className="size-3.5 shrink-0 transition-transform group-data-[state=open]/linked:rotate-90" />
        <span className="min-w-0 flex-1 truncate">{label}</span>
        <span className="text-muted-foreground">{entry.count}</span>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <ul className="mt-1 flex flex-col gap-0.5 pl-5 text-xs text-muted-foreground">
          {entry.items.map((item) => (
            <li key={item.id} className="truncate" title={item.label}>
              {item.label}
            </li>
          ))}
          {remaining > 0 ? <li>{t('recordMerge.linked.more', { count: remaining })}</li> : null}
        </ul>
      </CollapsibleContent>
    </Collapsible>
  )
}
