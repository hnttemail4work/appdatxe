// Fetch and inject CSRF token into form
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

// Initialize form handlers when page loads
document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const profileForm = document.getElementById('profileForm');

  // Login form handler
  if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const tokenOk = await ensureCsrf(loginForm);
      
      if (!tokenOk) {
        alert('⚠️ Lỗi bảo mật, vui lòng thử lại!');
        return;
      }

      const email = document.getElementById('loginEmail').value;
      const password = document.getElementById('loginPassword').value;
      const name = email.split('@')[0];
      
      try {
        const formData = new FormData(loginForm);
        const response = await fetch('/login', {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          const session = { name, email };
          localStorage.setItem('userSession', JSON.stringify(session));
          alert('✅ Đăng nhập thành công!');
          window.location.href = 'dashboard.html';
        } else {
          alert('❌ Email hoặc mật khẩu không đúng!');
        }
      } catch (error) {
        console.error('Login error:', error);
        alert('❌ Lỗi đăng nhập, vui lòng thử lại!');
      }
    });
  }

  // Register form handler
  if (registerForm) {
    registerForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const tokenOk = await ensureCsrf(registerForm);
      
      if (!tokenOk) {
        alert('⚠️ Lỗi bảo mật, vui lòng thử lại!');
        return;
      }

      const name = document.getElementById('regName').value;
      const email = document.getElementById('regEmail').value;
      const password = document.getElementById('regPassword').value;
      const password2 = document.getElementById('regPassword2').value;
      const phone = document.getElementById('regPhone').value;

      if (password !== password2) {
        alert('❌ Mật khẩu không trùng khớp!');
        return;
      }

      try {
        const formData = new FormData(registerForm);
        const response = await fetch('/register', {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          const session = { name, email, phone };
          localStorage.setItem('userSession', JSON.stringify(session));
          alert('✅ Đăng ký thành công!');
          window.location.href = 'dashboard.html';
        } else {
          alert('❌ Email đã tồn tại hoặc có lỗi!');
        }
      } catch (error) {
        console.error('Register error:', error);
        alert('❌ Lỗi đăng ký, vui lòng thử lại!');
      }
    });
  }

  // Profile form handler
  if (profileForm) {
    profileForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const tokenOk = await ensureCsrf(profileForm);
      
      if (!tokenOk) {
        alert('⚠️ Lỗi bảo mật, vui lòng thử lại!');
        return;
      }

      const name = document.getElementById('profileName').value;
      const email = document.getElementById('profileEmail').value;
      const phone = document.getElementById('profilePhone').value;
      
      const session = {
        name: name,
        email: email,
        phone: phone
      };
      
      try {
        // Save to localStorage for now (client-side)
        localStorage.setItem('userSession', JSON.stringify(session));
        alert('✅ Cập nhật thành công!');
      } catch (error) {
        console.error('Profile update error:', error);
        alert('❌ Lỗi cập nhật, vui lòng thử lại!');
      }
    });
  }
});
