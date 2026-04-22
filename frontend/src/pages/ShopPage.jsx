import { useEffect, useMemo, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useSearchParams } from 'react-router-dom'
import ProductCard from '../components/product/ProductCard'
import SkeletonBlock from '../components/skeleton/SkeletonBlock'
import ErrorState from '../components/common/ErrorState'
import EmptyState from '../components/common/EmptyState'
import { fetchProducts } from '../store/slices/productsSlice'
import { fetchCategories } from '../store/slices/categoriesSlice'
import { useDebounce } from '../hooks/useDebounce'

const PRICE_STEP = 50
const MAX_PRICE = 5000

function ShopPage() {
  const dispatch = useDispatch()
  const [searchParams] = useSearchParams()
  const { items: products, loading, error } = useSelector((state) => state.products)
  const { items: categories } = useSelector((state) => state.categories)
  const [search, setSearch] = useState('')
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || 'all')
  const [maxPrice, setMaxPrice] = useState(MAX_PRICE)
  const [page, setPage] = useState(1)
  const debouncedSearch = useDebounce(search, 350)

  useEffect(() => {
    dispatch(fetchProducts())
    dispatch(fetchCategories())
  }, [dispatch])

  const filteredProducts = useMemo(() => {
    const keyword = debouncedSearch.trim().toLowerCase()

    return products.filter((product) => {
      const inCategory =
        selectedCategory === 'all' || String(product.categoryId) === selectedCategory
      const underPrice = Number(product.price) <= maxPrice
      const matchesSearch =
        keyword.length === 0 ||
        product.name.toLowerCase().includes(keyword) ||
        String(product.sku).toLowerCase().includes(keyword)

      return inCategory && underPrice && matchesSearch
    })
  }, [products, selectedCategory, maxPrice, debouncedSearch])

  const paginatedProducts = useMemo(() => {
    const start = (page - 1) * 12
    return filteredProducts.slice(start, start + 12)
  }, [filteredProducts, page])

  const totalPages = Math.max(1, Math.ceil(filteredProducts.length / 12))

  return (
    <section className="space-y-6">
      <div className="rounded-2xl bg-white p-6 shadow-sm">
        <h1 className="text-3xl font-bold text-slate-900">Shop</h1>
        <p className="mt-2 text-slate-600">
          Explore your complete B2B assortment with company-specific pricing.
        </p>
      </div>

      <div className="grid gap-4 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-3">
        <input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          type="search"
          placeholder="Search by product name or SKU"
          className="rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"
        />

        <select
          className="rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"
          value={selectedCategory}
          onChange={(event) => setSelectedCategory(event.target.value)}
        >
          <option value="all">All categories</option>
          {categories.map((category) => (
            <option key={category.id} value={String(category.id)}>
              {category.name}
            </option>
          ))}
        </select>

        <div className="space-y-1">
          <label className="text-sm font-medium text-slate-700">
            Max price: ${maxPrice.toLocaleString()}
          </label>
          <input
            value={maxPrice}
            onChange={(event) => setMaxPrice(Number(event.target.value))}
            type="range"
            min={0}
            max={MAX_PRICE}
            step={PRICE_STEP}
            className="w-full accent-indigo-600"
          />
        </div>
      </div>

      {loading && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, index) => (
            <SkeletonBlock key={index} />
          ))}
        </div>
      )}

      {error && !loading && <ErrorState message={error} />}

      {!loading && !error && filteredProducts.length === 0 && (
        <EmptyState
          title="No products found"
          description="Try changing filters to see more items."
        />
      )}

      {!loading && !error && filteredProducts.length > 0 && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {paginatedProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>

          <div className="flex items-center justify-center gap-2">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage((previous) => Math.max(1, previous - 1))}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Previous
            </button>
            <span className="text-sm font-medium text-slate-600">
              Page {page} of {totalPages}
            </span>
            <button
              type="button"
              disabled={page >= totalPages}
              onClick={() => setPage((previous) => Math.min(totalPages, previous + 1))}
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </section>
  )
}

export default ShopPage
