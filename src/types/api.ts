// API Types
export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  role: 'customer' | 'operator' | 'admin';
  status: 'active' | 'suspended' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface AuthResponse {
  success: boolean;
  message?: string;
  data: {
    user: User;
    token: string;
  };
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
  role: 'customer' | 'operator' | 'admin';
}

export interface TripRoute {
  id: number;
  departure_city: string;
  destination_city: string;
  base_price: number;
  status: 'active' | 'inactive';
}

export interface Vehicle {
  id: number;
  operator_id: number;
  license_plate: string;
  type: 'limousine' | 'sedan' | 'suv';
  capacity: number;
  status: 'active' | 'maintenance' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface Schedule {
  id: number;
  route_id: number;
  vehicle_id: number;
  driver_name: string;
  departure_time: string;
  available_seats: number;
  status: 'draft' | 'scheduled' | 'running' | 'completed' | 'cancelled';
  route?: TripRoute;
  vehicle?: Vehicle;
  created_at: string;
  updated_at: string;
}

export interface SeatReservation {
  id: number;
  seat_number: string;
  status: 'available' | 'held' | 'booked';
  customer_name?: string;
  reserved_until?: string;
}

export interface SeatGrid {
  seat_number: string;
  status: 'available' | 'held' | 'booked';
  customer_name?: string;
  reserved_until?: string;
}

export interface Booking {
  id: number;
  customer_id: number;
  schedule_id: number;
  ticket_code: string;
  booking_reference: string;
  seat_numbers: string[];
  total_price: number;
  deposit_amount: number;
  payment_status: 'pending' | 'paid' | 'refunded';
  trip_status: 'pending' | 'confirmed' | 'completed' | 'cancelled';
  pickup_address: string;
  dropoff_address: string;
  notes?: string;
  customer?: User;
  schedule?: Schedule;
  created_at: string;
  updated_at: string;
}

export interface BookingRequest {
  schedule_id: number;
  seat_numbers: string[];
  pickup_address: string;
  dropoff_address: string;
  notes?: string;
}

export interface PaymentTransaction {
  id: number;
  booking_id: number;
  provider: 'vietqr' | 'momo' | 'bank_transfer';
  amount: number;
  currency: string;
  status: 'pending' | 'succeeded' | 'failed';
  transaction_ref: string;
  created_at: string;
}

export interface MerchantProfile {
  id: number;
  user_id: number;
  business_name: string;
  kyc_status: 'pending' | 'approved' | 'rejected' | 'suspended';
  verification_document_url?: string;
  user?: User;
  created_at: string;
  updated_at: string;
}

export interface PlatformSetting {
  key: string;
  value: any;
}

export interface RevenueAnalytics {
  date_from: string;
  date_to: string;
  gross_revenue: number;
  platform_commission_earned: number;
  operator_payout_total: number;
  operators: Array<{
    operator_id: number;
    operator_name: string;
    gross_revenue: number;
    commission: number;
    payout: number;
  }>;
}

export interface TripSearchFilters {
  departure_city?: string;
  destination_city?: string;
  date?: string;
  time?: string;
  vehicle_type?: 'limousine' | 'sedan' | 'suv';
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}
