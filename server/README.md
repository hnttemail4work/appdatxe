# gozviet - Laravel Backend

A complete Laravel 11 backend API for a long-distance car booking platform with support for customers, operators, and administrators.

## Features

- **Authentication**: Sanctum token-based API authentication
- **Customer Module**: Trip search, booking management, payment processing
- **Operator Module**: Vehicle management, schedule management, passenger tracking
- **Admin Module**: Merchant approval, commission settings, order audit, revenue analytics
- **Database**: 11 tables with proper relationships and constraints
- **Validation**: Form request validation for all endpoints
- **Role-Based Access Control**: Custom middleware for role-based authorization

## Installation

1. Install PHP 8.2+ and Composer

2. Clone the repository and navigate to the backend folder

3. Install dependencies:
```bash
cd backend
composer install
```

4. Create environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure database in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_platform
DB_USERNAME=root
DB_PASSWORD=
```

7. Run migrations:
```bash
php artisan migrate
```

8. Seed sample data:
```bash
php artisan db:seed
```

9. Start the development server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api`

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login and get token
- `GET /api/auth/me` - Get authenticated user
- `POST /api/auth/logout` - Logout (revoke token)

### Customer Routes
- `GET /api/customer/trips/search` - Search available trips
- `GET /api/customer/trips/{id}` - Get trip details with seat map
- `GET /api/customer/bookings` - List customer bookings
- `POST /api/customer/bookings` - Create new booking
- `GET /api/customer/bookings/{id}` - Get booking details
- `POST /api/customer/bookings/{id}/confirm-payment` - Confirm payment
- `DELETE /api/customer/bookings/{id}` - Cancel booking

### Operator Routes
- `GET /api/operator/vehicles` - List vehicles
- `POST /api/operator/vehicles` - Create vehicle
- `GET /api/operator/vehicles/{id}` - Get vehicle details
- `PUT /api/operator/vehicles/{id}` - Update vehicle
- `DELETE /api/operator/vehicles/{id}` - Delete vehicle
- `GET /api/operator/schedules` - List schedules
- `POST /api/operator/schedules` - Create schedule
- `GET /api/operator/schedules/{id}/seat-grid` - Get seat availability
- `GET /api/operator/passengers` - List passengers for upcoming trips

### Admin Routes
- `GET /api/admin/merchants` - List merchants (operators)
- `POST /api/admin/merchants/{id}/approve` - Approve merchant
- `POST /api/admin/merchants/{id}/suspend` - Suspend merchant
- `GET /api/admin/commission-settings` - Get commission settings
- `PUT /api/admin/commission-settings` - Update commission settings
- `GET /api/admin/orders` - List all orders with audit trail
- `GET /api/admin/orders/{id}` - Get order details with audit trail
- `GET /api/admin/analytics/revenue` - Get revenue analytics

## Database Schema

### Users Table
Stores customer, operator, and admin users with role-based access

### Trip Routes
Defines available trip routes (departure and destination cities)

### Vehicles
Fleet vehicles owned by operators with type and capacity

### Schedules
Trip schedules for specific routes and vehicles

### Bookings
Customer bookings with seat numbers and payment status

### Seat Reservations
Tracks seat holds and bookings with expiration times

### Payment Transactions
Payment records with provider and status information

### Merchant Profiles
Operator KYC verification and approval status

### Platform Settings
Global configuration (commission percentage)

### Booking Audits
Audit trail for all booking state changes

### Payouts
Operator payout records

## Models & Relationships

- **User** → has many Bookings, Vehicles, MerchantProfiles, BookingAudits
- **TripRoute** → has many Schedules
- **Vehicle** → belongs to User (operator), has many Schedules
- **Schedule** → belongs to TripRoute, Vehicle; has many SeatReservations, Bookings
- **Booking** → belongs to User (customer), Schedule; has many SeatReservations, PaymentTransactions, BookingAudits
- **SeatReservation** → belongs to Schedule, Booking, User (customer)
- **PaymentTransaction** → belongs to Booking
- **MerchantProfile** → belongs to User
- **BookingAudit** → belongs to Booking, User (actor)
- **Payout** → belongs to User (operator)

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── Auth/
│   │   │   ├── Customer/
│   │   │   ├── Operator/
│   │   │   └── Admin/
│   │   ├── Middleware/
│   │   ├── Requests/Api/
│   │   └── Kernel.php
│   ├── Models/
│   ├── Providers/
│   └── Exceptions/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── public/
│   └── index.php
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
├── storage/
├── .env
├── .env.example
├── artisan
├── composer.json
└── composer.lock
```

## Testing

Run tests with PHPUnit:
```bash
php artisan test
```

## Notes

- Booking deposits are calculated as 30% of total price
- Seat reservations expire after 15 minutes
- Payment processing uses mock providers (VietQR, MoMo)
- All API responses follow a consistent JSON format
