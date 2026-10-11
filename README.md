# Corner Market POS

A point-of-sale system for a small shop: cashier register, inventory tracking, sales history,
printable receipts and user accounts. Built with PHP 8.1+, MySQL/MariaDB, Bootstrap 5, vanilla JS.

## Folder structure

```
pos-system/
├── config/
│   └── config.php          Database login, store name, currency, tax rate
├── database/
│   └── schema.sql          Tables + sample products and two sample users
├── includes/               Shared PHP (not web-accessible)
│   ├── bootstrap.php       Loads everything below
│   ├── db.php              PDO connection
│   ├── auth.php            Sessions, login checks, admin checks
│   ├── functions.php       Helpers (escape, money, CSRF, flash messages)
│   ├── header.php          Sidebar + page top
│   └── footer.php          Scripts + page bottom
└── public/                 Web root: point your server here
    ├── index.php           Redirects to login or dashboard
    ├── login.php / logout.php
    ├── dashboard.php       Today's sales, 7-day chart, best sellers, low stock
    ├── pos.php             The cashier register
    ├── sales.php           Sales history, void a sale (admin)
    ├── receipt.php         Printable receipt
    ├── products.php        Add / edit / archive products (admin)
    ├── categories.php      Product categories (admin)
    ├── inventory.php       Stock in/out/recount + stock history (admin)
    ├── users.php           Admin and cashier accounts (admin)
    ├── api/checkout.php    Saves a sale and deducts stock in one transaction
    └── assets/
        ├── css/style.css
        └── js/ app.js · pos.js · dashboard.js
```

## Setup

1. Create the database and sample data:
   ```
   mysql -u root -p < database/schema.sql
   ```
2. Open `config/config.php` and set `DB_USER` / `DB_PASS` for your MySQL server.
3. Start the app (from the `pos-system` folder):
   ```
   php -S localhost:8000 -t public
   ```
   Or copy the folder into XAMPP `htdocs` and open `http://localhost/pos-system/public/`.
4. Sign in:

   | Role    | Username  | Password   |
   |---------|-----------|------------|
   | Admin   | `admin`   | `password` |
   | Cashier | `cashier` | `password` |

   **Change both passwords** in Users after your first sign in.

## Notes

- Currency, store name, address and tax rate live in `config/config.php`.
- Prices and stock are always re-checked on the server at checkout. The browser cannot change a price.
- Voiding a sale returns its items to stock. Archived products are hidden from the register but kept in history.
- Cashiers see only the register, their own sales and their own dashboard. Admins see everything.
- Bootstrap, icons, fonts and Chart.js load from CDNs, so the browser needs internet access.
