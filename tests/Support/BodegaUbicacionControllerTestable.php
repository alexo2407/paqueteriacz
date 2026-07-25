<?php
/**
 * tests/Support/BodegaUbicacionControllerTestable.php
 *
 * Clase auxiliar para pruebas del controlador BodegaUbicacion.
 * Permite inyectar PDO, simular autenticación y capturar respuestas
 * sin llamar exit() (usa ControllerResponseException en su lugar).
 *
 * Sigue exactamente el mismo patrón que ColectaControllerTestable:
 * los métodos de acción se reimplementan capturando ControllerResponseException
 * antes de cualquier catch (Throwable) para que pueda propagarse al test.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionServiceTestable.php';
require_once dirname(__DIR__, 2) . '/controlador/logistica_operativa/BodegaUbicacionController.php';

// ── Excepción capturable (reemplaza exit) ─────────────────────────────────────
if (!class_exists('ControllerResponseException')) {
    class ControllerResponseException extends \RuntimeException
    {
        public function __construct(
            public readonly int    $httpCode,
            public readonly string $jsonBody
        ) {
            parent::__construct("HTTP {$httpCode}");
        }
    }
}

// ── Testeable ─────────────────────────────────────────────────────────────────
if (!class_exists('BodegaUbicacionControllerTestable')) {

    class BodegaUbicacionControllerTestable extends \BodegaUbicacionController
    {
        /** Simulated JSON input (compartido entre llamadas). */
        public static string $simulatedInput = '';

        public function __construct(
            private \PDO $injectedDb,
            private int  $simulatedOperadorId,
            private bool $simulatedAuth = true,
            private bool $moduleEnabled = true,
            private bool $shadowEnabled = true,
            private bool $simulatedAuthorized = true
        ) {}

        // ── Módulo ──────────────────────────────────────────────────────────

        public function verificarModulo(): void
        {
            if (!$this->moduleEnabled) {
                $this->error('MODULE_DISABLED', 'El módulo Logística Operativa no está habilitado.', 403);
            }
            if (!$this->shadowEnabled) {
                $this->error('SHADOW_MODE_REQUIRED', 'El modo sombra debe estar activo.', 403);
            }
        }

        // ── Autorización ───────────────────────────────────────────────

        public function verificarAutorizacion(array $usuario): void
        {
            if (!$this->simulatedAuthorized) {
                $this->error(
                    'FORBIDDEN',
                    'No tiene permiso para acceder al módulo de bodega (logistica_operativa_bodega).',
                    403
                );
            }
        }

        // ── Servicio de prueba ───────────────────────────────────────────────

        protected function crearServicio(\PDO $db): \BodegaUbicacionService
        {
            return new \BodegaUbicacionServiceTestable($db);
        }

        // ── Acciones (reimplementadas para propagar ControllerResponseException) ──

        public function registrar(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $this->verificarAutorizacion($usuario);
            $idOperador = (int) ($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            foreach (['uuid', 'id_pedido', 'id_bodega', 'tipo_recepcion', 'recibido_at'] as $campo) {
                if (!isset($body[$campo]) || (is_string($body[$campo]) && trim($body[$campo]) === '')) {
                    $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido.", 400);
                }
            }
            if (!is_numeric($body['id_pedido']) || !is_numeric($body['id_bodega'])) {
                $this->error('INVALID_FIELD', 'id_pedido e id_bodega deben ser numéricos.', 400);
            }

            $datos = [
                'uuid'           => trim((string) $body['uuid']),
                'id_pedido'      => (int) $body['id_pedido'],
                'id_bodega'      => (int) $body['id_bodega'],
                'id_ubicacion'   => isset($body['id_ubicacion']) && is_numeric($body['id_ubicacion'])
                                        ? (int) $body['id_ubicacion'] : null,
                'id_escaneo'     => isset($body['id_escaneo']) && is_numeric($body['id_escaneo'])
                                        ? (int) $body['id_escaneo'] : null,
                'tipo_recepcion' => strtoupper(trim((string) $body['tipo_recepcion'])),
                'id_operador'    => $idOperador,           // ← del token, nunca del JSON
                'recibido_at'    => trim((string) $body['recibido_at']),
                'observacion'    => isset($body['observacion']) ? trim((string) $body['observacion']) : null,
            ];

            try {
                $resultado  = $this->crearServicio($this->injectedDb)->registrarRecepcion($datos);
                $httpCode   = $resultado['idempotente'] ? 200 : 201;
                $this->ok($resultado, $httpCode);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function asignar(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $this->verificarAutorizacion($usuario);
            $idOperador = (int) ($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            foreach (['id_pedido', 'id_recepcion', 'id_ubicacion'] as $campo) {
                if (!isset($body[$campo]) || !is_numeric($body[$campo])) {
                    $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido y debe ser numérico.", 400);
                }
            }

            $motivo = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

            try {
                $resultado = $this->crearServicio($this->injectedDb)->ubicarPaquete(
                    (int) $body['id_pedido'],
                    (int) $body['id_recepcion'],
                    (int) $body['id_ubicacion'],
                    $idOperador,
                    $motivo
                );
                $this->ok($resultado);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function actual(): void
        {
            $this->aplicarHeaders('GET, OPTIONS');
            $this->requerirMetodo('GET');
            $this->verificarModulo();
            $usuario = $this->autenticar();
            $this->verificarAutorizacion($usuario);

            $idPedidoRaw = $_GET['id_pedido'] ?? '';
            if ($idPedidoRaw === '' || !is_numeric($idPedidoRaw)) {
                $this->error('MISSING_PARAM', 'El parámetro id_pedido es requerido y debe ser numérico.', 400);
            }

            try {
                $resultado = $this->crearServicio($this->injectedDb)->obtenerUbicacionActual((int) $idPedidoRaw);
                if ($resultado === null) {
                    $this->error('UBICACION_ACTUAL_NO_ENCONTRADA', 'El pedido no tiene una ubicación activa.', 404);
                }
                $this->ok($resultado);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function reubicar(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $this->verificarAutorizacion($usuario);
            $idOperador = (int) ($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            foreach (['id_pedido', 'id_ubicacion_destino'] as $campo) {
                if (!isset($body[$campo]) || !is_numeric($body[$campo])) {
                    $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido y debe ser numérico.", 400);
                }
            }

            $motivo = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

            try {
                $resultado = $this->crearServicio($this->injectedDb)->reubicarPaquete(
                    (int) $body['id_pedido'],
                    (int) $body['id_ubicacion_destino'],
                    $idOperador,
                    $motivo
                );
                $this->ok($resultado);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function retirar(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $this->verificarAutorizacion($usuario);
            $idOperador = (int) ($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            if (!isset($body['id_pedido']) || !is_numeric($body['id_pedido'])) {
                $this->error('MISSING_FIELD', "El campo 'id_pedido' es requerido y debe ser numérico.", 400);
            }

            $motivo = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

            try {
                $resultado = $this->crearServicio($this->injectedDb)->retirarPaquete(
                    (int) $body['id_pedido'],
                    $idOperador,
                    $motivo
                );
                $this->ok($resultado);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function historial(): void
        {
            $this->aplicarHeaders('GET, OPTIONS');
            $this->requerirMetodo('GET');
            $this->verificarModulo();
            $usuario = $this->autenticar();
            $this->verificarAutorizacion($usuario);

            $idPedidoRaw = $_GET['id_pedido'] ?? '';
            if ($idPedidoRaw === '' || !is_numeric($idPedidoRaw)) {
                $this->error('MISSING_PARAM', 'El parámetro id_pedido es requerido y debe ser numérico.', 400);
            }

            try {
                $resultado = $this->crearServicio($this->injectedDb)->obtenerHistorial((int) $idPedidoRaw);
                $this->ok($resultado);
            } catch (\ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcion($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        // ── Sobreescrituras de infraestructura ───────────────────────────────

        public function autenticar(): array
        {
            if (!$this->simulatedAuth) {
                $this->error('UNAUTHENTICATED', 'Se requiere autenticación.', 401);
            }
            return ['id' => $this->simulatedOperadorId, 'nombre' => 'test-operador', 'rol' => 1];
        }

        public function crearConexion(): \PDO
        {
            return $this->injectedDb;
        }

        public function leerJson(): array
        {
            $raw = self::$simulatedInput;
            if (trim($raw) === '') {
                $this->error('EMPTY_BODY', 'El cuerpo de la petición está vacío.', 400);
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $this->error('INVALID_JSON', 'El cuerpo no es JSON válido.', 400);
            }
            return $data;
        }

        public function requerirJsonContentType(): void {}
        public function aplicarHeaders(string $allowedMethods = 'POST, OPTIONS'): void {}

        public function ok(mixed $data, int $code = 200): never
        {
            $json = json_encode(
                ['success' => true, 'data' => $data],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            throw new \ControllerResponseException($code, $json ?: '{}');
        }

        public function error(string $code, string $message, int $http = 400): never
        {
            $json = json_encode(
                ['success' => false, 'code' => $code, 'message' => $message],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            throw new \ControllerResponseException($http, $json ?: '{}');
        }
    }
}
