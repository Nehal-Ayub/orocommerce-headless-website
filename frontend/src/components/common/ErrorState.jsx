const ErrorState = ({ title = 'Something went wrong', message, onRetry }) => {
  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
      <h3 className="font-semibold">{title}</h3>
      {message ? <p className="mt-1 text-sm">{message}</p> : null}
      {onRetry ? (
        <button
          type="button"
          onClick={onRetry}
          className="mt-3 rounded-md bg-red-700 px-3 py-2 text-sm font-medium text-white"
        >
          Retry
        </button>
      ) : null}
    </div>
  )
}

export default ErrorState
