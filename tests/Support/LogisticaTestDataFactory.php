<?php

declare(strict_types=1);

/**
 * LogisticaTestDataFactory
 *
 * Crea datos ficticios mínimos para pruebas de integración de Logística Operativa.
 *
 * Reglas:
 * - Solo inserta en paquetes_apppack_test (la conexión PDO ya fue validada).
 * - Usa identificadores reconocibles: test-logistica-*, TEST-COLECTA-*.
 * - Sin emails, nombres, teléfonos ni contraseñas reales.
 * - Sin datos de producción.
 * - Las pruebas usan transacción + rollback → los datos desaparecen automáticamente.
 *
 * Inspecciona columnas obligatorias reales:
 *   usuarios: id, nombre (NOT NULL), email (UNIQUE NOT NULL), contrasena (NOT NULL)
 *   pedidos:  id, numero_orden (BIGINT NOT NULL), fecha_ingreso (NOT NULL DEFAULT NOW),
 *             id_estado, id_cliente, code_city (NOT NULL DEFAULT 0)
 */
class LogisticaTestDataFactory
{
    // Estado 11 = "Pendiente recolección por mensajería"
    public const ESTADO_PENDIENTE_RECOLECCION = 11;

    private static int $seq = 0;

    private static function next(): int
    {
        return ++self::$seq;
    }

    /**
     * Crea un usuario ficticio y le asigna un rol en usuarios_roles. Devuelve su ID.
     */
    public static function crearUsuario(PDO $db, string $prefijo = 'operador', ?int $idRol = null): int
    {
        $n     = self::next();
        $ts    = microtime(true);
        $email = "test-logistica-{$prefijo}-{$n}-{$ts}@test.invalid";
        $nombre = "TEST-LOGISTICA-{$prefijo}-{$n}";

        $stmt = $db->prepare(
            'INSERT INTO usuarios (nombre, email, contrasena, activo, created_at)
             VALUES (:nombre, :email, :contrasena, 1, NOW())'
        );
        $stmt->execute([
            ':nombre'    => $nombre,
            ':email'     => $email,
            ':contrasena' => '$2y$10$invalidhashfortest000000000000000000000000000000000000000',
        ]);
        $idUsuario = (int) $db->lastInsertId();

        // Determinar rol por omisión según el prefijo
        if ($idRol === null) {
            if (str_contains(strtolower($prefijo), 'cli')) {
                $idRol = 4;
            } else {
                $idRol = 5;
            }
        }

        if ($idRol > 0) {
            $stmtRol = $db->prepare(
                'INSERT INTO usuarios_roles (id_usuario, id_rol) VALUES (:uid, :rid)'
            );
            $stmtRol->execute([':uid' => $idUsuario, ':rid' => $idRol]);
        }

        return $idUsuario;
    }

    /**
     * Crea un pedido ficticio en estado 11 para el cliente y proveedor dados.
     */
    public static function crearPedido(PDO $db, int $idCliente, int $idProveedor = 0, int $idEstado = self::ESTADO_PENDIENTE_RECOLECCION): int
    {
        $n    = self::next();
        $ts   = (int)(microtime(true) * 10);
        $numeroOrden = (int)('9' . substr((string)$ts, -7) . str_pad((string)($n % 100), 2, '0', STR_PAD_LEFT));

        if ($idProveedor <= 0) {
            $idProveedor = self::crearUsuario($db, 'proveedor', 5);
        }

        $stmt = $db->prepare(
            'INSERT INTO pedidos
               (numero_orden, fecha_ingreso, id_estado, id_cliente, id_proveedor,
                code_city, created_at, updated_at)
             VALUES
               (:numero_orden, NOW(), :id_estado, :id_cliente, :id_proveedor,
                0, NOW(), NOW())'
        );
        $stmt->execute([
            ':numero_orden' => $numeroOrden,
            ':id_estado'    => $idEstado,
            ':id_cliente'   => $idCliente,
            ':id_proveedor' => $idProveedor,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Genera un UUID v4 aleatorio.
     */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Genera un qr_hash SHA-256 ficticio (64 hex).
     */
    public static function qrHash(string $contenido = ''): string
    {
        return hash('sha256', $contenido ?: uniqid('qr-test-', true));
    }

    /** Resetea el contador de secuencia (útil si se necesita aislamiento). */
    public static function resetSeq(): void
    {
        self::$seq = 0;
    }

    /**
     * Crea una bodega ficticia en paquetes_apppack_test y devuelve su ID.
     *
     * Los códigos son únicos y resistentes a ejecuciones repetidas:
     * usan microsegundo + secuencia interna para evitar colisiones UNIQUE.
     *
     * @param PDO   $db       Conexión PDO a la base de pruebas.
     * @param array $override Columnas a sobreescribir sobre los valores por defecto.
     *                        Columnas válidas: codigo, nombre, tipo, activa, direccion.
     * @return int ID de la bodega recién creada.
     */
    public static function crearBodega(PDO $db, array $override = []): int
    {
        $n      = self::next();
        $ts     = (int)(microtime(true) * 1000); // milisegundos
        $codigo = $override['codigo'] ?? ('BOD-TEST-' . substr((string)$ts, -6) . '-' . $n);

        $stmt = $db->prepare(
            'INSERT INTO logistica_bodegas
               (codigo, nombre, tipo, activa, created_at, updated_at)
             VALUES
               (:codigo, :nombre, :tipo, :activa, NOW(), NOW())'
        );
        $stmt->execute([
            ':codigo'  => $codigo,
            ':nombre'  => $override['nombre']  ?? "Bodega de Prueba {$n}",
            ':tipo'    => $override['tipo']    ?? 'CENTRAL',
            ':activa'  => isset($override['activa']) ? (int) $override['activa'] : 1,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Crea una ubicación ficticia dentro de la bodega indicada y devuelve su ID.
     *
     * @param PDO   $db       Conexión PDO a la base de pruebas.
     * @param int   $idBodega ID de la bodega padre (debe existir en la misma conexión).
     * @param array $override Columnas a sobreescribir sobre los valores por defecto.
     *                        Columnas válidas: codigo, zona, pasillo, estante, cajon,
     *                        nivel, tipo, capacidad, activa.
     * @return int ID de la ubicación recién creada.
     */
    public static function crearUbicacion(PDO $db, int $idBodega, array $override = []): int
    {
        $n      = self::next();
        $ts     = (int)(microtime(true) * 1000);
        $codigo = $override['codigo'] ?? ('LOC-' . substr((string)$ts, -5) . '-' . $n);

        $stmt = $db->prepare(
            'INSERT INTO logistica_ubicaciones
               (id_bodega, codigo, zona, pasillo, estante, cajon,
                nivel, tipo, capacidad, activa, created_at, updated_at)
             VALUES
               (:id_bodega, :codigo, :zona, :pasillo, :estante, :cajon,
                :nivel, :tipo, :capacidad, :activa, NOW(), NOW())'
        );
        $stmt->execute([
            ':id_bodega' => $idBodega,
            ':codigo'    => $codigo,
            ':zona'      => $override['zona']      ?? null,
            ':pasillo'   => $override['pasillo']   ?? null,
            ':estante'   => $override['estante']   ?? null,
            ':cajon'     => $override['cajon']     ?? null,
            ':nivel'     => $override['nivel']     ?? null,
            ':tipo'      => $override['tipo']      ?? 'GENERAL',
            ':capacidad' => $override['capacidad'] ?? null,
            ':activa'    => isset($override['activa']) ? (int) $override['activa'] : 1,
        ]);
        return (int) $db->lastInsertId();
    }
}
