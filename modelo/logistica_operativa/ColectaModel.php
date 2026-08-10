<?php

declare(strict_types=1);

/**
 * ColectaModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_colectas
 * y logistica_colecta_pedidos.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class ColectaModel
{
    public function __construct(private PDO $db) {}

    // ── Colectas ─────────────────────────────────────────────────────────

    /**
     * Inserta una nueva colecta y devuelve su ID.
     */
    public function insertar(
        int    $idCliente,
        int    $idProveedor,
        string $fecha,
        string $turno,
        int    $cantidadEsperada,
        int    $idAbiertaPor
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_colectas
               (id_cliente, id_proveedor, fecha, turno, estado,
                cantidad_esperada, cantidad_escaneada, cantidad_faltante,
                id_abierta_por, abierta_at, created_at, updated_at)
             VALUES
               (:id_cliente, :id_proveedor, :fecha, :turno, \'ABIERTA\',
                :cantidad_esperada, 0, 0,
                :id_abierta_por, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            ':id_cliente'        => $idCliente,
            ':id_proveedor'      => $idProveedor,
            ':fecha'             => $fecha,
            ':turno'             => $turno,
            ':cantidad_esperada' => $cantidadEsperada,
            ':id_abierta_por'    => $idAbiertaPor,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca una colecta por cliente, proveedor, fecha y turno (para detectar duplicados).
     */
    public function buscarPorClienteProveedorFechaTurno(int $idCliente, int $idProveedor, string $fecha, string $turno): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_colectas
              WHERE id_cliente = :id_cliente
                AND id_proveedor = :id_proveedor
                AND fecha = :fecha
                AND turno = :turno
              LIMIT 1'
        );
        $stmt->execute([
            ':id_cliente'   => $idCliente,
            ':id_proveedor' => $idProveedor,
            ':fecha'        => $fecha,
            ':turno'        => $turno,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Método de compatibilidad legado para buscarPorClienteFechaTurno.
     */
    public function buscarPorClienteFechaTurno(int $idCliente, string $fecha, string $turno): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_colectas
              WHERE id_cliente = :id_cliente
                AND fecha = :fecha
                AND turno = :turno
              LIMIT 1'
        );
        $stmt->execute([':id_cliente' => $idCliente, ':fecha' => $fecha, ':turno' => $turno]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Obtiene una colecta por ID, con bloqueo FOR UPDATE opcional.
     */
    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("
            SELECT lc.*, 
                   uc.nombre AS cliente_nombre, 
                   up.nombre AS proveedor_nombre,
                   uo.nombre AS operador_nombre
              FROM logistica_colectas lc
         LEFT JOIN usuarios uc ON uc.id = lc.id_cliente
         LEFT JOIN usuarios up ON up.id = lc.id_proveedor
         LEFT JOIN usuarios uo ON uo.id = lc.id_abierta_por
             WHERE lc.id = :id LIMIT 1{$lock}
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Actualiza los contadores de una colecta recalculándolos desde los registros reales.
     */
    public function recalcularContadores(int $idColecta): void
    {
        $stmt = $this->db->prepare(
            'UPDATE logistica_colectas
                SET cantidad_escaneada = (
                        SELECT COUNT(*) FROM logistica_colecta_pedidos
                        WHERE id_colecta = :id1 AND resultado = \'RECIBIDO\'
                    ),
                    cantidad_faltante  = (
                        SELECT COUNT(*) FROM logistica_colecta_pedidos
                        WHERE id_colecta = :id2 AND resultado = \'FALTANTE\'
                    ),
                    cantidad_esperada  = (
                        SELECT COUNT(*) FROM logistica_colecta_pedidos
                        WHERE id_colecta = :id3 AND resultado IN (\'ESPERADO\',\'RECIBIDO\',\'FALTANTE\')
                    ),
                    updated_at = NOW()
              WHERE id = :id4'
        );
        $stmt->execute([
            ':id1' => $idColecta,
            ':id2' => $idColecta,
            ':id3' => $idColecta,
            ':id4' => $idColecta,
        ]);
    }

    /**
     * Cierra y concilia la colecta: marca faltantes, registra operador y timestamps.
     */
    public function cerrar(int $idColecta, int $idCerradaPor): void
    {
        // Marcar como FALTANTE los pedidos que siguen ESPERADO
        $stmt = $this->db->prepare(
            'UPDATE logistica_colecta_pedidos
                SET resultado   = \'FALTANTE\',
                    updated_at  = NOW()
              WHERE id_colecta  = :id_colecta
                AND resultado   = \'ESPERADO\''
        );
        $stmt->execute([':id_colecta' => $idColecta]);

        // Recalcular contadores desde los registros reales
        $this->recalcularContadores($idColecta);

        // Cambiar estado a CONCILIADA
        $stmt = $this->db->prepare(
            'UPDATE logistica_colectas
                SET estado         = \'CONCILIADA\',
                    id_cerrada_por = :id_cerrada_por,
                    cerrada_at     = NOW(),
                    updated_at     = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':id_cerrada_por' => $idCerradaPor, ':id' => $idColecta]);
    }

    // ── Colecta–Pedidos ───────────────────────────────────────────────────

    /**
     * Inserta un pedido esperado en la colecta.
     */
    public function insertarPedidoEsperado(int $idColecta, int $idPedido): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_colecta_pedidos
               (id_colecta, id_pedido, resultado, created_at, updated_at)
             VALUES
               (:id_colecta, :id_pedido, \'ESPERADO\', NOW(), NOW())'
        );
        $stmt->execute([':id_colecta' => $idColecta, ':id_pedido' => $idPedido]);
    }

    /**
     * Obtiene el registro de un pedido dentro de una colecta.
     */
    public function obtenerPedidoEnColecta(int $idColecta, int $idPedido): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_colecta_pedidos
              WHERE id_colecta = :id_colecta
                AND id_pedido  = :id_pedido
              LIMIT 1'
        );
        $stmt->execute([':id_colecta' => $idColecta, ':id_pedido' => $idPedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Actualiza el resultado de un pedido en la colecta.
     */
    public function actualizarResultadoPedido(
        int    $idColecta,
        int    $idPedido,
        string $resultado,
        string $escaneadoAt
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE logistica_colecta_pedidos
                SET resultado    = :resultado,
                    escaneado_at = :escaneado_at,
                    updated_at   = NOW()
              WHERE id_colecta  = :id_colecta
                AND id_pedido   = :id_pedido'
        );
        $stmt->execute([
            ':resultado'    => $resultado,
            ':escaneado_at' => $escaneadoAt,
            ':id_colecta'   => $idColecta,
            ':id_pedido'    => $idPedido,
        ]);
    }

    /**
     * Inserta un pedido EXTRA (no estaba en los esperados).
     */
    public function insertarPedidoExtra(int $idColecta, int $idPedido, string $escaneadoAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_colecta_pedidos
               (id_colecta, id_pedido, resultado, escaneado_at, created_at, updated_at)
             VALUES
               (:id_colecta, :id_pedido, \'EXTRA\', :escaneado_at, NOW(), NOW())'
        );
        $stmt->execute([
            ':id_colecta'   => $idColecta,
            ':id_pedido'    => $idPedido,
            ':escaneado_at' => $escaneadoAt,
        ]);
    }

    /**
     * Elimina un pedido con resultado EXTRA de una colecta.
     */
    public function eliminarPedidoExtra(int $idColecta, int $idPedido): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM logistica_colecta_pedidos
              WHERE id_colecta = :id_colecta
                AND id_pedido  = :id_pedido
                AND resultado  = 'EXTRA'"
        );
        $stmt->execute([':id_colecta' => $idColecta, ':id_pedido' => $idPedido]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene los pedidos elegibles (estado 11) de un cliente y proveedor específicos,
     * excluyendo los ya RECIBIDO en colectas anteriores no canceladas.
     *
     * @return array<int> Lista de IDs de pedido
     */
    public function obtenerPedidosElegibles(int $idCliente, ?int $idProveedor = null): array
    {
        // Estado 11 = "Pendiente recolección por mensajería"
        $whereProv = ($idProveedor !== null && $idProveedor > 0) ? ' AND p.id_proveedor = :id_proveedor ' : '';

        $stmt = $this->db->prepare(
            "SELECT p.id
               FROM pedidos p
              WHERE p.id_cliente = :id_cliente
                {$whereProv}
                AND p.id_estado  = 11
                AND NOT EXISTS (
                    SELECT 1
                      FROM logistica_colecta_pedidos cp
                      JOIN logistica_colectas c ON c.id = cp.id_colecta
                     WHERE cp.id_pedido  = p.id
                       AND cp.resultado  = 'RECIBIDO'
                       AND c.estado     != 'CANCELADA'
                )"
        );
        $params = [':id_cliente' => $idCliente];
        if ($idProveedor !== null && $idProveedor > 0) {
            $params[':id_proveedor'] = $idProveedor;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Obtiene un resumen completo de la colecta con conteos por resultado.
     */
    public function obtenerResumen(int $idColecta): array
    {
        $colecta = $this->obtenerPorId($idColecta);
        if ($colecta === null) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT resultado, COUNT(*) AS cantidad
               FROM logistica_colecta_pedidos
              WHERE id_colecta = :id_colecta
              GROUP BY resultado'
        );
        $stmt->execute([':id_colecta' => $idColecta]);
        $conteos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $conteos[$row['resultado']] = (int) $row['cantidad'];
        }

        return [
            'colecta' => $colecta,
            'conteos' => [
                'ESPERADO'  => $conteos['ESPERADO']  ?? 0,
                'RECIBIDO'  => $conteos['RECIBIDO']  ?? 0,
                'FALTANTE'  => $conteos['FALTANTE']  ?? 0,
                'EXTRA'     => $conteos['EXTRA']      ?? 0,
            ],
        ];
    }

    // ── Listado con filtros (para la vista interna) ───────────────────────

    /**
     * Lista colectas con JOIN a usuarios (cliente / proveedor / operador) y filtros opcionales.
     *
     * @param array{fecha?:string|null, turno?:string|null, estado?:string|null, cliente?:string|null, id_proveedor?:int|null, id_cliente?:int|null} $filtros
     * @return array<int,array>
     */
    public function listarConFiltros(array $filtros = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtros['fecha'])) {
            $where[]  = 'lc.fecha = :fecha';
            $params[':fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['turno'])) {
            $where[]  = 'lc.turno = :turno';
            $params[':turno'] = $filtros['turno'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'lc.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['id_proveedor'])) {
            $where[]  = 'lc.id_proveedor = :id_proveedor';
            $params[':id_proveedor'] = (int)$filtros['id_proveedor'];
        }
        if (!empty($filtros['id_cliente'])) {
            $where[]  = 'lc.id_cliente = :id_cliente';
            $params[':id_cliente'] = (int)$filtros['id_cliente'];
        }
        if (!empty($filtros['cliente'])) {
            $where[]  = 'uc.nombre LIKE :cliente';
            $params[':cliente'] = '%' . $filtros['cliente'] . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->db->prepare("
            SELECT
                lc.id,
                lc.fecha,
                lc.turno,
                lc.estado,
                lc.id_cliente,
                lc.id_proveedor,
                lc.cantidad_esperada,
                lc.cantidad_escaneada,
                lc.cantidad_faltante,
                lc.created_at,
                uc.nombre AS cliente_nombre,
                up.nombre AS proveedor_nombre,
                uo.nombre AS operador_nombre
              FROM logistica_colectas lc
         LEFT JOIN usuarios uc ON uc.id = lc.id_cliente
         LEFT JOIN usuarios up ON up.id = lc.id_proveedor
         LEFT JOIN usuarios uo ON uo.id = lc.id_abierta_por
              {$whereSql}
          ORDER BY lc.created_at DESC
             LIMIT 200
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve los pedidos de una colecta con datos del pedido real
     * (numero_orden, destinatario) para la vista de detalle.
     *
     * @return array<int,array>
     */
    public function obtenerPedidosDetalle(int $idColecta): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cp.id_pedido,
                cp.resultado    AS resultado_pedido,
                cp.escaneado_at,
                p.numero_orden,
                p.destinatario
              FROM logistica_colecta_pedidos cp
         LEFT JOIN pedidos p ON p.id = cp.id_pedido
             WHERE cp.id_colecta = :id_colecta
          ORDER BY cp.updated_at DESC
        ");
        $stmt->execute([':id_colecta' => $idColecta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
