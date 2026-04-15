const QuantitySelector = ({ value = 1, onChange, min = 1, max = 99 }) => {
  const increment = () => onChange(Math.min(max, value + 1))
  const decrement = () => onChange(Math.max(min, value - 1))

  return (
    <div className="inline-flex items-center overflow-hidden rounded-lg border border-slate-300">
      <button
        type="button"
        onClick={decrement}
        className="px-3 py-2 text-slate-700 hover:bg-slate-100"
        aria-label="Decrease quantity"
      >
        -
      </button>
      <input
        type="number"
        value={value}
        min={min}
        max={max}
        onChange={(event) => onChange(Number(event.target.value))}
        className="w-14 border-x border-slate-300 py-2 text-center"
      />
      <button
        type="button"
        onClick={increment}
        className="px-3 py-2 text-slate-700 hover:bg-slate-100"
        aria-label="Increase quantity"
      >
        +
      </button>
    </div>
  )
}

export default QuantitySelector
