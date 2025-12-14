// service-worker.js
const CACHE_NAME = "ischo2-cache-v1";
const urlsToCache = [
  "/",
  "/home.php",
  "/admindashboard.php",
  "/applicantdashboard.php",
  "/superadmindashboard.php",
  "/about_us.php",
  "/announcements.php",
  "/login.php",
  "/forgotpassword.php",
  "/resetpassword.php",
  "/navbar.php",
  "/adminstyles.css",
  "/applicantstyles.css",
  // Add more static assets as needed
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    })
  );
});