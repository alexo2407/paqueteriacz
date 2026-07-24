<?php

declare(strict_types=1);

/**
 * LogisticaOperativaException
 *
 * Excepción base para errores del módulo Logística Operativa.
 * Permite distinguir errores de dominio de errores de infraestructura.
 */
class LogisticaOperativaException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
