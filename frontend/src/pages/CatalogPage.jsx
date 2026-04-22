import { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link } from 'react-router-dom'
import CatalogCard from '../components/catalog/CatalogCard'
import ProductCard from '../components/product/ProductCard'
import SkeletonBlock from '../components/skeleton/SkeletonBlock'
import ErrorState from '../components/common/ErrorState'
import { fetchCategories } from '../store/slices/categoriesSlice'
import { fetchProducts } from '../store/slices/productsSlice'

function CatalogPage() {
  const dispatch = useDispatch()
  const { items: categories, loading: categoriesLoading, error: categoriesError } = useSelector(
    (state) => state.categories,
  )
  const { items: products, loading: productsLoading } = useSelector((state) => state.products)

  useEffect(() => {
    dispatch(fetchCategories())
  }, [dispatch])

  useEffect(() => {
    const categoryId = categories[0]?.id
    dispatch(fetchProducts({ categoryId }))
  }, [dispatch, categories])

  return (
    <div className="space-y-12">
      <header className="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <h1 className="text-3xl font-semibold text-slate-900">Catalogs & Categories</h1>
        <p className="mt-2 text-slate-600">
          Browse OroCommerce catalogs and jump directly to scoped product collections.
        </p>
      </header>

      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-semibold text-slate-900">All Categories</h2>
          <Link to="/shop" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
            Open full shop
          </Link>
        </div>
        {categoriesError && <ErrorState message={categoriesError} />}
        {categoriesLoading ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <SkeletonBlock key={index} className="h-36" />
            ))}
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((category) => (
              <CatalogCard key={category.id} category={category} />
            ))}
          </div>
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-2xl font-semibold text-slate-900">Products in selected category</h2>
        {productsLoading ? (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 8 }).map((_, index) => (
              <SkeletonBlock key={index} className="h-72" />
            ))}
          </div>
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

export default CatalogPage
