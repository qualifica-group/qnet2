import { useTranslation } from 'react-i18next'
import { Card, CardContent } from '@/components/ui/card'
import { formatCount, formatUsd } from '@/features/ai-assistant-costs/format'
import type { AiCostFunction, AiCostModel, AiCostUser } from '@/features/ai-assistant-costs/types'

interface Row {
  key: string
  name: string
  requests: number
  inputTokens: number | null
  outputTokens: number | null
  cost: string
}

interface CostTableProps {
  title: string
  nameHeader: string
  rows: Row[]
  note?: string
}

/** One breakdown as a table: figures only, scrollable sideways on a narrow screen. */
function CostTable({ title, nameHeader, rows, note }: CostTableProps) {
  const { t } = useTranslation('aiAssistantCosts')
  const hasTokens = rows.some((row) => row.inputTokens !== null)

  return (
    <Card className="gap-2 py-3">
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="text-xs font-medium text-muted-foreground">{title}</h3>
        {rows.length === 0 ? (
          <p className="text-xs text-muted-foreground">{t('tables.empty')}</p>
        ) : (
          <div className="max-w-full overflow-x-auto rounded-md border border-border">
            <table className={`w-full text-left text-xs ${hasTokens ? 'min-w-[30rem]' : ''}`}>
              <thead className="text-muted-foreground">
                <tr>
                  <th scope="col" className="px-2 py-1 font-medium">{nameHeader}</th>
                  <th scope="col" className="px-2 py-1 text-right font-medium">{t('tables.requests')}</th>
                  {hasTokens ? (
                    <>
                      <th scope="col" className="px-2 py-1 text-right font-medium">{t('tables.inputTokens')}</th>
                      <th scope="col" className="px-2 py-1 text-right font-medium">{t('tables.outputTokens')}</th>
                    </>
                  ) : null}
                  <th scope="col" className="px-2 py-1 text-right font-medium">{t('tables.cost')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.key} className="border-t border-border tabular-nums">
                    <th scope="row" className="max-w-[16rem] truncate px-2 py-1 font-medium">{row.name}</th>
                    <td className="px-2 py-1 text-right">{formatCount(row.requests)}</td>
                    {hasTokens ? (
                      <>
                        <td className="px-2 py-1 text-right">{formatCount(row.inputTokens ?? 0)}</td>
                        <td className="px-2 py-1 text-right">{formatCount(row.outputTokens ?? 0)}</td>
                      </>
                    ) : null}
                    <td className="px-2 py-1 text-right font-medium">{formatUsd(row.cost)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {note ? <p className="text-xs text-muted-foreground">{note}</p> : null}
      </CardContent>
    </Card>
  )
}

export function FunctionCostTable({ rows }: { rows: AiCostFunction[] }) {
  const { t } = useTranslation('aiAssistantCosts')

  return (
    <CostTable
      title={t('tables.byFunction')}
      nameHeader={t('tables.function')}
      note={t('tables.functionNote')}
      rows={rows.map((row) => ({
        key: String(row.business_function_id ?? 'none'),
        name: row.name ?? t('tables.noFunction'),
        requests: row.requests,
        inputTokens: null,
        outputTokens: null,
        cost: row.cost_usd,
      }))}
    />
  )
}

export function UserCostTable({ rows }: { rows: AiCostUser[] }) {
  const { t } = useTranslation('aiAssistantCosts')

  return (
    <CostTable
      title={t('tables.byUser')}
      nameHeader={t('tables.user')}
      rows={rows.map((row) => ({
        key: String(row.user_id),
        name: row.name ?? t('tables.deletedPerson'),
        requests: row.requests,
        inputTokens: row.input_tokens,
        outputTokens: row.output_tokens,
        cost: row.cost_usd,
      }))}
    />
  )
}

export function ModelCostTable({ rows }: { rows: AiCostModel[] }) {
  const { t } = useTranslation('aiAssistantCosts')

  return (
    <CostTable
      title={t('tables.byModel')}
      nameHeader={t('tables.model')}
      rows={rows.map((row) => ({
        key: row.model_id,
        name: row.model_id,
        requests: row.requests,
        inputTokens: row.input_tokens,
        outputTokens: row.output_tokens,
        cost: row.cost_usd,
      }))}
    />
  )
}
