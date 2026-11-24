import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { formatDate } from '@/lib/formatting/date-display'
import { CommissionInput } from '@/integrations/formalab/learner-statement/commission-input'
import { euro, rate, splitHours, splitMinutes } from '@/integrations/formalab/learner-statement/format'
import type {
  CommissionPayload,
  LearnerStatement,
  PhaseMinutes,
  StatementLearner,
} from '@/integrations/formalab/learner-statement/types'

const PERCENT_STEP = 0.5

const AMOUNT_STEP = 0.01

const MAX_PERCENTAGE = 100

const PRESENCE_PHASES: (keyof PhaseMinutes)[] = ['presence_training', 'presence_internship']

const ABSENCE_PHASES: (keyof PhaseMinutes)[] = ['absence_training', 'absence_internship']

interface LearnersTableProps {
  statement: LearnerStatement
  onCommission: (taxCode: string, payload: CommissionPayload) => void
  saving: boolean
}

/** "Elenco Discenti": attendance, cost, revenue and partner commission of each learner (spec 0427). */
export function LearnersTable({ statement, onCommission, saving }: LearnersTableProps) {
  const { t } = useTranslation('formalab')
  const twoPhase = statement.class?.two_phase === true
  const { totals } = statement
  const phases = twoPhase ? totals.phases : null
  const minutes = (value: number) => {
    const [hours, rest] = splitMinutes(value)
    return t('learnerStatement.learners.hoursMinutesShort', { hours, minutes: rest })
  }

  return (
    <div className="overflow-x-auto rounded-lg border bg-card">
      <table className="w-full min-w-max text-xs">
        <thead className="bg-surface text-left text-muted-foreground">
          <tr>
            {[
              '#', 'lastName', 'firstName', 'presence',
              ...(twoPhase ? ['presenceTraining', 'presenceInternship'] : []),
              'absence',
              ...(twoPhase ? ['absenceTraining', 'absenceInternship'] : []),
              'passed', 'costRate', 'orderAmount', 'revenueRate', 'revenueHours', 'revenue', 'partner', 'percentage', 'commission', 'order',
            ].map((key) => (
              <th key={key} scope="col" className="whitespace-nowrap px-2 py-1.5 font-medium">
                {key === '#' ? '#' : t(`learnerStatement.learners.columns.${key}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {statement.learners.map((learner, index) => (
            <LearnerRow
              key={learner.tax_code}
              learner={learner}
              number={index + 1}
              statement={statement}
              onCommission={onCommission}
              saving={saving}
            />
          ))}
        </tbody>
        <tfoot className="bg-muted font-semibold">
          <tr>
            <td colSpan={3} className="px-2 py-1.5 text-right">{t('learnerStatement.learners.totalHours')}</td>
            <td className="px-2 py-1.5">{t('learnerStatement.learners.hoursMinutesShort', { ...totals.presence })}</td>
            {phases ? PRESENCE_PHASES.map((key) => <td key={key} className="px-2 py-1.5">{minutes(phases[key])}</td>) : null}
            <td className="px-2 py-1.5">{t('learnerStatement.learners.hoursMinutesShort', { ...totals.absence })}</td>
            {phases ? ABSENCE_PHASES.map((key) => <td key={key} className="px-2 py-1.5">{minutes(phases[key])}</td>) : null}
            <td colSpan={2} className="px-2 py-1.5 text-right">{t('learnerStatement.learners.total')}</td>
            <td className="px-2 py-1.5 text-right tabular-nums">{euro(totals.learner_costs)}</td>
            <td colSpan={2} className="px-2 py-1.5 text-right">{t('learnerStatement.learners.totalRevenue')}</td>
            <td className="px-2 py-1.5 text-right tabular-nums">{euro(totals.learner_revenue)}</td>
            <td colSpan={4} />
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

interface LearnerRowProps {
  learner: StatementLearner
  number: number
  statement: LearnerStatement
  onCommission: (taxCode: string, payload: CommissionPayload) => void
  saving: boolean
}

function LearnerRow({ learner, number, statement, onCommission, saving }: LearnerRowProps) {
  const { t } = useTranslation('formalab')
  const twoPhase = statement.class?.two_phase === true && learner.phases !== null
  const passed = learner.passed === true
  const [revenueHours, revenueMinutes] = splitHours(learner.revenue_hours)
  const fullName = `${learner.first_name} ${learner.last_name}`
  const phase = (key: keyof PhaseMinutes) => {
    const [hours, minutes] = splitMinutes(learner.phases?.[key] ?? 0)
    return <td key={key} className="whitespace-nowrap px-2 py-1">{t('learnerStatement.learners.hoursMinutes', { hours, minutes })}</td>
  }

  return (
    <tr className="border-t">
      <td className="px-2 py-1 text-muted-foreground">{number}</td>
      <td className="whitespace-nowrap px-2 py-1">{learner.last_name}</td>
      <td className="whitespace-nowrap px-2 py-1">{learner.first_name}</td>
      <td className="whitespace-nowrap px-2 py-1">{t('learnerStatement.learners.hoursMinutes', { ...learner.presence })}</td>
      {twoPhase ? PRESENCE_PHASES.map(phase) : null}
      <td className="whitespace-nowrap px-2 py-1">{t('learnerStatement.learners.hoursMinutes', { ...learner.absence })}</td>
      {twoPhase ? ABSENCE_PHASES.map(phase) : null}
      <td className="px-2 py-1 text-center font-semibold">{t(passed ? 'learnerStatement.learners.yes' : 'learnerStatement.learners.no')}</td>
      <td className="whitespace-nowrap px-2 py-1">{t('learnerStatement.learners.perHour', { rate: rate(statement.rates.cost_per_hour) })}</td>
      <td className="px-2 py-1 text-right tabular-nums">{passed ? euro(learner.cost) : null}</td>
      <td className="whitespace-nowrap px-2 py-1">{t('learnerStatement.learners.perHour', { rate: rate(statement.rates.revenue_per_hour) })}</td>
      <td className="whitespace-nowrap px-2 py-1 font-semibold">{t('learnerStatement.learners.revenueHoursValue', { hours: revenueHours, minutes: revenueMinutes })}</td>
      <td className="px-2 py-1 text-right tabular-nums">{euro(learner.revenue)}</td>
      <td className="max-w-40 truncate px-2 py-1" title={learner.partner ?? undefined}>{learner.partner}</td>
      {passed ? (
        <>
          <td className="px-2 py-1">
            {learner.partner === null ? <span className="text-muted-foreground">-</span> : statement.can_manage ? (
              <CommissionInput
                key={`p-${learner.percentage}`}
                value={learner.percentage}
                onCommit={(percentage) => onCommission(learner.tax_code, { percentage })}
                label={t('learnerStatement.learners.percentageOf', { name: fullName })}
                max={MAX_PERCENTAGE}
                step={PERCENT_STEP}
                placeholder="%"
                disabled={saving}
              />
            ) : `${learner.percentage}%`}
          </td>
          <td className="px-2 py-1">
            <CommissionCell learner={learner} statement={statement} onCommission={onCommission} saving={saving} fullName={fullName} />
          </td>
        </>
      ) : (
        <td colSpan={2} />
      )}
      <td className="whitespace-nowrap px-2 py-1">
        {learner.learner_order ? (
          <Badge variant="secondary" className="text-[11px]">
            {t('learnerStatement.learners.orderLabel', { id: learner.learner_order.id, date: formatDate(learner.learner_order.date) })}
          </Badge>
        ) : (
          <span className="text-muted-foreground">-</span>
        )}
      </td>
    </tr>
  )
}

/** With a percentage the amount is computed (read-only); without, it is typed by hand. */
function CommissionCell({ learner, statement, onCommission, saving, fullName }: Omit<LearnerRowProps, 'number'> & { fullName: string }) {
  const { t } = useTranslation('formalab')

  if (learner.partner === null) {
    return <span className="text-muted-foreground">-</span>
  }
  if (learner.percentage > 0) {
    return (
      <Badge variant="secondary" className="bg-success/20 text-[11px] text-foreground" title={t('learnerStatement.learners.computedHint')}>
        {euro(learner.commission)}
      </Badge>
    )
  }
  if (!statement.can_manage) {
    return <span className="tabular-nums">{euro(learner.amount)}</span>
  }

  return (
    <CommissionInput
      key={`a-${learner.amount}`}
      value={learner.amount}
      onCommit={(amount) => onCommission(learner.tax_code, { amount })}
      label={t('learnerStatement.learners.amountOf', { name: fullName })}
      step={AMOUNT_STEP}
      placeholder="€"
      disabled={saving}
    />
  )
}
