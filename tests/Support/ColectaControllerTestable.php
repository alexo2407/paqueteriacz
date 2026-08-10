<?php
/**
 * tests/Support/ColectaControllerTestable.php
 *
 * Clase auxiliar para pruebas del controlador de colectas.
 * Permite inyectar PDO, simular autenticación y capturar respuestas
 * sin llamar exit() (usa ControllerResponseException en su lugar).
 *
 * Compartida entre ColectaApiTest y ColectaUiTest.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tests/Support/ColectaServiceTestable.php';
require_once dirname(__DIR__, 2) . '/controlador/logistica_operativa/ColectaController.php';

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
if (!class_exists('ColectaControllerTestable')) {
    class ColectaControllerTestable extends \ColectaController
    {
        public static string $simulatedInput = '';

        public function __construct(
            private \PDO $injectedDb,
            private int  $simulatedOperadorId,
            private bool $simulatedAuth = true,
            private bool $moduleEnabled = true
        ) {}

        // ── Módulo ──────────────────────────────────────────────────────

        public function verificarModulo(): void
        {
            if (!$this->moduleEnabled) {
                $this->error('MODULE_DISABLED', 'El módulo Logística Operativa no está habilitado.', 403);
            }
        }

        // ── Servicio de prueba ────────────────────────────────────────────

        private function servicio(): \ColectaServiceTestable
        {
            return new \ColectaServiceTestable($this->injectedDb);
        }

        // ── Acciones (usan ColectaServiceTestable) ────────────────────────

        public function abrir(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $idOperador = (int)($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            if (empty($body['id_cliente']) || !is_numeric($body['id_cliente'])) {
                $this->error('MISSING_FIELD', 'El campo id_cliente es requerido y debe ser numérico.', 400);
            }
            if (empty($body['fecha']) || !is_string($body['fecha'])) {
                $this->error('MISSING_FIELD', 'El campo fecha es requerido.', 400);
            }
            if (empty($body['turno']) || !is_string($body['turno'])) {
                $this->error('MISSING_FIELD', 'El campo turno es requerido.', 400);
            }

            $idProveedor = !empty($body['id_proveedor']) ? (int)$body['id_proveedor'] : $idOperador;

            try {
                $resultado = $this->servicio()->abrirColecta(
                    (int)$body['id_cliente'],
                    $idProveedor,
                    trim($body['fecha']),
                    strtoupper(trim($body['turno'])),
                    $idOperador
                );
                $this->ok($resultado, 201);
            } catch (ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcionTestable($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function escanear(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $idOperador = (int)($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();

            foreach (['uuid', 'id_colecta', 'id_pedido', 'tipo_evento', 'qr_hash', 'escaneado_at'] as $campo) {
                if (!isset($body[$campo]) || (is_string($body[$campo]) && trim($body[$campo]) === '')) {
                    $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido.", 400);
                }
            }

            $datos = [
                'uuid'          => trim((string)$body['uuid']),
                'id_colecta'    => (int)$body['id_colecta'],
                'id_pedido'     => (int)$body['id_pedido'],
                'tipo_evento'   => strtoupper(trim((string)$body['tipo_evento'])),
                'qr_hash'       => strtolower(trim((string)$body['qr_hash'])),
                'id_operador'   => $idOperador,
                'dispositivo'   => isset($body['dispositivo']) ? trim((string)$body['dispositivo']) : null,
                'escaneado_at'  => trim((string)$body['escaneado_at']),
                'metadata_json' => isset($body['metadata_json']) && is_array($body['metadata_json'])
                                        ? json_encode($body['metadata_json']) : null,
            ];

            try {
                $resultado = $this->servicio()->registrarEscaneo($datos);
                $this->ok($resultado);
            } catch (ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcionTestable($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function cerrar(): void
        {
            $this->aplicarHeaders('POST, OPTIONS');
            $this->requerirMetodo('POST');
            $this->verificarModulo();
            $this->requerirJsonContentType();

            $usuario    = $this->autenticar();
            $idOperador = (int)($usuario['id'] ?? 0);
            if ($idOperador <= 0) {
                $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
            }

            $body = $this->leerJson();
            if (empty($body['id_colecta']) || !is_numeric($body['id_colecta'])) {
                $this->error('MISSING_FIELD', 'El campo id_colecta es requerido y debe ser numérico.', 400);
            }

            try {
                $resultado = $this->servicio()->cerrarYConciliar((int)$body['id_colecta'], $idOperador);
                $this->ok($resultado);
            } catch (ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcionTestable($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        public function resumen(): void
        {
            $this->aplicarHeaders('GET, OPTIONS');
            $this->requerirMetodo('GET');
            $this->verificarModulo();
            $this->autenticar();

            $idColectaRaw = $_GET['id_colecta'] ?? '';
            if ($idColectaRaw === '' || !is_numeric($idColectaRaw)) {
                $this->error('MISSING_PARAM', 'El parámetro id_colecta es requerido y debe ser numérico.', 400);
            }

            try {
                $resultado = $this->servicio()->obtenerResumen((int)$idColectaRaw);
                $this->ok($resultado);
            } catch (ControllerResponseException $e) {
                throw $e;
            } catch (\LogisticaOperativaException $e) {
                $this->mapearExcepcionTestable($e);
            } catch (\Throwable $e) {
                $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
            }
        }

        // ── Mapeo de excepción ────────────────────────────────────────────

        private function mapearExcepcionTestable(\LogisticaOperativaException $e): never
        {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Ya existe una colecta') ||
                str_contains($msg, 'no está ABIERTA') ||
                str_contains($msg, 'No se puede cerrar')) {
                $this->error('CONFLICT', $msg, 409);
            }
            if (str_contains($msg, 'no encontrada') || str_contains($msg, 'no encontrado')) {
                $this->error('NOT_FOUND', $msg, 404);
            }
            if (str_contains($msg, 'inválido') || str_contains($msg, 'inválida') ||
                str_contains($msg, 'debe ser') || str_contains($msg, 'Use ')) {
                $this->error('UNPROCESSABLE', $msg, 422);
            }
            $this->error('BAD_REQUEST', $msg, 400);
        }

        // ── Sobreescrituras de infraestructura ────────────────────────────

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
            throw new ControllerResponseException($code, $json ?: '{}');
        }

        public function error(string $code, string $message, int $http = 400): never
        {
            $json = json_encode(
                ['success' => false, 'code' => $code, 'message' => $message],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            throw new ControllerResponseException($http, $json ?: '{}');
        }
    }
}
