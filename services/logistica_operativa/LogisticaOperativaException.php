<?php

declare(strict_types=1);

/**
 * LogisticaOperativaException
 *
 * Excepción base para errores del módulo Logística Operativa.
 * Permite distinguir errores de dominio de errores de infraestructura.
 *
 * Extensión retrocompatible: ahora soporta un código de dominio estable
 * (domainCode) que los controladores usan para mapear HTTP status codes
 * sin depender del texto del mensaje.
 *
 * Retrocompatibilidad:
 *   - El constructor original (message, int $code, ?Throwable $previous)
 *     sigue funcionando; domainCode queda vacío en ese caso.
 *   - Para lanzar con código de dominio, usar:
 *       new LogisticaOperativaException('mensaje', 'DOMAIN_CODE')
 *     o con causa:
 *       new LogisticaOperativaException('mensaje', 'DOMAIN_CODE', $e)
 *
 * Nota: Exception::getCode() devuelve el código entero heredado de PHP,
 * no usar ese campo para códigos de texto; usar getDomainCode() en su lugar.
 */
class LogisticaOperativaException extends RuntimeException
{
    private string $domainCode;

    /**
     * @param string          $message    Mensaje seguro para logs internos.
     * @param string|int      $domainCode Código de dominio estable (p. ej. 'PEDIDO_NO_ENCONTRADO').
     *                                    Si se pasa un int, se comporta igual que el constructor original.
     * @param \Throwable|null $previous   Excepción causante (opcional).
     */
    public function __construct(
        string           $message,
        string|int       $domainCode = 0,
        ?\Throwable      $previous   = null
    ) {
        // Retrocompatibilidad: si domainCode es entero, lo usamos como código HTTP heredado
        if (is_int($domainCode)) {
            parent::__construct($message, $domainCode, $previous);
            $this->domainCode = '';
        } else {
            parent::__construct($message, 0, $previous);
            $this->domainCode = $domainCode;
        }
    }

    /**
     * Devuelve el código de dominio estable.
     * Vacío ('') si la excepción fue creada sin código de dominio explícito.
     */
    public function getDomainCode(): string
    {
        return $this->domainCode;
    }
}

