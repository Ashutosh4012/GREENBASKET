# QuickBite Grocery — Full Online Grocery Management System
### Full Stack Web Project

## Overview
QuickBite Grocery is a full online grocery ordering and management system built with HTML, CSS, vanilla JavaScript, and a Node.js backend.

The UI keeps the original QuickBite design style, while the product logic is now grocery-first:
- Multi-store grocery catalog
- Product inventory with stock levels
- Real user auth + persistent sessions
- Cart, coupons, checkout, order tracking, reorder
- Admin dashboard for both order operations and inventory management

## Tech Stack
- Frontend: HTML5, CSS3, Vanilla JavaScript
- Backend: Node.js HTTP server (`server.js`)
- Storage: SQLite database (`data/store.db`), with a JSON mirror at `data/store.json`

### Storage
- User accounts, sessions, orders, inventory, and catalog changes are stored in SQLite.
- `data/store.json` is maintained as a readable mirror and migration source; it is not the primary database.
- Signup and checkout require the live backend. The browser demo fallback will not report success for those writes.
- Local SQLite storage uses Node.js 22.5 or newer. Deployments must configure persistent Vercel KV storage.

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
   npm start
   ```
3. Open:
   - `http://127.0.0.1:3000`

If the backend is unreachable, the app can use local demo data for browsing. Account creation and checkout require the live backend so they are saved in persistent storage.

Backend entry file in project root:
- `backend.js` (starts the SQLite-backed Node.js server)

## Deploy To Vercel
This project is now Vercel-ready with:
- `api/index.js` as the serverless API entry
- `api/[...route].js` catch-all route for `/api/*`

### Steps
1. Push this folder to GitHub.
2. Import the repo in Vercel.
3. Set Environment Variables:
   - `QB_ADMIN_PASSWORD` (required, strong secret)
   - `KV_REST_API_URL` and `KV_REST_API_TOKEN` (recommended for persistent data)
4. Deploy.

### Important Notes
- Without Vercel KV, the app falls back to volatile in-memory storage on Vercel Functions (non-persistent across cold starts).
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
├── api/
│   ├── index.js
│   └── [...route].js
├── index.html
├── style.css
├── app.js
├── server.js
├── data/
│   ├── store.db
│   └── store.json
├── package.json
└── README.md
```
