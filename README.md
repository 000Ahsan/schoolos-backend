# SchoolOS Backend 🚀

The robust Laravel-based backend for SchoolOS, featuring multi-tenancy (database-per-tenant), automated fee management, and WhatsApp integration.

## 🛠 Prerequisites

- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js & NPM (for asset management)
- Redis (recommended for high-performance queues)

## 📥 Installation

1.  **Clone the repository**:
    ```bash
    git clone [repository-url]
    cd schoolos-backend
    ```

2.  **Install PHP dependencies**:
    ```bash
    composer install
    ```

3.  **Install Node dependencies & build assets**:
    ```bash
    npm install
    npm run build
    ```

4.  **Environment Setup**:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *Note: Configure `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `TENANCY_CENTRAL_DOMAINS` (e.g., `localhost,127.0.0.1`) in your `.env`.*

---

## 🗄 Database & Multi-tenancy

SchoolOS uses `stancl/tenancy` for a database-per-tenant architecture.

### 1. Central Database
Run migrations for the central database (stores tenants and domains):
```bash
php artisan migrate
```

### 2. Tenant Management

#### **Creating a New Tenant**
You can create a new tenant and its associated domain via Tinker:
```bash
php artisan tinker
```
```php
$tenant = App\Models\Tenant::create(['id' => 'school1']);
$tenant->domains()->create(['domain' => 'school1.localhost']);
```

#### **Tenant Migrations**
Run migrations for all existing tenant databases:
```bash
php artisan tenants:migrate
```

#### **Tenant Seeders**
Seed all tenant databases with default data (Users, Academic Years, Classes, etc.):
```bash
php artisan tenants:seed
```

To seed a **specific tenant** database:
```bash
php artisan tenants:run db:seed --tenants=[TENANT_ID]
```

#### **Legacy Data Migration**
To migrate students from a legacy SQL dump (`pybappsc_smartdb.sql` in the root):
```bash
php artisan tenants:run db:seed --class=LegacyDataSeeder --tenants=[TENANT_ID]
```

---

## 🚀 Running the Application

### Start the Development Server
```bash
php artisan serve
```

### Run Background Workers
Crucial for WhatsApp notifications, background fee processing, and email delivery:
```bash
php artisan queue:work
```

### Development with Vite
To run the Vite dev server for live asset reloading:
```bash
npm run dev
```

---

## 🛠 Custom Artisan Commands
These commands are scoped to individual schools and should be run via `tenants:run`:

| Command | Description |
| :--- | :--- |
| `invoices:mark-overdue` | Marks pending invoices as overdue if past due date. |
| `app:migrate-to-ledger-system` | Refactors existing fee records to the new ledger system. |

**Example usage:**
```bash
php artisan tenants:run invoices:mark-overdue --tenants=school1
```

---

## 💬 WhatsApp Integration
The backend communicates with a separate `whatsapp-service`. 
1. Ensure the `whatsapp-service` is running.
2. Configure the WhatsApp API URL in your `.env`.
3. Monitor logs via `php artisan tenants:run whatsapp:logs --tenants=[TENANT_ID]`.

---
Built with ❤️ for better school management.
