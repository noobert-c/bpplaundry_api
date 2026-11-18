// Login Google via Firebase Auth, lalu kirim ID token ke session.php untuk diverifikasi server
import { signInWithPopup } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";
import { auth, googleProvider } from "./firebase-config.js";

const btn = document.getElementById("btn-google");
const label = btn.querySelector(".btn__label");
const errBox = document.getElementById("login-error");
const defaultLabel = label.textContent;

function setLoading(on) {
  btn.disabled = on;
  label.textContent = on ? "Menghubungkan…" : defaultLabel;
}
function showError(msg) {
  errBox.textContent = msg;
  errBox.hidden = !msg;
}

const pesan = {
  "auth/popup-blocked": "Popup diblokir browser. Izinkan popup untuk situs ini lalu coba lagi.",
  "auth/unauthorized-domain": "Domain ini belum diizinkan di Firebase (Authentication → Settings → Authorized domains).",
  "auth/operation-not-allowed": "Login Google belum diaktifkan di Firebase Authentication.",
  "auth/network-request-failed": "Jaringan bermasalah. Periksa koneksi lalu coba lagi."
};

btn.addEventListener("click", async () => {
  showError("");
  setLoading(true);
  try {
    const cred = await signInWithPopup(auth, googleProvider);
    const idToken = await cred.user.getIdToken();

    const res = await fetch("session.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ idToken })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || "Gagal membuat sesi.");

    window.location.href = "lengkapi-data.php";
  } catch (e) {
    if (e.code === "auth/popup-closed-by-user" || e.code === "auth/cancelled-popup-request") {
      setLoading(false);
      return;
    }
    showError(pesan[e.code] || e.message || "Masuk gagal. Coba lagi.");
    setLoading(false);
  }
});