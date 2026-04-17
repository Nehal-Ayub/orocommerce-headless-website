# OroCommerce Headless B2B eCommerce Platform

Production-oriented headless B2B eCommerce implementation with:

- **Frontend**: React + Hooks, Tailwind CSS, Axios, React Router, Redux Toolkit
- **Backend**: Clean PHP MVC middleware layer
- **Commerce core**: OroCommerce JSON:API + Checkout API
- **Key business logic**: Company-specific price list mapping and dynamic pricing

---

## 1) Project overview

This repository contains a modular full-stack B2B storefront where:

1. React app provides modern Shopify-like UX for buyers.
2. PHP middleware authenticates users, resolves company context, orchestrates Oro API calls, and enforces company-specific pricing.
3. OroCommerce remains the source of truth for products, catalogs/categories, pricing, and checkout orchestration.

The architecture follows **headless commerce** best practices:

- Presentation and user interaction in frontend.
- Domain integration and security boundaries in middleware.
- Commerce entities and checkout workflow in OroCommerce.

---

## 2) Repository structure

```text
.
├── frontend
│   ├── src
│   │   ├── app
│   │   ├── components
│   │   │   ├── catalog
│   │   │   ├── common
│   │   │   ├── layout
│   │   │   ├── product
│   │   │   └── skeleton
│   │   ├── hooks
│   │   ├── pages
│   │   ├── services
│   │   ├── store
│   │   │   └── slices
│   │   └── utils
│   ├── index.html
│   ├── package.json
│   └── vite.config.js
├── backend
│   ├── bootstrap
│   ├── config
│   ├── controllers
│   ├── helpers
│   ├── middlewares
│   ├── models
│   ├── public
│   ├── routes
│   ├── services
│   ├── storage
│   │   ├── cache
│   │   └── logs
│   ├── .env.example
│   └── composer.json
└── README.md
```

---

## 3) Tech stack

### Frontend

- React (hooks-first functional components)
- React Router (lazy loaded route-level code splitting)
- Redux Toolkit + React Redux (global state)
- Axios (API layer)
- Tailwind CSS (modern responsive UI)
- Vite (build tooling)

### Backend

- PHP 8.1+ clean MVC-style structure
- Service-layer-driven Oro integration
- JWT-style token handling for session context
- File-based cache and cart persistence for middleware-level state

### External integration

- OroCommerce JSON:API docs: `https://doc.oroinc.com/api`
- OroCommerce Checkout API docs: `https://doc.oroinc.com/api/checkout-api/`

---

## 4) Features implemented

### Authentication

- Login endpoint: `POST /api/auth/login`
- Middleware verifies bearer token and injects company/user context.
- Frontend stores token and user/company context in localStorage.

### Company-based pricing

- Backend resolves **company -> price lists** relationship using Oro APIs.
- Price list is mapped to logged-in company.
- Product prices are populated dynamically using company-specific price list records.
- Product listing, PDP, cart, and checkout all consume this dynamic pricing.

### Core pages

1. **Home page**
   - Hero section
   - Catalog preview
   - Featured products from API
2. **Shop page**
   - Product grid
   - Filters: category, price range, search
   - Pagination
3. **Catalog page**
   - Categories listing
   - Category-linked product browsing
4. **Product detail page (PDP)**
   - Gallery, description, dynamic price list-aware pricing
   - Quantity selector + add to cart
   - Related products
5. **Cart**
   - Add/update/remove
   - Dynamic company pricing values
6. **Checkout**
   - Billing, shipping, payment, review flow
   - Place order via backend checkout orchestration
7. **Contact us**
   - Name/email/message form
   - Backend submission endpoint

### UX and optimization

- Skeleton loading states
- Centralized error states
- Route lazy loading
- Reusable component architecture
- API caching at middleware layer
- Debounced search on shop

---

## 5) Frontend setup

### Prerequisites

- Node.js 20+ (tested on Node 22 runtime)
- npm

### Install and run

```bash
cd frontend
npm install
npm run dev
```

### Build and lint

```bash
npm run lint
npm run build
```

### Frontend environment variables

Create `frontend/.env`:

```env
VITE_API_BASE_URL=http://localhost:8080/api
```

---

## 6) Backend setup

### Prerequisites

- PHP 8.1+
- cURL extension enabled

### Install

Copy environment file:

```bash
cd backend
cp .env.example .env
```

