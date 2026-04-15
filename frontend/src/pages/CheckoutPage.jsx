import { useEffect, useMemo, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { placeOrder, setCheckoutStep, resetCheckoutState } from '../store/slices/checkoutSlice'
import { clearCart } from '../store/slices/cartSlice'
import { formatCurrency } from '../utils/formatters'

const steps = ['billing', 'shipping', 'payment', 'review']

export default function CheckoutPage() {
  const dispatch = useDispatch()
  const { placingOrder, error, step, latestOrder } = useSelector((state) => state.checkout)
  const cartItems = useSelector((state) => state.cart.items)
  const companyId = useSelector((state) => state.auth.user?.companyId)
  const [formState, setFormState] = useState({
    billing: {
      firstName: '',
      lastName: '',
      address: '',
      city: '',
      postalCode: '',
      country: '',
    },
    shippingMethod: '',
    paymentMethod: '',
  })

  useEffect(() => {
    return () => {
      dispatch(resetCheckoutState())
    }
  }, [dispatch])

  const subtotal = useMemo(
    () =>
      cartItems.reduce((sum, item) => {
        const unitPrice = Number(item.price ?? 0)
        return sum + unitPrice * Number(item.quantity ?? 1)
      }, 0),
    [cartItems],
  )

  const total = useMemo(() => subtotal, [subtotal])

  const updateBilling = (field, value) => {
    setFormState((prev) => ({
      ...prev,
      billing: {
        ...prev.billing,
        [field]: value,
      },
    }))
  }

  const proceedStep = () => {
    const currentIndex = steps.indexOf(step)
    if (currentIndex < steps.length - 1) {
      dispatch(setCheckoutStep(steps[currentIndex + 1]))
    }
  }

  const backStep = () => {
    const currentIndex = steps.indexOf(step)
    if (currentIndex > 0) {
      dispatch(setCheckoutStep(steps[currentIndex - 1]))
    }
  }

  const submitOrder = async () => {
    const payload = {
      companyId,
      lineItems: cartItems.map((item) => ({
        productId: item.productId ?? item.id,
        quantity: item.quantity,
      })),
      billingAddress: formState.billing,
      shippingMethod: formState.shippingMethod,
      paymentMethod: formState.paymentMethod,
    }

    const resultAction = await dispatch(placeOrder(payload))
    if (placeOrder.fulfilled.match(resultAction)) {
      dispatch(clearCart())
    }
  }

  if (!cartItems.length && !latestOrder) {
    return (
      <section className="rounded-2xl bg-white p-6 shadow-sm">
        <h1 className="text-2xl font-bold text-slate-900">Checkout</h1>
        <p className="mt-3 text-sm text-slate-600">
          Your cart is empty. Add products before starting checkout.
        </p>
      </section>
    )
  }

  if (latestOrder) {
    return (
      <section className="rounded-2xl bg-white p-6 shadow-sm">
        <h1 className="text-2xl font-bold text-slate-900">Order Confirmed</h1>
        <p className="mt-3 text-sm text-slate-600">
          Your order has been placed successfully in OroCommerce.
        </p>
        <p className="mt-2 text-sm text-slate-700">
          Order Number: <span className="font-semibold">{latestOrder.orderNumber ?? 'N/A'}</span>
        </p>
        <button
          type="button"
          onClick={() => dispatch(resetCheckoutState())}
          className="mt-4 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          Start new checkout
        </button>
      </section>
    )
  }

  return (
    <section className="space-y-6">
      <header className="rounded-2xl bg-white p-6 shadow-sm">
        <h1 className="text-2xl font-bold text-slate-900">Checkout</h1>
        <p className="mt-2 text-sm text-slate-600">
          Follow OroCommerce checkout sequence: Billing → Shipping → Payment → Review.
        </p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="rounded-2xl bg-white p-6 shadow-sm">
          <div className="mb-6 flex flex-wrap gap-2">
            {steps.map((s) => (
              <span
                key={s}
                className={`rounded-full px-3 py-1 text-xs font-semibold uppercase ${
                  s === step
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-100 text-slate-600'
                }`}
              >
                {s}
              </span>
            ))}
          </div>

          {step === 'billing' && (
            <div className="grid gap-4 sm:grid-cols-2">
              {Object.entries(formState.billing).map(([field, value]) => (
                <label key={field} className="flex flex-col gap-1 text-sm text-slate-700">
                  <span className="capitalize">{field}</span>
                  <input
                    value={value}
                    onChange={(event) => updateBilling(field, event.target.value)}
                    className="rounded-xl border border-slate-300 px-3 py-2 outline-none ring-blue-500 focus:ring"
                  />
                </label>
              ))}
            </div>
          )}

          {step === 'shipping' && (
            <div className="space-y-3">
              {['standard', 'express'].map((method) => (
                <label key={method} className="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="radio"
                    value={method}
                    checked={formState.shippingMethod === method}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        shippingMethod: event.target.value,
                      }))
                    }
                  />
                  {method}
                </label>
              ))}
            </div>
          )}

          {step === 'payment' && (
            <div className="space-y-3">
              {['credit_card', 'purchase_order'].map((method) => (
                <label key={method} className="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="radio"
                    value={method}
                    checked={formState.paymentMethod === method}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        paymentMethod: event.target.value,
                      }))
                    }
                  />
                  {method.replace('_', ' ')}
                </label>
              ))}
            </div>
          )}

          {step === 'review' && (
            <div className="space-y-3">
              <h2 className="text-lg font-semibold text-slate-900">Order Review</h2>
              <p className="text-sm text-slate-600">
                Validate billing, shipping, payment, and company-specific prices before placing
                order.
              </p>
            </div>
          )}

          {error && <p className="mt-4 text-sm text-red-600">{error}</p>}

          <div className="mt-6 flex items-center justify-between">
            <button
              type="button"
              onClick={backStep}
              disabled={step === steps[0]}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 disabled:opacity-40"
            >
              Back
            </button>

            {step !== 'review' ? (
              <button
                type="button"
                onClick={proceedStep}
                className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white"
              >
                Continue
              </button>
            ) : (
              <button
                type="button"
                disabled={placingOrder}
                onClick={submitOrder}
                className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
              >
                {placingOrder ? 'Placing Order...' : 'Place Order'}
              </button>
            )}
          </div>
        </div>

        <aside className="h-fit rounded-2xl bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Summary</h2>
          <div className="mt-4 space-y-2 text-sm text-slate-700">
            <div className="flex items-center justify-between">
              <span>Subtotal</span>
              <span>{formatCurrency(subtotal)}</span>
            </div>
            <div className="flex items-center justify-between font-semibold text-slate-900">
              <span>Total</span>
              <span>{formatCurrency(total)}</span>
            </div>
          </div>
        </aside>
      </div>
    </section>
  )
}
