# GREENBASKET Grocery — Full Online Grocery Management System
### Full Stack Web Project · Developed by Ashutosh Panda

## Overview
**GREENBASKET** is an online grocery ordering and management system built with HTML, CSS, vanilla JavaScript, and a PHP backend (with Node.js compatibility).

The UI provides a grocery-first delivery experience:
- Multi-store grocery catalog with live store discovery
- Real-time product inventory tracking with stock levels
- User authentication and persistent user sessions
- Cart management, discount coupons, checkout, and live order tracking
- Admin dashboard for order fulfillment and stock/inventory management
- Store manager portal for individual store operations

## Tech Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript (Single Page Application architecture)
- **Backend:** PHP API (`api.php`) with built-in server router (`php-router.php`)
- **Storage:** JSON database (`data/store.json`) with atomic locking and persistent records

### Database & Storage
- User profiles, authentication sessions, orders, catalog stores, inventory, and manager credentials are stored in `data/store.json`.
- The database is owned and configured for **Ashutosh Panda**.
- The API applies file locks while updating to guarantee transactional safety.
- Default user account:
  - **Name:** Ashutosh Panda
  - **Email:** `ashutoshpanda@gmail.com`
  - **Password:** `Ashutosh@2026`

## Main Features

### Customer Side
- Grocery store discovery by location (Chandigarh, Mohali, Panchkula, etc.)
- Category filters (Vegetables, Fruits, Dairy, Bakery, Beverages, Snacks)
- Fast live search across stores and items
- Product-level stock indicators (In Stock, Low Stock, Out of Stock)
- Coupon system (`FIRST50`, `SAVE20`, `FREEDEL`)
- Real-time order progress tracking and reorder capabilities

### Admin Side
- Password-protected Admin login
- Order lifecycle management (Pending, Confirmed, Delivered, Cancelled)
- Live inventory metrics (total products, low stock, out of stock, total inventory valuation)
- Instant stock increment/decrement controls
- Add new stores and create custom or bulk product listings

## Run Locally

1. Open the project directory in your terminal:
   ```bash
   cd Quick-Bite-Grocery
   ```
2. Start the local server:
   ```bash
   php -S 127.0.0.1:3000 -t . php-router.php
   ```
   *Or with npm:*
   ```bash
   npm start
   ```
3. Open in your browser:
   - `http://127.0.0.1:3000`

### Admin Credentials
- **Super Admin Password:** `GREENBASKET@2026`

### Store Manager Passwords
- Each store manager can log in independently using:
  - Pattern: `Store<StoreId>@GB`
  - Example: for store ID `301`, password is `Store301@GB`

## Deployment
Deploy to any PHP host with PHP 8.1+ and URL rewriting enabled. Route `/api/*` to `api.php`.
- Environment Variable: `GB_ADMIN_PASSWORD` (or `QB_ADMIN_PASSWORD`)
- Ensure write permissions are granted for `data/store.json`.

## Project Structure
```text
GREENBASKET/
├── api.php             # Main PHP REST API endpoint
├── php-router.php      # Local PHP router serving frontend & API
├── index.html          # Single Page Application frontend
├── style.css           # Styling and responsive design
├── app.js              # Frontend logic, state, and API integration
├── server.js           # Optional Node.js runtime backend
├── backend.js          # Node.js backend launcher
├── data/
│   └── store.json      # JSON Database (users, orders, inventory)
├── package.json        # NPM scripts and project metadata
└── README.md           # Project documentation
```
