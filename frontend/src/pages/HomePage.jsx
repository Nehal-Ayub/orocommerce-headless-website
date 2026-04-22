import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useDispatch, useSelector } from 'react-redux'
import { fetchProducts } from '../store/slices/productsSlice'
import { fetchCategories } from '../store/slices/categoriesSlice'
import ProductCard from '../components/product/ProductCard'
import CatalogCard from '../components/catalog/CatalogCard'
import SkeletonBlock from '../components/skeleton/SkeletonBlock'
import ErrorState from '../components/common/ErrorState'

function HomePage() {
  const dispatch = useDispatch()
  const { items: products, loading: productsLoading, error: productsError } = useSelector((state) => state.products)
  const { items: categories, loading: categoriesLoading, error: categoriesError } = useSelector((state) => state.categories)

  useEffect(() => {
    dispatch(fetchProducts({ featured: true, page: 1, perPage: 8 }))
    dispatch(fetchCategories())
  }, [dispatch])

  return (
    <div className="space-y-14">
      <section className="rounded-2xl bg-gradient-to-r from-blue-700 to-indigo-700 px-8 py-12 text-white">
        <p className="mb-3 text-sm uppercase tracking-[0.2em] text-blue-100">B2B Commerce Platform</p>
        <h1 className="max-w-3xl text-4xl font-bold leading-tight md:text-5xl">
          Build procurement experiences your company buyers trust.
        </h1>
        <p className="mt-4 max-w-2xl text-lg text-blue-100">
          Connected to OroCommerce headless APIs for real-time catalogs, company-specific pricing, and checkout orchestration.
        </p>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link to="/shop" className="rounded-lg bg-white px-5 py-3 font-semibold text-blue-700 transition hover:bg-blue-50">
            Explore Shop
          </Link>
          <Link
            to="/catalogs"
            className="rounded-lg border border-blue-300 px-5 py-3 font-semibold text-white transition hover:bg-blue-600"
          >
            Browse Catalogs
          </Link>
        </div>
      </section>

      <section>
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-2xl font-bold text-slate-900">Catalog Categories</h2>
          <Link to="/catalogs" className="text-sm font-medium text-blue-700 hover:text-blue-900">
            View all
          </Link>
        </div>
        {categoriesError ? <ErrorState message={categoriesError} /> : null}
        {categoriesLoading ? (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {[...Array(3)].map((_, index) => (
              <SkeletonBlock key={index} className="h-40 rounded-2xl" />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {categories.slice(0, 6).map((category) => (
              <CatalogCard key={category.id} category={category} />
            ))}
          </div>
        )}
      </section>

      <section>
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-2xl font-bold text-slate-900">Featured Products</h2>
          <Link to="/shop" className="text-sm font-medium text-blue-700 hover:text-blue-900">
            Shop all
          </Link>
        </div>
        {productsError ? <ErrorState message={productsError} /> : null}
        {productsLoading ? (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {[...Array(4)].map((_, index) => (
              <SkeletonBlock key={index} className="h-72 rounded-2xl" />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {products.slice(0, 8).map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

export default HomePage
