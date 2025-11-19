<?php 

/**
 * templateController
 *
 * Controlador sencillo encargado de incluir la plantilla principal del
 * frontend. Mantener esta clase mínima; la lógica de render debería
 * residir en vistas y helpers de plantilla.
 */
class templateController {

    /**
     * Incluye la vista de la plantilla principal.
     * No devuelve nada; la vista es responsable de renderizar el layout.
     */
    public function template()
    {
        // Incluir la plantilla principal (vista)
        include "vista/template.php";
    }
}