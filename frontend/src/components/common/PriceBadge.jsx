import { formatCurrency } from '../../utils/formatters'

function PriceBadge({
  amount,
  price,
  currency = 'USD',
  className = '',
  listName = '',
}) {
  const value = amount ?? price ?? 0
  return (
    <div className="space-y-1">
      <p className={`text-xl font-bold text-indigo-600 ${className}`}>
        {formatCurrency(value, currency)}
      </p>
      {listName ? (
        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
          Price List: {listName}
        </p>
      ) : null}
    </div>
  )
}

export default PriceBadge
