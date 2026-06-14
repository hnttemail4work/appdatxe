// Inject CSRF token into forms that POST to Laravel
async function ensureCsrf(form) {
  try {
    const response = await fetch('/csrf-token');
    if (!response.ok) {
      console.error('Failed to fetch CSRF token');
      return false;
    }
    const data = await response.json();
    let tokenInput = form.querySelector('input[name="_token"]');
    if (!tokenInput) {
      tokenInput = document.createElement('input');
      tokenInput.type = 'hidden';
      tokenInput.name = '_token';
      form.appendChild(tokenInput);
    }
    tokenInput.value = data.token;
    return true;
  } catch (error) {
    console.error('CSRF token injection failed:', error);
    return false;
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const profileForm = document.getElementById('profileForm');

  // Login/register: inject CSRF then submit natively so Laravel handles redirect by role
  for (const form of [loginForm, registerForm]) {
    if (!form) {
      continue;
    }

    await ensureCsrf(form);

    form.addEventListener('submit', async (event) => {
      const tokenOk = await ensureCsrf(form);
      if (!tokenOk) {
        event.preventDefault();
        alert('Lỗi bảo mật, vui lòng thử lại!');
      }
      // Allow native form POST — server redirects to the correct dashboard
    });
  }

  if (profileForm) {
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      alert('Vui lòng cập nhật thông tin tại trang Dashboard Laravel (/customer/dashboard).');
      window.location.href = '/dashboard';
    });
  }
});
