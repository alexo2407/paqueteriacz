-- ===========================================================================
-- 07-auditoria-datos-historicos.sql
--
-- Módulo:  Logística Operativa — Fase 0.1
-- Fecha:   2026-07-22
-- Autor:   SDD — Spec-Driven Development
--
-- PROPÓSITO: Diagnóstico de solo lectura.
-- Estas consultas identifican registros que PUEDEN haber sido afectados
-- por las inconsistencias corregidas en Fase 0.1.
--
-- RESTRICCIONES OBLIGATORIAS:
--   ❌ NO ejecutar UPDATE, DELETE ni INSERT.
--   ❌ NO aplicar correcciones automáticas sobre datos históricos.
--   ❌ NO ejecutar en producción sin autorización explícita y sin respaldo.
--   ✅ Solo SELECT y análisis manual.
--
-- INTERPRETACIÓN DE RESULTADOS:
--   Los registros devueltos NO demuestran automáticamente que cada fila fue
--   afectada por el bug. Pueden existir explicaciones alternativas legítimas.
--   Cualquier corrección histórica requiere:
--     1. Respaldo completo de la base de datos.
--     2. Revisión manual caso por caso.
--     3. Autorización explícita del responsable del sistema.
-- ===========================================================================


-- ---------------------------------------------------------------------------
-- CONSULTA 1: Entregas con fecha registrada pero estado "Asignado" (ID 1)
--
-- Contexto: Antes de Fase 0.1, EntregaModel::marcarEntregado() asignaba
--   id_estado_entrega = 1 ("Asignado") en lugar de 3 ("Entregado con éxito").
--
-- Interpretación:
--   Un registro con fecha_entrega IS NOT NULL y id_estado_entrega = 1
--   PUEDE indicar que fue marcado como entregado bajo el comportamiento
--   defectuoso anterior.
--   También puede ser un registro en proceso que nunca se completó.
--   Requiere revisión manual para distinguir ambos casos.
--
-- NO modificar estos registros sin análisis previo.
-- ---------------------------------------------------------------------------

SELECT
    id,
    id_pedido,
    id_repartidor,
    id_estado_entrega,
    fecha_entrega
FROM entregas
WHERE fecha_entrega IS NOT NULL
  AND id_estado_entrega = 1;


-- ---------------------------------------------------------------------------
-- CONSULTA 2: Pedidos con "Domicilio cerrado" (ID 5) y sus reservas de stock
--
-- Contexto: Antes de Fase 0.1, PedidoService::ESTADO_CANCELADO = 5,
--   lo que causaba que al entrar al estado "Domicilio cerrado" se ejecutara
--   liberarReservaPedido(). Esto liberaba reservas de pedidos que no estaban
--   cancelados.
--
-- Interpretación:
--   Un pedido en estado 5 con reservas liberadas (liberada = 1) puede haber
--   tenido su reserva suelta incorrectamente.
--   Un pedido en estado 5 con reservas activas (liberada = 0) puede haber
--   fallado al liberar antes del bug, o el pedido puede ser reciente.
--   Requiere análisis del historial de cambios para determinar la causa real.
--
-- NO modificar reservas ni estados sin análisis y respaldo previo.
-- ---------------------------------------------------------------------------

SELECT
    p.id,
    p.numero_orden,
    p.id_estado,
    prs.id_producto,
    prs.cantidad,
    prs.liberada,
    prs.updated_at
FROM pedidos p
INNER JOIN pedido_reservas_stock prs
    ON prs.id_pedido = p.id
WHERE p.id_estado = 5
ORDER BY prs.updated_at DESC;


-- ---------------------------------------------------------------------------
-- CONSULTA COMPLEMENTARIA: Pedidos cancelados (ID 17) con reservas activas
--
-- Contexto: Antes de Fase 0.1, los pedidos en estado 17 ("Cancelado") no
--   eran reconocidos por PedidoService, por lo que sus reservas de stock
--   NUNCA se liberaban. Este es el efecto de "fuga de stock" documentado.
--
-- Interpretación:
--   Un pedido en estado 17 con reservas activas (liberada = 0) puede tener
--   stock bloqueado indefinidamente a causa del bug anterior.
--   Verificar también el historial de estados (historial_estados_pedidos)
--   para confirmar que el cambio a estado 17 ocurrió antes de Fase 0.1.
--
-- NO ejecutar liberaciones automáticas sin revisión individual.
-- ---------------------------------------------------------------------------

SELECT
    p.id,
    p.numero_orden,
    p.id_estado,
    prs.id_producto,
    prs.cantidad,
    prs.liberada,
    prs.updated_at
FROM pedidos p
INNER JOIN pedido_reservas_stock prs
    ON prs.id_pedido = p.id
WHERE p.id_estado = 17
  AND prs.liberada = 0
ORDER BY prs.updated_at DESC;
