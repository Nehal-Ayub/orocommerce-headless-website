export const formatCurrency = (amount, currency = 'USD') => {
  const numericAmount = Number(amount ?? 0)
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(Number.isNaN(numericAmount) ? 0 : numericAmount)
}
