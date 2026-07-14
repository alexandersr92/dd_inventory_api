# Guía de Integración Frontend: Módulos, Roles y Permisos (SaaS)

Esta guía documenta cómo consumir y aplicar el control de accesos por **módulos contratados** y **roles/permisos de usuario** en la aplicación frontend.

---

## 🔑 1. Autenticación y Carga de Privilegios

Cuando el usuario inicia sesión (`POST /api/v1/login`), o cuando se valida el token al cargar la app (`GET /api/v1/validateToken`), la API retorna el perfil del usuario autenticado. 

### Payload de Respuesta Recomendado
El objeto `user` incluye la información de su rol actual y las relaciones clave:
```json
{
  "id": "user-uuid-1234",
  "name": "Alex Sanchez",
  "email": "alex@tenant.com",
  "organization_id": "org-uuid-5678",
  "role_id": "role-uuid-9999",
  "roles": [
    {
      "uuid": "role-uuid-9999",
      "name": "Owner",
      "permissions": [
        { "name": "product.index", "display_name": "Listar Productos" },
        { "name": "product.store", "display_name": "Crear Producto" },
        { "name": "invoice.store", "display_name": "Crear Factura" }
      ]
    }
  ],
  "organization": {
    "id": "org-uuid-5678",
    "name": "Mi Tienda ERP",
    "modules": [
      { "slug": "products", "status": "active" },
      { "slug": "invoices", "status": "active" },
      { "slug": "settings", "status": "active" },
      { "slug": "purchases", "status": "inactive" }
    ]
  }
}
```

> [!TIP]
> **Estrategia en Frontend:** Al recibir este objeto, guarda la lista de `permissions` (ej. `['product.index', 'product.store']`) y la lista de `modules` activos en tu estado global (Vuex/Pinia, Redux, Context API) para realizar validaciones reactivas e instantáneas en la UI.

---

## 🧱 2. Control de Acceso a Módulos (Licencias SaaS)

Evita que los usuarios accedan a módulos que la organización del Tenant **no ha contratado**.

### A. Menú de Navegación Dinámico
Filtra las rutas del menú lateral/navegación principal comparándolas con los módulos activos del inquilino:

```javascript
// Ejemplo en JavaScript / Vue / React
const menuItems = [
  { label: 'Productos', path: '/products', module: 'products' },
  { label: 'Facturación', path: '/invoices', module: 'invoices' },
  { label: 'Compras', path: '/purchases', module: 'purchases' },
];

const visibleMenu = menuItems.filter(item => {
  const orgModules = authStore.user.organization.modules;
  const targetModule = orgModules.find(m => m.slug === item.module);
  return targetModule && targetModule.status === 'active';
});
```

### B. Manejo de Errores API (403 Forbidden)
Si un usuario intenta saltarse la interfaz y accede a una ruta deshabilitada mediante URL directa, el backend interceptará la petición con el middleware `module` y retornará un código HTTP `403 Forbidden`:

```json
{
  "message": "Your organization does not have access to the 'purchases' module. Please upgrade your subscription."
}
```

> [!WARNING]
> **Intercepción Global:** Configura un interceptor de Axios/Fetch para detectar respuestas `403` con este tipo de mensaje e introduce un modal o redirección a una vista de *"Actualiza tu Plan / Adquiere este Módulo"*.

---

## 🔐 3. Control de Permisos de Usuario (ACL)

Controla lo que un usuario puede hacer **dentro** de un módulo según su rol (ej: el cajero solo puede ver productos, el administrador puede crear y editar).

### A. Guardas de UI (Directivas / Helpers)
Crea una utilidad global `can(permissionName)` para evaluar de forma reactiva si el usuario posee un permiso:

```javascript
// helper.js
export function can(permission) {
  const permissions = authStore.user.roles[0]?.permissions || [];
  return permissions.some(p => p.name === permission);
}
```

#### Uso en Plantillas (Ejemplo Vue/React)
```html
<!-- Vue Directive/v-if -->
<button v-if="can('product.store')" @click="openCreateModal">
  Nuevo Producto
</button>

<!-- React Condicional -->
{ can('product.delete') && (
  <button onClick={() => deleteProduct(product.id)}>Eliminar</button>
)}
```

### B. Listado de Permisos Clave del Sistema
Usa este catálogo para mapear tus botones de acción:

| Permiso | Acción Asociada |
| :--- | :--- |
| `product.index` / `product.show` | Listar y Ver detalle de productos |
| `product.store` / `product.update` | Crear y Modificar productos / precios |
| `product.delete` | Eliminar físicamente un producto |
| `invoice.index` / `invoice.show` | Listar ventas, buscar facturas |
| `invoice.store` | Crear nueva venta (POS / Factura) |
| `invoice.delete` | Anular una factura emitida |
| `credit.index` / `credit.payment` | Consultar créditos de clientes / Registrar abonos |
| `user.index` / `user.store` | Ver personal / Crear nuevos usuarios en el panel |

---

## 🛠️ 4. API Endpoints para la Administración de Usuarios y Roles

Para crear pantallas de configuración de personal e inventario de roles:

### A. Gestión de Usuarios del Personal
* **Listar Usuarios del Tenant:** `GET /api/v1/users`
* **Crear Usuario de Empleado:** `POST /api/v1/users`
  ```json
  {
    "name": "Juan Perez",
    "email": "juan@miempresa.com",
    "password": "passwordSeguro123",
    "role_id": "role-uuid-cajero", // Opcional
    "stores": ["uuid-sucursal-1"]  // Opcional: Sucursales asociadas
  }
  ```
* **Actualizar Empleado:** `PUT /api/v1/users/{id}` (Para cambiar nombre, email, password, estado `active`/`inactive`).
* **Asignar Rol Específico:** `POST /api/v1/users/{id}/roles`
  ```json
  { "role_id": "role-uuid-gerente" }
  ```
* **Asociar Sucursales:** `POST /api/v1/users/{id}/stores`
  ```json
  { "stores": ["uuid-sucursal-1", "uuid-sucursal-2"] }
  ```
* **Eliminar Empleado:** `DELETE /api/v1/users/{id}`

### B. Gestión de Roles y Permisos Propios
* **Listar Roles del Tenant:** `GET /api/v1/roles` (Retorna los roles específicos creados por la organización).
* **Listar Permisos del Sistema:** `GET /api/v1/roles/permissions` (Catálogo completo de permisos disponibles para marcar con checkboxes en formularios de creación de roles).
* **Crear Nuevo Rol Personalizado:** `POST /api/v1/roles`
  ```json
  {
    "name": "Supervisor de Almacén",
    "permissions": [
      "product.index",
      "product.show",
      "inventory.index",
      "inventory.show",
      "inventory.update"
    ]
  }
  ```
* **Actualizar Rol (Modificar Permisos):** `PUT /api/v1/roles/{id}` (Sincroniza y actualiza la lista de permisos asignada al rol).
* **Eliminar Rol:** `DELETE /api/v1/roles/{id}`
