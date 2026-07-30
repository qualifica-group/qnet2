import { useTranslation } from 'react-i18next'
import { splitVariableSegments } from '@/features/document-layouts/editor/preview/variable-chip-utils'
import type { ProductsTableBlock } from '@/features/document-layouts/layout-config'

/** Composes one column's cell content preview from its `lines` (D-11): keys shown as bracketed placeholders, no real record data client-side (D-8). */
function composeColumnPreview(column: ProductsTableBlock['columns'][number]): string[] {
  return column.lines.map((line) => line.keys.map((key) => `[${key}]`).join(line.separator))
}

interface PreviewProductsTableBlockProps {
  block: ProductsTableBlock
  labelFor: (variable: string) => string
}

/**
 * Preview-only rendering of the dynamic products table: no real quote lines
 * exist client-side (D-8, config-only preview), so the body row shows each
 * column's composed KEY placeholders (`[code] - [name]`) rather than
 * pretending to resolve real data.
 */
export function PreviewProductsTableBlock({ block, labelFor }: PreviewProductsTableBlockProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-1 text-[10px]" style={{ width: `${block.width_pct}%` }}>
      <table className="w-full border-collapse">
        <tbody>
          {block.show_header && (
            <tr style={{ backgroundColor: block.header_background ? `#${block.header_background}` : undefined }}>
              {block.columns.map((column, index) => (
                <th
                  key={index}
                  style={{ width: `${column.width_pct}%`, textAlign: column.align }}
                  className="border border-border p-1 font-semibold"
                >
                  {column.label || t('documentLayouts.editor.preview.untitledColumn')}
                </th>
              ))}
            </tr>
          )}
          <tr>
            {block.columns.map((column, index) => (
              <td key={index} style={{ textAlign: column.align }} className="border border-border p-1 align-top">
                {composeColumnPreview(column).map((line, lineIndex) => (
                  <p key={lineIndex}>{line || ' '}</p>
                ))}
              </td>
            ))}
          </tr>
        </tbody>
      </table>
      {block.totals.show && block.totals.rows.length > 0 && (
        <ul className="flex flex-col gap-0.5">
          {block.totals.rows.map((row, index) => (
            <li key={index} className="flex justify-between gap-2" style={{ fontWeight: row.bold ? 700 : undefined }}>
              <span>{row.label}</span>
              {splitVariableSegments(row.variable, labelFor).map((segment, segmentIndex) =>
                segment.kind === 'chip' ? (
                  <span key={segmentIndex} className="rounded bg-primary/10 px-1 text-primary">
                    {segment.label}
                  </span>
                ) : (
                  <span key={segmentIndex}>{segment.value}</span>
                ),
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
