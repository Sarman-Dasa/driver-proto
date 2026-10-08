# BillFlow

BillFlow is a modern financial operations and billing SaaS platform designed for businesses to manage workspaces, billing, and invoicing workflows.

> **Note**: In accordance with the application's architectural standards, company-level data isolation is referred to strictly as **Company Context**, **Workspaces**, and **Company Data Separation**.

---

## Technical Stack

- **Backend**: Laravel 13, PHP 8.3+, MySQL, Laravel Sanctum
- **Frontend**: Vue 3 (Composition API), Vite, TypeScript, Pinia, Vue Router, Vuetify 3, Axios
- **Code Quality**: Laravel Pint, PHPUnit, ESLint, Prettier

---

## Requirements

- PHP 8.3+
- Composer 2.8+
- Node.js 20+ & NPM 10+
- MySQL 8.0+

---

## Installation & Setup

### 1. Clone & Dependencies

```bash
# Install PHP dependencies
composer install

# Install Frontend dependencies
npm install
```

### 2. Environment Configuration

Copy the example environment file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

Ensure your `.env` contains the correct MySQL credentials:

```ini
APP_NAME=BillFlow
APP_ENV=local
APP_KEY=base64:...
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billflow
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Database Migrations & Seeding

```bash
# Run migrations
php artisan migrate

# Optional: Seed sample development user and workspaces
php artisan db:seed
```

Default development credentials created by the seeder:
- **Email**: `developer@billflow.test`
- **Password**: `Password123!`
- **Workspaces**: `Acme Corporation`, `Stark Industries`

---

## Development Servers

### Backend

```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api/v1`

### Frontend

```bash
npm run dev
```

Or build production assets:

```bash
npm run build
```

---

## Background Queue & Scheduler

### Queue Worker

Laravel queue is configured with the `database` driver:

```bash
php artisan queue:work
```

### Scheduler

Run the Laravel scheduler locally:

```bash
php artisan schedule:work
```

---

## Project Structure

### Backend

```text
app/
├── Actions/
│   ├── Auth/             # RegisterUserAction, AuthenticateUserAction
│   └── Company/          # CreateCompanyAction, SwitchCompanyAction
├── Console/
├── Enums/                # CompanyStatus (Active, Inactive)
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   │       ├── Auth/     # AuthController, PasswordResetController, EmailVerificationController
│   │       └── Company/  # CompanyController
│   ├── Middleware/       # EnsureCompanyAccess (company.access)
│   ├── Requests/         # Form requests with strict validation
│   └── Resources/        # UserResource, CompanyResource
├── Jobs/
├── Listeners/
├── Mail/
├── Models/               # User, Company
├── Notifications/
├── Policies/
├── Services/             # CompanyContext
└── Support/              # ApiResponse standardizer
```

### Frontend

```text
resources/js/
├── assets/
├── components/
├── layouts/
│   ├── AuthLayout.vue    # Clean layout for authentication flows
│   └── AppLayout.vue     # Main shell with workspace switcher & user menu
├── pages/
│   ├── auth/             # Login, Register, Forgot Password, Reset Password, Verify Email
│   ├── onboarding/       # Create / setup initial workspace
│   └── dashboard/        # Dashboard overview & architecture foundation status
├── plugins/
│   └── vuetify.ts        # Vuetify 3 theme and icon configuration
├── router/               # Vue Router with auth and company route guards
├── services/
│   └── api.ts            # Centralized Axios client for /api/v1 with interceptors
├── stores/
│   ├── auth.ts           # Pinia store for Sanctum auth state & actions
│   └── company.ts        # Pinia store for active company & switching
├── types/                # Strict TypeScript interfaces and API payload types
├── App.vue               # Root component with dynamic layout resolver
└── main.ts               # Application entry point
```

---

## API Architecture

Base URL: `/api/v1`

Standard Success Response:
```json
{
    "success": true,
    "message": "Request successful.",
    "data": {}
}
```

Standard Error Response:
```json
{
    "success": false,
    "message": "Error description.",
    "errors": {}
}
```

### Endpoints (Phase 0)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/auth/register` | Register user and provision initial workspace |
| `POST` | `/api/v1/auth/login` | Authenticate user & issue Sanctum token |
| `POST` | `/api/v1/auth/logout` | Revoke active access token |
| `GET` | `/api/v1/auth/me` | Retrieve authenticated user profile & companies |
| `POST` | `/api/v1/auth/forgot-password` | Request password reset notification |
| `POST` | `/api/v1/auth/reset-password` | Set new password with reset token |
| `POST` | `/api/v1/auth/verify-email` | Verify user email address |
| `POST` | `/api/v1/auth/email/resend` | Resend verification email notification |
| `GET` | `/api/v1/companies` | List all companies the user belongs to |
| `POST` | `/api/v1/companies` | Create a new company workspace |
| `POST` | `/api/v1/companies/switch` | Switch active company context |
| `GET` | `/api/v1/companies/current` | Get current active company (via header or fallback) |
| `GET` | `/api/v1/companies/{company}` | View specific company (enforces membership) |

---

## Company Architecture & Data Separation

- Each business entity is represented by a `Company` record.
- Users and Companies have a `belongsToMany` relationship mapped via the `company_users` pivot table.
- A unique database constraint on `(company_id, user_id)` guarantees prevention of duplicate memberships.
- The `EnsureCompanyAccess` middleware (`company.access`) verifies that:
  1. The user is authenticated.
  2. The company exists.
  3. The user is a member of the company.
  4. The company status is `ACTIVE`.
- Company context is resolved dynamically from either the route parameter or the `X-Company-Id` header and stored in the request-lifecycle `CompanyContext` singleton. Frontend `company_id` is never trusted without backend validation.

---

## Testing & Quality Assurance

Run the test suite:
```bash
php artisan test
```

Check code formatting (Laravel Pint):
```bash
./vendor/bin/pint --test
```

Run frontend linting:
```bash
npm run lint
```

Build frontend bundle:
```bash
npm run build
```
