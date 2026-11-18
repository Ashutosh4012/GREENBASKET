# QuickBite Grocery — Full Online Grocery Management System
### Full Stack Web Project

## Overview
QuickBite Grocery is an online grocery ordering and management system built with HTML, CSS, vanilla JavaScript, and a PHP backend.

The UI keeps the original QuickBite design style, while the product logic is now grocery-first:
- Multi-store grocery catalog
- Product inventory with stock levels
- Real user auth + persistent sessions
- Cart, coupons, checkout, order tracking, reorder
- Admin dashboard for both order operations and inventory management

## Tech Stack
- Frontend: HTML5, CSS3, Vanilla JavaScript
- Backend: PHP API (`api.php`) with a PHP built-in server router (`php-router.php`)
- Storage: JSON file (`data/store.json`)

### Storage
- User accounts, sessions, orders, inventory, and catalog changes are stored in `data/store.json`.
- The API locks the JSON file while updating it.
- Signup and checkout require the live backend. The browser demo fallback will not report success for those writes.
- PHP 8.1 or newer is recommended. The PHP process needs write access to `data/store.json`.
- Accounts from the previous Node.js backend use scrypt hashes that this PHP backend cannot verify, so existing users need to register again. Default store manager passwords continue to work.

## Main Features

### Customer Side
- Grocery store discovery by location
- Category filters and fast search
- Product-level stock visibility
- Stock-safe cart and checkout
- Coupon system (`FIRST50`, `SAVE20`, `FREEDEL`)
- Order history, live progress, reorder

### Admin Side
- Password-protected admin login
- Order status operations (Pending, Confirmed, Delivered, Cancelled)
- Live inventory metrics (total products, low stock, out of stock, value)
- Low-stock alerts
- Quick stock adjust actions (+/-)
- Add new products from dashboard

## Run Locally
1. Open the project folder in terminal
2. Run:
   ```bash
   php -S 127.0.0.1:3000 -t . php-router.php
   ```
3. Open:
   - `http://127.0.0.1:3000`

If the backend is unreachable, the app can use local demo data for browsing. Account creation and checkout require the live backend so they are saved in persistent storage.

Backend entry files in project root:
- `api.php` (PHP API)
- `php-router.php` (serves the frontend and routes `/api/*` requests)

## Deployment
Deploy to a PHP host with PHP 8.1+ and URL rewriting enabled. Route `/api/*` to `api.php`. The old JavaScript handlers in `api/` are retained as legacy files and are not used by the PHP backend.

### Steps
1. Push this folder to GitHub.
2. Deploy to a PHP hosting provider.
3. Set Environment Variables:
   - `QB_ADMIN_PASSWORD` (strong secret)
4. Ensure PHP can write to `data/store.json`.

### Important Notes
- Frontend and API use same-origin routes on deployed environments (`/api/...`).

### Admin Password
- `QuickBite@2026`

### Store Manager Passwords
- Each store can now log in separately as a **Store Manager**.
- Default pattern: `Store<StoreId>@QB`
- Example: for store ID `301`, password is `Store301@QB`
- Store managers can only:
  - View orders for their own store
  - Manage inventory for their own store
  - Add products to their own store

### Super Admin Center
- Super admin can monitor all store activity in one dashboard.
- Super admin can add products to:
  - A selected store, or
  - **All stores at once** from the Add Grocery tab.

## Project Structure
```text
QuickBite/
├── api.php
├── php-router.php
├── index.html
├── style.css
├── app.js
├── data/
│   └── store.json
├── package.json
└── README.md
```
