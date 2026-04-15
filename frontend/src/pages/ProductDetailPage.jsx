import { useEffect, useMemo, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useDispatch, useSelector } from 'react-redux'
import { fetchProductDetail, fetchProducts, resetProductDetail } from '../store/slices/productsSlice'
import { addItemToCart } from '../store/slices/cartSlice'
import QuantitySelector from '../components/common/QuantitySelector'
import PriceBadge from '../components/common/PriceBadge'
import SkeletonBlock from '../components/skeleton/SkeletonBlock'
import ErrorState from '../components/common/ErrorState'
import EmptyState from '../components/common/EmptyState'
import ProductCard from '../components/product/ProductCard'

const ProductDetailPage = () => {
  const { id } = useParams()
  const navigate = useNavigate()
  const dispatch = useDispatch()
  const [quantity, setQuantity] = useState(1)
  const [selectedImage, setSelectedImage] = useState('')
  const { productDetail, items, loading, detailLoading, error } = useSelector((state) => state.products)

  useEffect(() => {
    dispatch(fetchProductDetail(id))
    return () => {
      dispatch(resetProductDetail())
    }
  }, [dispatch, id])

  useEffect(() => {
    if (!items.length) {
      dispatch(fetchProducts({}))
    }
  }, [dispatch, items.length])

  const primaryImage = selectedImage || productDetail?.images?.[0] || ''

  const relatedProducts = useMemo(() => {
    if (!productDetail) {
      return []
    }
    return items.filter((item) => item.id !== productDetail.id).slice(0, 4)
  }, [productDetail, items])

  const handleAddToCart = () => {
    if (!productDetail) {
      return
    }
    dispatch(addItemToCart({ product: productDetail, quantity }))
    navigate('/cart')
  }

  if (detailLoading) {
    return (
      <div className="space-y-6">
        <SkeletonBlock className="h-10 w-40" />
        <div className="grid gap-6 md:grid-cols-2">
          <SkeletonBlock className="h-96 w-full" />
          <div className="space-y-4">
            <SkeletonBlock className="h-8 w-2/3" />
            <SkeletonBlock className="h-6 w-1/3" />
            <SkeletonBlock className="h-24 w-full" />
            <SkeletonBlock className="h-12 w-40" />
          </div>
        </div>
      </div>
    )
  }

  if (error && !productDetail) {
    return <ErrorState message={error || 'Unable to load product details.'} />
  }

  if (!productDetail) {
    return <EmptyState title="Product unavailable" description="This product could not be found." />
  }

  return (
    <div className="space-y-10">
      <div className="grid gap-8 lg:grid-cols-[1.2fr_1fr]">
        <div className="space-y-4">
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <img
              src={primaryImage}
              alt={productDetail.name}
              className="h-96 w-full object-cover"
            />
          </div>
          <div className="grid grid-cols-4 gap-3">
            {productDetail.images?.map((image) => (
              <button
                type="button"
                key={image}
                onClick={() => setSelectedImage(image)}
                className={`overflow-hidden rounded-lg border ${
                  selectedImage === image ? 'border-indigo-500' : 'border-slate-200'
                }`}
              >
                <img src={image} alt={productDetail.name} className="h-20 w-full object-cover" />
              </button>
            ))}
          </div>
        </div>
        <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
          <p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">
            {productDetail.categoryName || 'General'}
          </p>
          <h1 className="text-3xl font-bold text-slate-900">{productDetail.name}</h1>
          <PriceBadge
            amount={productDetail.price}
            listName={productDetail.priceListName}
            currency={productDetail.currency}
            className="text-lg"
          />
          <p className="text-sm leading-6 text-slate-600">
            {productDetail.description || 'Detailed product information is available via OroCommerce data.'}
          </p>

          <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Quantity</p>
            <QuantitySelector value={quantity} onChange={setQuantity} />
          </div>

          <button
            type="button"
            onClick={handleAddToCart}
            className="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            Add to cart
          </button>
        </div>
      </div>

      <section className="space-y-4">
        <h2 className="text-2xl font-bold text-slate-900">Related products</h2>
        {loading && (
          <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 4 }).map((_, idx) => (
              <SkeletonBlock key={idx} className="h-72 w-full" />
            ))}
          </div>
        )}
        {!loading && !relatedProducts.length && (
          <EmptyState title="No related products" description="Explore the shop for more items." />
        )}
        {!loading && relatedProducts.length > 0 && (
          <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {relatedProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

export default ProductDetailPage
