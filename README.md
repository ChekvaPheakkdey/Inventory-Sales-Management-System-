# Inventory and Sales API

A Laravel 13 JSON API for inventory, purchasing, point of sale, expenses, and reporting.

## Inventory & Sales Management System

**Technologies:** PHP, Laravel, PostgreSQL

- Developed a web-based system to manage products, inventory, suppliers, customers, and sales transactions.
- Implemented CRUD operations, authentication, and role-based access control.
- Built sales, purchasing, invoice, and payment management features.
- Designed a PostgreSQL database with relational tables and foreign key constraints.
- Developed dashboards and reports to monitor stock levels, sales performance, and business transactions.

## Requirements and setup

- PHP 8.3+
- Composer
- SQLite, MySQL, MariaDB, or PostgreSQL

Start the included PostgreSQL service, install dependencies, configure `.env`, then migrate and seed the database:

```sh
docker compose up -d postgres
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

