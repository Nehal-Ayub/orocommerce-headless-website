# Backend (PHP MVC) - OroCommerce Middleware

This backend provides:

- Authentication (JWT session token for frontend clients)
- OroCommerce API integration (products, categories, checkout)
- Company-scoped price list resolution
- Cart and checkout endpoints used by the React frontend

## Run locally

```bash
cp .env.example .env
# PHP 8.1+ required
php -S localhost:8080 -t public
```

## Notes

- Uses a lightweight MVC structure and custom router.
- Uses in-memory demo users for local login bootstrap.
- Intended to be replaced with real Oro customer user auth in production.
