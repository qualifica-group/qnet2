import { useTranslation } from 'react-i18next'
import type { HelpBlock } from '@/features/help/types'
import { renderHelpInlineText } from '@/features/help/components/help-inline-text'
import { HelpCallout } from '@/features/help/components/help-callout'

/**
 * Renders one content block (AC-008): `steps` as `<ol>`, `list` as `<ul>`,
 * `table` with `<th>` headers inside a horizontally scrollable container,
 * `tip`/`warning`/`note` as a labelled callout. Pure presentation — no
 * business logic — over the frozen `HelpBlock` union from `types.ts`.
 */
export function HelpBlockRenderer({ block }: { block: HelpBlock }) {
  const { t } = useTranslation()

  switch (block.type) {
    case 'paragraph':
      return <p className="text-sm text-foreground">{renderHelpInlineText(block.text)}</p>
    case 'steps':
      return (
        <ol className="list-decimal space-y-1 pl-5 text-sm text-foreground">
          {block.items.map((item, index) => (
            <li key={index}>{renderHelpInlineText(item)}</li>
          ))}
        </ol>
      )
    case 'list':
      return (
        <ul className="list-disc space-y-1 pl-5 text-sm text-foreground">
          {block.items.map((item, index) => (
            <li key={index}>{renderHelpInlineText(item)}</li>
          ))}
        </ul>
      )
    case 'table':
      return (
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full text-left text-xs">
            <thead className="bg-surface">
              <tr>
                {block.headers.map((header, index) => (
                  <th key={index} scope="col" className="border-b border-border px-2 py-1 font-medium">
                    {renderHelpInlineText(header)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {block.rows.map((row, rowIndex) => (
                <tr key={rowIndex}>
                  {row.map((cell, cellIndex) => (
                    <td key={cellIndex} className="border-b border-border px-2 py-1 align-top">
                      {renderHelpInlineText(cell)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )
    case 'tip':
      return <HelpCallout tone="tip" label={t('help.calloutTip')} text={block.text} />
    case 'warning':
      return <HelpCallout tone="warning" label={t('help.calloutWarning')} text={block.text} />
    case 'note':
      return <HelpCallout tone="note" label={t('help.calloutNote')} text={block.text} />
  }
}
