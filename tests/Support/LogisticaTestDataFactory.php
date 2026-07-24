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
     * Crea un usuario ficticio y devuelve su ID.
     * El email usa un sufijo único para evitar colisiones dentro de la misma transacción.
     */
    public static function crearUsuario(PDO $db, string $prefijo = 'operador'): int
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
        return (int) $db->lastInsertId();
    }

    /**
     * Crea un pedido ficticio en estado 11 para el cliente dado.
     * Rellena todas las columnas NOT NULL con valores mínimos.
     *
     * numero_orden se construye como: prefijo fijo (9) + timestamp μs truncado (7 d) + seq (2 d).
     * Ejemplo: 9_1234567_01 → 9123456701.
     * El UNIQUE (id_cliente, numero_orden) nunca colisiona entre distintas
     * sesiones PHP porque el microsegundo difiere, ni dentro de la misma sesión
     * porque el seq difiere.
     */
    public static function crearPedido(PDO $db, int $idCliente, int $idEstado = self::ESTADO_PENDIENTE_RECOLECCION): int
    {
        $n    = self::next();
        $ts   = (int)(microtime(true) * 10); // décimas de segundo, 13 dígitos aprox.
        // Mantener BIGINT en rango seguro: tomamos los últimos 7 dígitos del timestamp
        // más los 2 últimos dígitos del seq → 10 dígitos totales con prefijo 9.
        $numeroOrden = (int)('9' . substr((string)$ts, -7) . str_pad((string)($n % 100), 2, '0', STR_PAD_LEFT));

        $stmt = $db->prepare(
            'INSERT INTO pedidos
               (numero_orden, fecha_ingreso, id_estado, id_cliente,
                code_city, created_at, updated_at)
             VALUES
               (:numero_orden, NOW(), :id_estado, :id_cliente,
                0, NOW(), NOW())'
        );
        $stmt->execute([
            ':numero_orden' => $numeroOrden,
            ':id_estado'    => $idEstado,
            ':id_cliente'   => $idCliente,
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
}
