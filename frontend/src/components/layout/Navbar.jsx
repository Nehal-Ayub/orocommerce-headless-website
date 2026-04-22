import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useDispatch, useSelector } from 'react-redux'
import { logout } from '../../store/slices/authSlice'
import { clearCart } from '../../store/slices/cartSlice'

const linkClass = ({ isActive }) =>
  `rounded-md px-3 py-2 text-sm font-medium transition ${
    isActive ? 'bg-blue-600 text-white' : 'text-slate-700 hover:bg-slate-200'
  }`

export default function Navbar() {
  const navigate = useNavigate()
  const dispatch = useDispatch()
  const cartCount = useSelector((state) => state.cart.items.length)
  const user = useSelector((state) => state.auth.user)

  const handleLogout = () => {
    dispatch(logout())
    dispatch(clearCart())
    navigate('/login')
  }

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3">
        <div className="flex items-center gap-6">
          <Link to="/" className="text-xl font-bold text-blue-700">
            OroB2B
          </Link>
          <nav className="hidden gap-1 md:flex">
            <NavLink to="/" className={linkClass}>
              Home
            </NavLink>
            <NavLink to="/shop" className={linkClass}>
              Shop
            </NavLink>
            <NavLink to="/catalogs" className={linkClass}>
              Catalogs
            </NavLink>
            <NavLink to="/contact" className={linkClass}>
              Contact
            </NavLink>
          </nav>
        </div>

        <div className="flex items-center gap-3">
          {user ? (
            <>
              <span className="hidden text-sm text-slate-600 md:inline">
                {user.name} · {user.companyName}
              </span>
              <Link
                to="/cart"
                className="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-200"
              >
                Cart ({cartCount})
              </Link>
              <button
                type="button"
                onClick={handleLogout}
                className="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900"
              >
                Logout
              </button>
            </>
          ) : (
            <Link
              to="/login"
              className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Login
            </Link>
          )}
        </div>
      </div>
    </header>
  )
}
