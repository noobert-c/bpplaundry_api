/* Interaksi ringan untuk LaundryFlow */
document.addEventListener('DOMContentLoaded', function () {
  // Accordion pesanan
  document.querySelectorAll('.acc__btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (panel) panel.hidden = open;
    });
  });

  // Kirim OTP (simulasi) dengan hitung mundur
  var otpBtn = document.getElementById('kirim-otp');
  if (otpBtn) {
    otpBtn.addEventListener('click', function () {
      var s = 30;
      otpBtn.disabled = true;
      var t = setInterval(function () {
        otpBtn.textContent = 'Kirim ulang ' + s + 'd';
        if (s-- <= 0) { clearInterval(t); otpBtn.disabled = false; otpBtn.textContent = 'Kirim OTP'; }
      }, 1000);
      otpBtn.textContent = 'Kirim ulang 30d';
    });
  }
});