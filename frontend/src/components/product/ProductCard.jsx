import { Link } from 'react-router-dom'
import PriceBadge from '../common/PriceBadge'

function ProductCard({ product, onAddToCart = null }) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
      <Link to={`/product/${product.id}`} className="block">
        <img
          src={product.images?.[0] || 'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=1200'}
          alt={product.name}
          className="h-44 w-full rounded-xl object-cover"
          loading="lazy"
        />
      </Link>
      <div className="mt-4 space-y-2">
        <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">{product.sku}</p>
        <h3 className="line-clamp-2 text-base font-semibold text-slate-900">{product.name}</h3>
        <PriceBadge amount={product.price} currency={product.currency} listName={product.priceListName} />
        {onAddToCart ? (
          <button
            type="button"
            onClick={() => onAddToCart(product)}
            className="mt-2 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            Add to cart
          </button>
        ) : null}
      </div>
    </article>
  )
}

export default ProductCard
