import { initializeApp } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

const firebaseConfig = {
    apiKey: "AIzaSyC0MCW0mOdsUucR4K0FrMXjr_SmCDWWrLU",
    authDomain: "lougarou.firebaseapp.com",
    projectId: "lougarou",
    storageBucket: "lougarou.firebasestorage.app",
    messagingSenderId: "356481784155",
    appId: "1:356481784155:web:8b370ae8a005cc7053227e",
};

const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
