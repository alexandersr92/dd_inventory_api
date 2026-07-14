# API: Cambiar Contraseña de Usuario

Permite al usuario autenticado cambiar su contraseña actual por una nueva.

* **Método HTTP:** `PUT`
* **URL:** `/api/v1/user/password`
* **Autenticación requerida:** Sí (Laravel Sanctum Bearer Token)

---

## Cabeceras (Headers)

```http
Authorization: Bearer <TOKEN_DE_SESION>
Accept: application/json
Content-Type: application/json
```

---

## Cuerpo de la Petición (Request Body - JSON)

| Campo | Tipo | Requerido | Descripción |
| :--- | :--- | :--- | :--- |
| `current_password` | String | Sí | La contraseña actual del usuario para verificar su identidad. |
| `password` | String | Sí | La nueva contraseña (mínimo 6 caracteres). |
| `password_confirmation` | String | Sí | Debe coincidir exactamente con el valor del campo `password`. |

### Ejemplo de Petición:
```json
{
  "current_password": "mi_clave_actual",
  "password": "mi_nueva_clave_123",
  "password_confirmation": "mi_nueva_clave_123"
}
```

---

## Respuestas del Servidor (Responses)

### 1. Éxito (`200 OK`)
Se retorna cuando la validación es correcta y la contraseña ha sido actualizada.

```json
{
  "message": "Contraseña actualizada correctamente."
}
```

### 2. Error de Credenciales o Validación (`422 Unprocessable Entity`)

* **Caso A: La contraseña actual no coincide**
  ```json
  {
    "message": "La contraseña actual no es correcta."
  }
  ```

* **Caso B: Fallo en las reglas de validación (ej. contraseña muy corta o no coinciden)**
  ```json
  {
    "message": "The password field confirmation does not match.",
    "errors": {
      "password": [
        "The password field confirmation does not match."
      ]
    }
  }
  ```

### 3. No Autorizado (`401 Unauthorized`)
Se retorna si no se envía el token o si es inválido/expirado.

```json
{
  "message": "Unauthenticated."
}
```
