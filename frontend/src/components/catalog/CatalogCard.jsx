import { Link } from 'react-router-dom'

function CatalogCard({ category }) {
  return (
    <Link
      to={`/shop?category=${encodeURIComponent(category.id)}`}
      className="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md"
    >
      <h3 className="text-lg font-semibold text-slate-900">{category.name}</h3>
      <p className="mt-2 text-sm text-slate-600 line-clamp-2">
        {category.description || 'Explore this catalog category'}
      </p>
    </Link>
  )
}

export default CatalogCard
