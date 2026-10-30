# 🍔 SmartMenu SaaS — Sistema QR para Restaurantes

Plataforma web **Multi-tenant (SaaS)** y **API-First** diseñada para la gestión integral de restaurantes, bares y cafeterías: cartas digitales interactivas por código QR, toma de pedidos por mesa en tiempo real, panel de cocina con **Server-Sent Events (SSE)**, cuentas acumuladas por mesa, diseño de carteles QR imprimibles, control de caja y exportación contable.

---

## ✨ Características Principales

### 📱 Experiencia del Cliente (Carta Digital QR)
- **Acceso instantáneo sin descargas:** Escaneo de QR único por mesa (`/m/{hash_qr}` o `/carta/{alias}/{hash_qr}`) o acceso de solo lectura mediante enlace corto (`/carta/{alias}`).
- **Carrito interactivo y rondas de pedido:** Los comensales pueden enviar múltiples rondas de pedidos a cocina bajo una misma cuenta abierta en su mesa.
- **Estado de cuenta en vivo y "Pedir la Cuenta":** Visualización del consumo acumulado de la mesa (incluyendo cargo por servicio de mesa/cubierto si aplica) y botón para solicitar la cuenta al personal con alerta sonora y visual en el panel.
- **Sincronización de stock:** Los productos pausados desde el panel administrativo se bloquean inmediatamente en la carta digital.

### 🖥️ Panel Administrativo y Cocina (Tiempo Real)
- **Monitor de Cocina y Salón en Tiempo Real:** Impulsado por **SSE (`sse_pedidos.php`)** con fallback automático a REST (`api_pedidos.php`), alertas sonoras y notificaciones visuales sin recargar la página.
- **Flujo de Comandas y Mozos:**
  - Estados de pedido: `pendiente` ➔ `preparando` ➔ `servido` / `cancelado`.
  - Registro del **nombre del mozo** que toma o acepta el pedido.
  - Emisión de **tickets térmicos (58mm / 80mm)** listos para impresión (`admin/ticket.php`).
- **Cuentas Abiertas por Mesa:** Agrupación automática de todas las rondas pedidas por una mesa, alerta destacada cuando el cliente solicita la cuenta y cierre individual de mesa al cobrar.
- **Gestión de Carta Digital (`admin/menu.php`):**
  - Alta, edición y baja de categorías y productos.
  - Subida segura de imágenes de productos (JPG, PNG, WEBP hasta 2MB con validación MIME binaria).
  - Interruptor rápido de disponibilidad (*En Stock / Pausado*) mediante AJAX.
- **Gestión de Mesas y Diseñador QR (`admin/mesas.php` & `admin/disenar_qr.php`):**
  - Generación automática de hashes criptográficos únicos por mesa.
  - Generador de códigos QR (`endroid/qr-code`) y estudio de diseño de carteles QR personalizables listos para imprimir.
- **Arqueo y Control de Caja (`admin/caja.php`):**
  - Resumen de ventas del turno actual (efectivo/recaudación de pedidos servidos no cerrados).
  - Cierre de caja transaccional con historial de cierres anteriores.
- **Historial y Reportes (`admin/historial.php` & `admin/exportar_excel.php`):**
  - Búsqueda avanzada de pedidos por rango de fechas, estado y mesa con paginación.
  - Exportación directa a **Excel (`.xls`)** con desglose de ventas.
- **Configuración del Local (`admin/configuracion.php`):**
  - Personalización de nombre comercial, alias para URL amigable, teléfono, dirección y logo.
  - Configuración opcional de **cobro por servicio de mesa** (monto fijo por mesa/apertura).
  - Generación de **API Token (`Bearer`)** para integración con aplicaciones móviles nativas (Android / Retrofit).

---

## 🛠️ Stack Tecnológico

