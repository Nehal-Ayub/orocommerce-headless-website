import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import EmptyState from '../components/common/EmptyState'
import QuantitySelector from '../components/common/QuantitySelector'
import { useAppDispatch, useAppSelector } from '../hooks/useStore'
import {
  clearCart,
  removeFromCart,
  selectCartItems,
  selectCartTotal,
  updateCartQuantity,
} from '../store/slices/cartSlice'
import { formatCurrency } from '../utils/formatters'

function CartPage() {
  const dispatch = useAppDispatch()
  const items = useAppSelector(selectCartItems)
  const total = useAppSelector(selectCartTotal)

  const itemCount = useMemo(
    () => items.reduce((count, item) => count + item.quantity, 0),
    [items],
  )

  if (!items.length) {
    return (
      <EmptyState
        title="Your cart is empty"
        description="Add items from your company catalog to continue with checkout."
      />
    )
  }

  return (
    <section className="space-y-6">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-slate-900">Cart</h1>
          <p className="text-sm text-slate-500">
            {itemCount} item{itemCount > 1 ? 's' : ''} selected
          </p>
        </div>
        <button
          type="button"
          className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
          onClick={() => dispatch(clearCart())}
        >
          Clear cart
        </button>
      </header>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="space-y-4">
          {items.map((item) => (
            <article
              key={item.id}
              className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-lg font-semibold text-slate-900">
                    {item.name}
                  </h2>
                  <p className="text-sm text-slate-500">{item.sku}</p>
                  <p className="mt-2 text-sm font-semibold text-slate-700">
                    Unit price: {formatCurrency(item.price)}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <QuantitySelector
                    value={item.quantity}
                    onChange={(value) =>
                      dispatch(
                        updateCartQuantity({
                          id: item.id,
                          quantity: value,
                        }),
                      )
                    }
                  />
                  <button
                    type="button"
                    className="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    onClick={() => dispatch(removeFromCart(item.id))}
                  >
                    Remove
                  </button>
                </div>
              </div>
            </article>
          ))}
        </div>

        <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Order summary</h2>
          <dl className="mt-4 space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <dt>Subtotal</dt>
              <dd>{formatCurrency(total)}</dd>
            </div>
            <div className="flex items-center justify-between">
              <dt>Shipping</dt>
              <dd>Calculated in checkout</dd>
            </div>
            <div className="border-t border-slate-200 pt-2 text-base font-semibold text-slate-900">
              <div className="flex items-center justify-between">
                <dt>Total</dt>
                <dd>{formatCurrency(total)}</dd>
              </div>
            </div>
          </dl>
          <Link
            to="/checkout"
            className="mt-6 block rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-indigo-700"
          >
            Continue to checkout
          </Link>
        </aside>
      </div>
    </section>
  )
}

export default CartPage
