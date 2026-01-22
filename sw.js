const CACHE_NAME = 'aquatiq-v1';
const OFFLINE_URL = '/login.php';

// Instalación: Cacheamos solo lo mínimo imprescindible para que la app se considere PWA
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll([OFFLINE_URL, '/favicon.png', '/manifest.json']);
      })
      .then(() => self.skipWaiting())
  );
});

// Activación: Limpieza inmediata
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          return caches.delete(cacheName);
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Estrategia: Network-only con fallback a cache solo si falla la red
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .catch(() => {
        // Solo si falla la red, buscamos en el cache
        return caches.match(event.request)
          .then((response) => {
            if (response) return response;
            
            // Si no hay nada en cache y es una navegación, mostramos login (como offline)
            if (event.request.mode === 'navigate') {
              return caches.match(OFFLINE_URL);
            }
          });
      })
  );
});
