import { Link } from 'react-router-dom'

function NotFoundPage() {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
      <p className="text-sm font-medium uppercase tracking-wide text-indigo-600">404</p>
      <h1 className="mt-3 text-3xl font-semibold text-slate-900">Page not found</h1>
      <p className="mt-2 text-slate-600">The page you are looking for does not exist.</p>
      <Link
        to="/"
        className="mt-6 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
      >
        Back to Home
      </Link>
    </div>
  )
}

export default NotFoundPage
