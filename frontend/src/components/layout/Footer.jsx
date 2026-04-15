function Footer() {
  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-slate-600 md:flex-row md:items-center md:justify-between md:px-6 lg:px-8">
        <p>&copy; {new Date().getFullYear()} Oro B2B Commerce. All rights reserved.</p>
        <p>Headless commerce experience powered by OroCommerce APIs.</p>
      </div>
    </footer>
  )
}

export default Footer