### Run backend (PHP built-in server)

```bash
php -S 0.0.0.0:8080 -t public
```

### Backend environment variables

`backend/.env.example` includes:

```env
APP_NAME=Oro Headless Middleware
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:5173

SESSION_SECRET=change_this_to_a_strong_secret
JWT_TTL=3600

ORO_OAUTH_TOKEN_URL=https://your-orocommerce-instance.com/oauth2-token
ORO_CLIENT_ID=your_oro_oauth_client_id
ORO_CLIENT_SECRET=your_oro_oauth_client_secret
# backward-compatible aliases
ORO_API_CLIENT_ID=your_oro_oauth_client_id
ORO_API_CLIENT_SECRET=your_oro_oauth_client_secret

ORO_API_BASE_URL=https://your-orocommerce-instance.com/api
ORO_CHECKOUT_API_BASE_URL=https://your-orocommerce-instance.com/api

HTTP_TIMEOUT_SECONDS=30
CACHE_TTL_PRODUCTS=300
CACHE_TTL_CATEGORIES=600
CACHE_TTL_PRICE_LISTS=300
```

---

## 7) API endpoints (middleware)

### Auth

- `POST /api/auth/login`
  - Body: `{ email, password, companyCode }`
  - Returns token + user/company context

### Catalog and products

- `GET /api/products`
  - Supports filters: `search`, `categoryId`, `minPrice`, `maxPrice`, `page`, `perPage`, `featured`
- `GET /api/product/{id}`
- `GET /api/categories`

### Cart

- `GET /api/cart`
- `POST /api/cart`
  - Upserts line items and recalculates totals

### Checkout

- `POST /api/checkout/start`
- `POST /api/checkout/shipping-methods`
- `POST /api/checkout/payment-methods`
- `POST /api/checkout/review`
- `POST /api/checkout`
- `POST /api/checkout/place` (alias)

### Contact

- `POST /api/contact`

### Health

- `GET /health`

---

## 8) How OroCommerce integration works

### Authentication to Oro

1. `OroAuthService` retrieves bearer token from Oro OAuth endpoint.
2. Token is cached (with TTL minus safety buffer).
3. `OroApiClient` injects token into JSON:API requests.

### Entity relationship flow

1. User logs in to middleware and is associated to a `company_id`.
2. `OroCompanyPricingService` fetches price lists associated with company customer users.
3. Product IDs from listing/PDP are used to query company price list product prices.
4. Middleware maps prices back to products and exposes unified frontend DTO.

---

## 9) Company-based pricing logic details

The backend pricing flow:

1. Read authenticated `company_id` from token.
2. Fetch company price lists from Oro relationships.
3. Select primary (or fallback) price list for the company.
4. Fetch product prices scoped to that price list.
5. Attach:
   - `price`
   - `currency`
   - `priceListId`
   - `priceListName`
   per product response.

This guarantees the same product appears with different prices for different companies.

---

## 10) Checkout flow explanation

Frontend follows step sequence:

1. Billing
2. Shipping method
3. Payment method
4. Review
5. Place order

Backend flow:

1. Validates and normalizes line items.
2. Resolves company-specific prices before order placement.
3. Builds checkout payload in Oro-compatible shape.
4. Calls Oro checkout API endpoint.
5. Returns order metadata to frontend.

---

## 11) Deployment notes

### Frontend deployment

1. `npm run build`
2. Serve `frontend/dist` from CDN/static host (Vercel/Netlify/Nginx).
3. Set `VITE_API_BASE_URL` to deployed middleware URL.

### Backend deployment

1. Deploy `backend` to PHP runtime (Nginx+PHP-FPM or Apache).
2. Set document root to `backend/public`.
3. Configure `.env` with production Oro credentials and URLs.
4. Ensure writable permissions for:
   - `backend/storage/cache`
   - `backend/storage/logs`
5. Use HTTPS and secure secrets management.

### Production hardening recommendations

- Move cart persistence to Redis/DB.
- Replace demo login user map with Oro customer auth identity source.
- Add rate limiting + request validation middleware.
- Add structured logging and observability.
- Add CI for lint/build/smoke tests.

---

## 12) Notes about this implementation

- PHP runtime was not available in this execution environment, so backend runtime checks (`php -l`, server boot) could not be executed here.
- Frontend build and lint were executed successfully.
- The backend is structured for real Oro integration and company pricing, without hardcoded product/catalog data.
