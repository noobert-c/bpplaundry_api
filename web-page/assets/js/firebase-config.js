// Konfigurasi Firebase (ES module via CDN, tanpa Node.js / bundler)
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js";
import { getAuth, GoogleAuthProvider } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";

export const firebaseConfig = {
  apiKey: "AIzaSyCxxjZ9LB6GK0SPGTazEUSehFeDGTnXGUs",
  authDomain: "bpplaundry.firebaseapp.com",
  projectId: "bpplaundry",
  storageBucket: "bpplaundry.firebasestorage.app",
  messagingSenderId: "439269277273",
  appId: "1:439269277273:web:7f2d000cafe4308580fb48",
  measurementId: "G-3RVL2MQ5R2"
};

const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
auth.languageCode = "id";

export const googleProvider = new GoogleAuthProvider();
googleProvider.setCustomParameters({ prompt: "select_account" });