// src/services/axios.js
import axios from "axios";

const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || "http://localhost:8000/api",
});

// Add Authorization Bearer Token Automatically
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("access_token");

    // Optional Debug Log
    console.log("🔐 Sending token:", token?.slice(0, 20) + "...");

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// Optional: Log 401 errors for debugging
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      console.warn("❌ 401 Unauthorized:", error.config.url);
    }
    return Promise.reject(error);
  }
);

export default axiosInstance;