| Capa | Tecnología |
| :--- | :--- |
| **Backend** | PHP 8.0+ (PDO, transacciones ACID, SSE, sesiones y autenticación híbrida Bearer/Session) |
| **Base de Datos** | MySQL / MariaDB (`utf8mb4`, diseño relacional Multi-tenant con índices compuestos) |
| **Dependencias** | Composer — [`endroid/qr-code ^4.6`](https://github.com/endroid/qr-code) |
| **Frontend** | HTML5, CSS3 (Tema *Dark Mode / Cyberpunk* con *Glassmorphism*), JavaScript Vanilla (`fetch`, `EventSource`) |
| **Servidor Web** | Apache 2.4+ (`mod_rewrite`, `mod_headers` configurados en `.htaccess`) |

---

## 📂 Estructura del Proyecto

```text
resto/
├── admin/                          # Panel Administrativo, Cocina y Endpoints API
│   ├── actualizar_pedido.php       # Cambia estado de pedidos, asigna mozo o cierra cuenta de mesa
│   ├── api_historial.php           # Endpoint JSON con filtros y paginación de pedidos históricos
│   ├── api_pedidos.php             # Endpoint REST JSON de comandas activas y cuentas por mesa
│   ├── caja.php                    # Vista de control y arqueo de caja por turno
│   ├── conexion.php                # Conexión PDO centralizada a MySQL
│   ├── configuracion.php           # Ajustes del restaurante, servicio de mesa y API Token
│   ├── dashboard.php               # Métricas principales, accesos rápidos y estado del local
│   ├── disenar_qr.php              # Diseñador visual de plantillas QR para imprimir
│   ├── exportar_excel.php          # Generador de reportes descargables en formato Excel
│   ├── generar_qr.php              # Renderizado de imagen PNG del código QR de cada mesa
│   ├── historial.php               # Vista de historial de pedidos y facturación
│   ├── layout.php                  # Barra de navegación lateral y estructura común del panel
│   ├── login.php                   # Autenticación (Web por sesión y API móvil con token)
│   ├── logout.php                  # Cierre de sesión seguro
│   ├── menu.php                    # ABM de categorías, productos y control de stock
│   ├── mesas.php                   # ABM de mesas y acceso a códigos QR
│   ├── pedidos.php                 # Monitor de cocina y salón en tiempo real
│   ├── procesar_categoria.php      # Creación y eliminación de categorías
│   ├── procesar_cierre.php         # Ejecución transaccional del cierre de caja
│   ├── procesar_configuracion.php  # Guardado de perfil, logo, alias y servicio de mesa
│   ├── procesar_mesa.php           # Alta y eliminación de mesas con generación de hash QR
│   ├── procesar_producto.php       # Alta, edición y baja de productos con subida de imagen
│   ├── registro.php                # Registro de nuevos restaurantes en la plataforma SaaS
│   ├── seguridad.php               # Helpers de autenticación, Rate Limiting, validación MIME y consultas
│   ├── sse_pedidos.php             # Stream Server-Sent Events para actualizaciones en tiempo real
│   ├── ticket.php                  # Vista de comanda/ticket optimizada para impresoras térmicas
│   └── toggle_stock.php            # Endpoint AJAX para pausar/activar productos al instante
├── cliente/                        # Experiencia del Comensal (Carta Digital)
│   ├── menu.php                    # Carta interactiva, carrito y estado de cuenta de la mesa
│   ├── pedir_cuenta.php            # Endpoint para notificar solicitud de cuenta al salón
│   └── procesar_pedido_cliente.php # Recepción transaccional de pedidos desde la mesa
├── uploads/                        # Almacenamiento de imágenes (protegido contra ejecución PHP)
│   ├── .htaccess                   # Bloqueo de scripts ejecutables en el directorio de subidas
│   ├── logos/                      # Logos de los restaurantes registrados
│   └── productos/                  # Fotografías de los platos y bebidas
├── vendor/                         # Dependencias de Composer (endroid/qr-code)
├── .htaccess                       # Reglas de seguridad HTTP y URLs amigables (Apache)
├── bd.sql                          # Esquema completo de base de datos, índices y datos demo
├── composer.json                   # Definición de dependencias PHP
└── index.php                       # Landing Page comercial + Modal de Login/Registro SaaS
```

---

## 🚀 Instalación y Puesta en Marcha

### 1. Requisitos Previos
- **PHP >= 8.0** con las extensiones `pdo_mysql`, `gd`, `fileinfo` y `mbstring` habilitadas.
- **MySQL 5.7+ / MariaDB 10.4+**.
- **Apache 2.4+** con los módulos `mod_rewrite` y `mod_headers` activos (ej. WAMP, XAMPP, Laragon).
- **Composer** (opcional si ya se incluye la carpeta `vendor/`).

### 2. Clonar el Repositorio
Ubica el proyecto dentro del directorio público de tu servidor web (por ejemplo `C:\wamp64\www\resto` o `/var/www/html/resto`):

```bash
git clone https://github.com/JoelGaleano/SistemaQRRestaurante.git resto
cd resto
```

### 3. Instalar Dependencias (Opcional)
El repositorio ya incluye las dependencias necesarias en `vendor/`. Si deseas reinstalarlas o actualizarlas con Composer:

```bash
composer install
```

### 4. Importar la Base de Datos
1. Abre **phpMyAdmin**, **HeidiSQL** o tu consola de MySQL.
2. Ejecuta el script completo ubicado en `bd.sql`:
   ```bash
   mysql -u root -p < bd.sql
   ```
   Esto creará automáticamente la base de datos `plataforma_restaurantes`, todas las tablas, los índices de alto rendimiento y un restaurante de demostración.

### 5. Configurar la Conexión
Verifica las credenciales de base de datos en `admin/conexion.php`:

```php
$host = 'localhost';
$dbname = 'plataforma_restaurantes';
$username = 'root';
$password = '';
```

### 6. Permisos de Carpetas
Asegúrate de que el servidor web tenga permisos de escritura en la carpeta `uploads/` (y sus subcarpetas `uploads/logos` y `uploads/productos`).

---

## 🔑 Credenciales de Prueba (Demo)

El archivo `bd.sql` incluye un restaurante preconfigurado para probar todas las funciones de inmediato:

- **URL de Inicio / Login:** `http://localhost/resto/`
- **Correo electrónico:** `admin@burgerjoe.com`
- **Contraseña:** `123456`
- **Mesas de prueba:**
  - Mesa 1 (QR): `http://localhost/resto/m/hash_prueba_mesa_1`
  - Mesa 2 (QR): `http://localhost/resto/m/hash_prueba_mesa_2`
  - Carta pública (solo lectura): `http://localhost/resto/carta/burger-joe`

---

## 🌐 Rutas y URLs Amigables (`.htaccess`)

El motor de reescritura de Apache proporciona rutas limpias tanto para clientes como para administradores:

| Ruta Amigable | Archivo Destino | Descripción |
| :--- | :--- | :--- |
| `/m/{hash_qr}` | `cliente/menu.php?m={hash_qr}` | Acceso directo al menú de una mesa para pedir |
| `/carta/{alias}/{hash_qr}` | `cliente/menu.php?alias={alias}&m={hash_qr}` | Acceso por alias del local + hash de mesa |
| `/carta/{alias}` | `cliente/menu.php?alias={alias}` | Carta digital en modo vista (sin mesa asignada) |
| `/login` | `index.php#acceso` | Acceso rápido al formulario de inicio de sesión |
| `/admin/dashboard` | `admin/dashboard.php` | Cualquier vista del panel sin extensión `.php` |
| `/admin/salir` | `admin/logout.php` | Cierre de sesión inmediato |

---

## 🔒 Arquitectura de Seguridad

1. **Aislamiento Multi-Tenant Estricto:** Todas las consultas SQL (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) filtran obligatoriamente por `id_restaurante` para garantizar que ningún comercio acceda a datos de otro.
2. **Prevención de Inyección SQL:** Uso exclusivo de sentencias preparadas con `PDO::prepare()`.
3. **Rate Limiting Integrado:** Tabla `api_rate_limits` y función `verificarRateLimit()` para mitigar ataques de fuerza bruta y spam de pedidos por IP y endpoint.
4. **Subida Segura de Archivos (`subirImagenSegura`):**
   - Límite estricto de 2 MB por imagen.
   - Inspección del tipo MIME real mediante `finfo(FILEINFO_MIME_TYPE)` y validación de dimensiones con `getimagesize()`.
   - Renombrado aleatorio (`bin2hex(random_bytes(6))`) y bloqueo de ejecución de scripts en `uploads/.htaccess`.
5. **Protección de Archivos Sensibles:** El `.htaccess` raíz bloquea el acceso público directo a `bd.sql`, `composer.json`, `conexion.php`, `seguridad.php`, `layout.php` y `vendor/`.
6. **Autenticación Híbrida (Web + API Móvil):** Soporte nativo tanto para sesiones PHP tradicionales como para cabeceras `Authorization: Bearer <token>` o `X-API-Key` orientadas a clientes móviles (Android / Retrofit).

---

## 📄 Licencia

Este proyecto fue desarrollado como solución integral de gestión gastronómica por QR. Puedes adaptarlo y extenderlo según las necesidades de tu negocio.
