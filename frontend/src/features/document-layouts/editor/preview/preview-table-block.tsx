import { PreviewTextBlock } from '@/features/document-layouts/editor/preview/preview-text-block'
import type { TableBlock } from '@/features/document-layouts/layout-config'

const VERTICAL_ALIGN_CSS: Record<TableBlock['rows'][number]['cells'][number]['vertical_align'], string> = {
  top: 'top',
  center: 'middle',
  bottom: 'bottom',
}

interface PreviewTableBlockProps {
  block: TableBlock
  labelFor: (variable: string) => string
}

export function PreviewTableBlock({ block, labelFor }: PreviewTableBlockProps) {
  const border = block.borders ? `${Math.max(1, block.borders.size / 8)}px solid #${block.borders.color}` : undefined

  return (
    <table style={{ width: `${block.width_pct}%`, borderCollapse: 'collapse' }} className="text-[10px]">
      <tbody>
        {block.rows.map((row, rowIndex) => (
          <tr key={rowIndex}>
            {row.cells.map((cell, cellIndex) => (
              <td
                key={cellIndex}
                colSpan={cell.col_span}
                style={{
                  border,
                  verticalAlign: VERTICAL_ALIGN_CSS[cell.vertical_align] as 'top' | 'middle' | 'bottom',
                  backgroundColor: cell.background ? `#${cell.background}` : undefined,
                  fontWeight: row.is_header ? 700 : undefined,
                  padding: 2,
                }}
              >
                {cell.blocks.map((textBlock) => (
                  <PreviewTextBlock key={textBlock.id} block={textBlock} labelFor={labelFor} />
                ))}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  )
}
