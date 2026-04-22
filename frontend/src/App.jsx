import { Suspense, lazy, useEffect } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import AppLayout from './components/layout/AppLayout'
import ProtectedRoute from './components/common/ProtectedRoute'
import SkeletonBlock from './components/skeleton/SkeletonBlock'
import { useAppDispatch, useAppSelector } from './hooks/useStore'
import { fetchCart } from './store/slices/cartSlice'
import { hydrateAuthFromStorage } from './store/slices/authSlice'

const HomePage = lazy(() => import('./pages/HomePage'))
const ShopPage = lazy(() => import('./pages/ShopPage'))
const CatalogPage = lazy(() => import('./pages/CatalogPage'))
const ProductDetailPage = lazy(() => import('./pages/ProductDetailPage'))
const CartPage = lazy(() => import('./pages/CartPage'))
const CheckoutPage = lazy(() => import('./pages/CheckoutPage'))
const ContactPage = lazy(() => import('./pages/ContactPage'))
const LoginPage = lazy(() => import('./pages/LoginPage'))
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'))

function App() {
  const dispatch = useAppDispatch()
  const { token } = useAppSelector((state) => state.auth)

  useEffect(() => {
    dispatch(hydrateAuthFromStorage())
  }, [dispatch])

  useEffect(() => {
    if (token) {
      dispatch(fetchCart())
    }
  }, [dispatch, token])

  return (
    <Suspense fallback={<SkeletonBlock className="min-h-screen" />}>
      <Routes>
        <Route element={<AppLayout />}>
          <Route path="/" element={<HomePage />} />
          <Route path="/shop" element={<ShopPage />} />
          <Route path="/catalogs" element={<CatalogPage />} />
          <Route path="/product/:id" element={<ProductDetailPage />} />
          <Route path="/contact" element={<ContactPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/cart"
            element={
              <ProtectedRoute>
                <CartPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/checkout"
            element={
              <ProtectedRoute>
                <CheckoutPage />
              </ProtectedRoute>
            }
          />
          <Route path="/404" element={<NotFoundPage />} />
          <Route path="*" element={<Navigate to="/404" replace />} />
        </Route>
      </Routes>
    </Suspense>
  )
}

export default App
