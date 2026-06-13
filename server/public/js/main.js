const searchForm = document.getElementById('searchForm');
const bookingPanel = document.getElementById('bookingResult');
const departureInput = document.getElementById('departure');
const destinationInput = document.getElementById('destination');
const travelDateInput = document.getElementById('travelDate');
const travelTimeInput = document.getElementById('travelTime');
const routeLabel = document.getElementById('routeLabel');
const summarySeats = document.getElementById('summarySeats');
const summaryCost = document.getElementById('summaryCost');
const summaryDeposit = document.getElementById('summaryDeposit');
const summaryBalance = document.getElementById('summaryBalance');
const resultMessage = document.getElementById('resultMessage');

const defaultSeatCount = 2;
let currentBooking = {};

function formatVnd(value) {
  return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
}

function setMinTravelDate() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  travelDateInput.value = tomorrow.toISOString().split('T')[0];
  travelDateInput.min = tomorrow.toISOString().split('T')[0];
}

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add('visible'), 10);
  setTimeout(() => toast.classList.remove('visible'), 3600);
  setTimeout(() => toast.remove(), 4000);
}

function updateBookingSummary(route, date, time) {
  const total = defaultSeatCount * 240000;
  const deposit = Math.round(total * 0.35);
  const remaining = total - deposit;
  routeLabel.textContent = `${route}`;
  summarySeats.textContent = `${defaultSeatCount} chỗ`;
  summaryCost.textContent = formatVnd(total);
  summaryDeposit.textContent = formatVnd(deposit);
  summaryBalance.textContent = formatVnd(remaining);
  resultMessage.textContent = `Bạn đã chọn chuyến đi ${route} vào ${date} lúc ${time}.`;
  
  currentBooking = {
    from: departureInput.value,
    to: destinationInput.value,
    date: date,
    time: time,
    seats: `${defaultSeatCount} chỗ`,
    deposit: formatVnd(deposit),
    balance: formatVnd(remaining),
    total: formatVnd(total),
    vehicleType: 'Xe VIP Limousine',
    status: 'pending'
  };
}

function handleSearch(event) {
  event.preventDefault();
  const departure = departureInput.value.trim();
  const destination = destinationInput.value.trim();
  const travelDate = travelDateInput.value;
  const travelTime = travelTimeInput.value;

  if (!departure || !destination || departure === destination) {
    showToast('Vui lòng chọn điểm đi và điểm đến khác nhau.', 'error');
    return;
  }

  if (!travelDate || !travelTime) {
    showToast('Vui lòng chọn ngày và giờ khởi hành.', 'error');
    return;
  }

  bookingPanel.classList.add('visible');
  updateBookingSummary(`${departure} → ${destination}`, travelDate, travelTime);
  window.location.hash = 'bookingResult';
}

async function saveBooking() {
  const session = JSON.parse(localStorage.getItem('userSession') || '{}');
  if (!session.email) {
    showToast('⚠️ Vui lòng đăng nhập để lưu booking', 'error');
    setTimeout(() => { window.location.href = 'login.html'; }, 1500);
    return;
  }

  try {
    // Fetch CSRF token for backend API integration
    const csrfResponse = await fetch('/csrf-token');
    if (csrfResponse.ok) {
      const csrfData = await csrfResponse.json();
      
      // Attempt to save to backend API with CSRF protection
      const apiResponse = await fetch('/customer/bookings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfData.token
        },
        body: JSON.stringify({
          ...currentBooking,
          user_email: session.email
        })
      });
      
      if (!apiResponse.ok) {
        console.warn('Backend booking save failed, using localStorage only');
      }
    }
  } catch (error) {
    console.warn('Backend booking save skipped:', error);
  }

  // Always save to localStorage for client-side persistence
  const bookings = JSON.parse(localStorage.getItem('userBookings') || '[]');
  const newBooking = { ...currentBooking, id: Date.now(), status: 'confirmed' };
  bookings.push(newBooking);
  localStorage.setItem('userBookings', JSON.stringify(bookings));
  
  showToast('✅ Booking đã được lưu! Xem chi tiết tại Dashboard.', 'success');
  setTimeout(() => { window.location.href = 'dashboard.html'; }, 1500);
}

if (searchForm) {
  searchForm.addEventListener('submit', handleSearch);
}

const confirmBtn = document.querySelector('[onclick="saveBooking()"]');
if (!confirmBtn) {
  const existingBtn = document.querySelector('.panel button[type="button"]');
  if (existingBtn && existingBtn.textContent.includes('Thanh toán')) {
    existingBtn.onclick = saveBooking;
  }
}

setMinTravelDate();
