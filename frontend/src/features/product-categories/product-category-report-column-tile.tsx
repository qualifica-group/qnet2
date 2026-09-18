import { Link2 } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'

interface ProductCategoryReportColumnTileProps {
  id: string
  label: string
  checked: boolean
  /** Checked because it is INHERITED (no own override), not this category's own pick — attenuated style + link glyph. */
  inherited: boolean
  disabled: boolean
  onToggle: () => void
}

/**
 * One selectable statistics column of the report-columns grid (spec 0141):
 * the whole tile — border, tint, label text — is the click target via a
 * native `<label htmlFor>`, never a clickable `<div>` (ui-design.md, WCAG
 * target size). Defined at module level, never inside the field component.
 *
 * An inherited-but-checked tile reads as "already applies here, not yours to
 * remove": dashed border and a small link glyph distinguish it from an own
 * selection, which gets the same primary tint but a solid border — state is
 * never signalled by color alone.
 */
export function ProductCategoryReportColumnTile({
  id,
  label,
  checked,
  inherited,
  disabled,
  onToggle,
}: ProductCategoryReportColumnTileProps) {
  return (
    <label
      htmlFor={id}
      className={cn(
        'flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-colors',
        'has-[:focus-visible]:ring-[2px] has-[:focus-visible]:ring-ring/50',
        disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
        checked
          ? inherited
            ? 'border-dashed border-primary/40 bg-primary/5 text-foreground'
            : 'border-primary/50 bg-primary/10 text-foreground'
          : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:bg-muted/40 hover:text-foreground',
      )}
    >
      <Checkbox id={id} checked={checked} disabled={disabled} onCheckedChange={onToggle} />
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {inherited && checked ? (
        <Link2 aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
      ) : null}
    </label>
  )
}
