# 📱 VinaRoute Frontend Client - Cấu Trúc Trang

## 🎯 Trang Chính (Static Pages)

### 1️⃣ **index.html** - Trang Chủ & Tìm Chuyến
- Hero section giới thiệu dịch vụ
- Form tìm kiếm chuyến (Điểm đi → Điểm đến → Ngày → Giờ)
- Hiển thị kết quả chuyến tìm được
- Danh sách "Tuyến được ưa chuộng"
- Lợi ích dịch vụ VinaRoute
- **Tính năng**: Người dùng đã login thấy nút "Xác nhận & Lưu booking", chưa login thấy nút "Đăng nhập để đặt vé"

### 2️⃣ **login.html** - Đăng Nhập
- Form đăng nhập (Email + Mật khẩu)
- Link "Chưa có tài khoản? Đăng ký ngay"
- Xác thực CSRF token với Laravel
- Lưu session khách hàng vào localStorage
- Redirect tới dashboard.html sau khi đăng nhập thành công

### 3️⃣ **register.html** - Đăng Ký
- Form đăng ký (Họ tên, Email, Mật khẩu, Xác nhận, Số điện thoại, Vai trò)
- Link "Đã có tài khoản? Đăng nhập"
- Xác thực CSRF token
- Lưu session và redirect tới dashboard

### 4️⃣ **dashboard.html** - Dashboard Khách Hàng
**Sidebar Menu:**
- 📋 Lịch sử booking - Xem tất cả booking và trạng thái
- 👤 Thông tin cá nhân - Cập nhật hồ sơ
- 💬 Hỗ trợ - Liên hệ hotline, email, chat

**Chức năng chính:**
- Hiển thị danh sách booking từ localStorage
- Quản lý thông tin cá nhân (lưu vào localStorage)
- Nút Đăng xuất → Xóa session → Về index.html

## 🗂️ Cấu Trúc Thư Mục

```
backend/public/static/
├── index.html           (Trang chủ)
├── login.html           (Đăng nhập)
├── register.html        (Đăng ký)
├── dashboard.html       (Dashboard khách hàng)
├── css/
│   └── style.css        (CSS chính - Responsive)
└── js/
    ├── main.js          (Xử lý tìm kiếm, booking)
    └── auth.js          (Xử lý CSRF, login/register)
```

## 🔄 Luồng Dữ Liệu

### LocalStorage Keys:
- **userSession**: `{ name, email, phone }`
- **userBookings**: `[{ from, to, date, time, seats, deposit, status, ... }]`

### Luồng Người Dùng:
1. **Khách**: Xem index.html → Tìm chuyến → Yêu cầu login
2. **Đăng nhập**: login.html → Lưu session → dashboard.html
3. **Đặt vé**: Tìm chuyến → Xác nhận → Lưu vào userBookings → Dashboard
4. **Quản lý**: Dashboard → Xem booking, update profile, hỗ trợ

## ✅ Chính Sách & Điểm Cần Lưu Ý

- **Không dùng React**: Trang static HTML/CSS/JS thuần
- **CSRF Token**: Tất cả form gửi đến Laravel đều có CSRF token
- **localStorage**: Lưu session & booking tạm thời trên client
- **Responsive**: Mobile-first, hỗ trợ desktop
- **Encoding**: UTF-8 cho toàn bộ file

## 🚀 Cách Chạy

1. Xác định Laravel server chạy ở `http://127.0.0.1:8000`
2. Truy cập `http://127.0.0.1:8000/static/index.html`
3. Đăng ký → Tìm chuyến → Dashboard

## 📌 File Tham Khảo

- **style.css**: Định nghĩa màu, layout, responsive
- **main.js**: Xử lý form tìm kiếm và lưu booking
- **auth.js**: Xử lý login/register + CSRF token
