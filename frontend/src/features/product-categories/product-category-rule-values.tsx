import { DetailEmpty } from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'

interface RuleValueProps {
  value: string
  /** Set when the rule is inherited from a branch root — renders the chip naming it. */
  inheritedFrom?: string
  inheritedLabel?: string
}

/** One rule's resolved value, plus the "inherited from X" chip when it is not this category's own. */
export function RuleValue({ value, inheritedFrom, inheritedLabel }: RuleValueProps) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span>{value}</span>
      {inheritedFrom ? (
        <Badge variant="outline" className="text-xs">
          {inheritedLabel}
        </Badge>
      ) : null}
    </div>
  )
}

interface ReportColumnsValueProps {
  columns: string[]
  labelByKey: Map<string, string>
  inheritedFrom: string | null
  inheritedLabel?: string
}

/** Own or inherited report columns, one small chip each (spec 0141) — a label per key, or the empty state when none apply. */
export function ReportColumnsValue({ columns, labelByKey, inheritedFrom, inheritedLabel }: ReportColumnsValueProps) {
  if (columns.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      {columns.map((key) => (
        <Badge key={key} variant="secondary" className="text-xs">
          {labelByKey.get(key) ?? key}
        </Badge>
      ))}
      {inheritedFrom ? (
        <Badge variant="outline" className="text-xs">
          {inheritedLabel}
        </Badge>
      ) : null}
    </div>
  )
}
