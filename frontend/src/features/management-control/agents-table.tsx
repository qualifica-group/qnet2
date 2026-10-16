import { useTranslation } from 'react-i18next'
import type { AgentFigures, Agents } from '@/features/management-control/agents-types'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-[color-mix(in_oklab,var(--card),var(--muted)_40%)]'

/** What is left to settle comes first: it is what the page is for. The pending-documents count sits between the two groups. */
const LEADING_COLUMNS = ['to_settle', 'accrued', 'in_statement'] as const
const TRAILING_COLUMNS = ['approved_unpaid', 'paid'] as const

interface FiguresCellsProps {
  figures: AgentFigures
  pendingDocuments: number
}

function FiguresCells({ figures, pendingDocuments }: FiguresCellsProps) {
  return (
    <>
      {LEADING_COLUMNS.map((column) => (
        <td key={column} className={NUMERIC_CELL}><MoneyValue value={figures[column]} /></td>
      ))}
      <td className={`${NUMERIC_CELL} tabular-nums`}>{pendingDocuments}</td>
      {TRAILING_COLUMNS.map((column) => (
        <td key={column} className={NUMERIC_CELL}><MoneyValue value={figures[column]} /></td>
      ))}
    </>
  )
}

/**
 * Commissions by agent: the top N by amount still to settle, the rest in one "others" row, then the total
 * (spec 0272 D-5).
 */
export function AgentsTable({ data }: { data: Agents }) {
  const { t } = useTranslation()
  const { agents, others, totals } = data

  return (
    <ExposureSection title={t('managementControl.agents.table.title')} hint={t('managementControl.agents.table.hint')}>
      <ExposureTable caption={t('managementControl.agents.table.caption')}>
        <thead>
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.agents.table.agent')}</th>
            {LEADING_COLUMNS.map((column) => (
              <th key={column} scope="col" className={NUMERIC_HEAD_CELL}>{t(`managementControl.agents.figures.${column}`)}</th>
            ))}
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.agents.table.pendingDocument')}</th>
            {TRAILING_COLUMNS.map((column) => (
              <th key={column} scope="col" className={NUMERIC_HEAD_CELL}>{t(`managementControl.agents.figures.${column}`)}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {agents.map((agent) => (
            <tr key={agent.referent.id} className="group border-t hover:bg-muted/40">
              <th scope="row" title={agent.referent.name} className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>
                {agent.referent.name}
              </th>
              <FiguresCells figures={agent} pendingDocuments={agent.pending_document_count} />
            </tr>
          ))}
          {others ? (
            <tr className="group border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>
                {t('managementControl.agents.table.others', { count: others.agents_count })}
              </th>
              <FiguresCells figures={others} pendingDocuments={others.pending_document_count} />
            </tr>
          ) : null}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.agents.table.total')}</th>
            <FiguresCells figures={totals} pendingDocuments={totals.pending_document_count} />
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
