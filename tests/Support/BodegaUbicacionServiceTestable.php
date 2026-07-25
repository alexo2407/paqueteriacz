<?php

declare(strict_types=1);

// BodegaUbicacionServiceTestable extiende BodegaUbicacionService
// y sobreescribe verificarFlags() para omitir la verificación durante las pruebas.
//
// Gestión de transacciones anidadas (igual que ColectaServiceTestable):
//   Las pruebas abren una transacción en setUp() para hacer rollback en tearDown().
//   El servicio usa SAVEPOINTs cuando detecta que ya hay una transacción activa.

require_once dirname(__DIR__, 2) . '/services/logistica_operativa/LogisticaOperativaException.php';
require_once dirname(__DIR__, 2) . '/services/logistica_operativa/BodegaUbicacionService.php';

class BodegaUbicacionServiceTestable extends BodegaUbicacionService
{
    public function __construct(\PDO $db)
    {
        parent::__construct($db);
    }

    /**
     * En pruebas, no se verifica LOGISTICA_OPERATIVA_ENABLED ni SHADOW_MODE.
     * Los flags están desactivados en phpunit.xml para aislar de producción.
     */
    protected function verificarFlags(): void
    {
        // No-op: los flags están desactivados en el entorno de test.
        // La seguridad se garantiza por la base de pruebas exclusiva.
    }
}
