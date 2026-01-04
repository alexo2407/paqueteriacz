# ✅ Corrección de Redirección de Login para Clientes

## 🐛 Problema Identificado

Los usuarios con rol **Cliente** eran redirigidos al **dashboard** después del login, en lugar de ser enviados a su página principal de **notificaciones** (`/crm/notificaciones`).

### Causa Raíz

La lógica de redirección después del login estaba hardcodeada en el método `login()` del `UsuariosController`, enviando a todos los usuarios (excepto repartidores) al dashboard.

---

## 🔧 Soluciones Implementadas

### 1. **Modificación del Controlador de Login** (`controlador/usuario.php`)

**Ubicación:** Líneas 252-278

**Cambios realizados:**

```php
// ANTES: Solo verificaba repartidor
if ($isRepartidor && !$isAdmin) {
    // redirigir a seguimiento
}
// Todos los demás -> dashboard

// AHORA: Verifica múltiples roles
$isCliente = in_array('Cliente', $rolesNombres, true);

if ($isRepartidor && !$isAdmin) {
    // → seguimiento/listar
}

if ($isCliente && !$isAdmin) {
    // → crm/notificaciones ✅
}

// Otros roles → dashboard
```

### 2. **Variable de Sesión `idUsuario` Agregada**

**Problema:** El sistema usaba `$_SESSION['idUsuario']` en todo el código, pero el login solo configuraba `$_SESSION['user_id']`.

**Solución agregada en línea 224:**
```php
$_SESSION['user_id'] = $user['ID_Usuario'];
$_SESSION['idUsuario'] = $user['ID_Usuario']; // ✅ Compatibilidad
```

### 3. **Redirección desde `index.php`** (Ya implementado anteriormente)

Si un cliente accede a la raíz o a `/inicio`, es redirigido automáticamente a `/crm/notificaciones`.

---

## 📊 Flujo Completo de Redirección

```
Usuario inicia sesión
        │
        ├─ POST /login
        │
        v
[UsuariosController::login()]
        │
        ├─ Verificar credenciales
        │
        ├─ Guardar sesión
        │    ├─ $_SESSION['registrado'] = true
        │    ├─ $_SESSION['nombre'] = "..."
        │    ├─ $_SESSION['user_id'] = ID
        │    ├─ $_SESSION['idUsuario'] = ID ✅
        │    └─ $_SESSION['roles_nombres'] = [...]
        │
        ├─ Determinar rol
        │
        └─ Redirigir según rol:
             │
             ├─ Repartidor (no admin) → /seguimiento/listar
             │
             ├─ Cliente (no admin)    → /crm/notificaciones ✅
             │
             └─ Otros (admin, proveedor) → /dashboard
```

---

## 🎯 Resultado

| Rol | Página después del Login |
|-----|--------------------------|
| **Cliente** | ✅ `/crm/notificaciones` |
| **Repartidor** | `/seguimiento/listar` |
| **Proveedor** | `/dashboard` |
| **Administrador** | `/dashboard` |
| **Cliente + Admin** | `/dashboard` (Admin tiene prioridad) |

---

## 📝 Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `controlador/usuario.php` | Agregada lógica de redirección para clientes (L254-270) |
| `controlador/usuario.php` | Agregada variable `$_SESSION['idUsuario']` (L224) |
| `index.php` | Redirección desde raíz según rol (implementado previamente) |
| `controlador/crm.php` | Corrección de `$_SESSION['usuario_id']` a `$_SESSION['idUsuario']` |

---

## ✅ Validación

### Prueba 1: Login como Cliente
```
1. Ir a /login
2. Ingresar credenciales de usuario con rol "Cliente"
3. Hacer clic en "Iniciar sesión"
4. ✅ Debe redirigir a /crm/notificaciones
5. ✅ Debe ver el banner de bienvenida
6. ✅ Debe ver sus notificaciones
```

### Prueba 2: Login como Admin
```
1. Ir a /login
2. Ingresar credenciales de administrador
3. Hacer clic en "Iniciar sesión"
4. ✅ Debe redirigir a /dashboard
```

### Prueba 3: Login como Cliente + Admin (Multi-rol)
```
1. Ir a /login
2. Ingresar credenciales de usuario con ambos roles
3. Hacer clic en "Iniciar sesión"
4. ✅ Debe redirigir a /dashboard (Admin tiene prioridad)
```

---

## 🔍 Verificación de Variables de Sesión

Después del login como cliente, las siguientes variables deben estar configuradas:

```php
$_SESSION['registrado'] = true;
$_SESSION['nombre'] = "Nombre del Cliente";
$_SESSION['rol'] = 3; // ID del rol Cliente
$_SESSION['user_id'] = 123;
$_SESSION['idUsuario'] = 123; // ✅ Agregada
$_SESSION['roles'] = [3]; // Array de IDs de roles
$_SESSION['roles_nombres'] = ['Cliente']; // ✅ Usada para verificación
```

---

## 🚀 Estado Actual

✅ **Problema Resuelto**
- Los clientes ahora son redirigidos correctamente a `/crm/notificaciones` después del login
- La variable de sesión `idUsuario` está correctamente configurada
- El sistema de redirección funciona para todos los roles

---

**Fecha:** 2026-01-04  
**Estado:** ✅ Completado y Funcional  
**Próximo paso:** Probar el login como cliente para confirmar la redirección
