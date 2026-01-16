<?php
header("Content-Type: application/json; charset=UTF-8");

include_once '../../utils/crm_roles.php';
include_once '../../modelo/conexion.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener Payload
$data = json_decode(file_get_contents("php://input"), true);

// 1. Validaciones Básicas de Entrada
if (!isset($data['cliente_id']) || !isset($data['lead_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Falta cliente_id o lead_ids']);
    exit;
}

$clienteId = (int)$data['cliente_id'];
$leadIds = is_array($data['lead_ids']) ? $data['lead_ids'] : [$data['lead_ids']]; // Normalizar a array
$observaciones = isset($data['observaciones']) ? $data['observaciones'] : "Asignación manual de cliente";

if (empty($leadIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lista de leads vacía']);
    exit;
}

try {
    // Autenticación global (ya hecha por router, pero validamos usuario)
    if (!isset($globalUserId)) {
        throw new Exception("Error de sesión");
    }

    $db = Conexion::conectar();
    
    // 2. Verificar que el Usuario es Admin o Proveedor
    // (Un cliente no puede asignarse leads a sí mismo ni a otros)
    if (isClienteCRM($globalUserId) && !isUserAdmin($globalUserId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permiso denegado: Los clientes no pueden asignar leads']);
        exit;
    }

    // 3. Validar que el CLIENTE destino exista y sea rol Cliente
    $stmt = $db->prepare("SELECT id, nombre, rol FROM usuarios WHERE id = ?");
    $stmt->execute([$clienteId]);
    $clienteDestino = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clienteDestino || strtolower($clienteDestino['rol']) !== 'cliente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El ID proporcionado no corresponde a un usuario con rol Cliente']);
        exit;
    }

    $nombreCliente = $clienteDestino['nombre'];
    $isAdmin = isUserAdmin($globalUserId);

    // 4. Filtrar Leads Válidos (Propiedad)
    // Preparamos placeholders para IN (...)
    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    
    // Seleccionamos ID y proveedor_id para verificar propiedad
    $stmt = $db->prepare("SELECT id, proveedor_id, cliente_id FROM crm_leads WHERE id IN ($placeholders)");
    $stmt->execute($leadIds);
    $foundLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $validIds = [];
    $failedDetails = [];
    
    // Check de Existencia en BD
    $foundIdsMap = [];
    foreach ($foundLeads as $l) {
        $foundIdsMap[$l['id']] = $l;
    }
    
    foreach ($leadIds as $id) {
        if (!isset($foundIdsMap[$id])) {
            $failedDetails[] = ['lead_id' => $id, 'error' => 'Lead no encontrado'];
            continue;
        }
        
        $lead = $foundIdsMap[$id];
        
        // Regla de Negocio: 
        // Admin puede todo. 
        // Proveedor solo puede asignar SUS leads.
        if (!$isAdmin) {
            // Asumimos que $globalUserId es el proveedor_id
            // (En tu sistema, usuario ID = proveedor ID en crm_leads)
            if ((int)$lead['proveedor_id'] !== $globalUserId) {
                $failedDetails[] = ['lead_id' => $id, 'error' => 'No tienes permiso (No eres el proveedor creador)'];
                continue;
            }
        }
        
        $validIds[] = $id;
    }

    // 5. Ejecutar Actualización Masiva (Batch Update)
    $updatedCount = 0;
    
    if (!empty($validIds)) {
        // Comenzamos transacción para atomicidad de update + historial
        $db->beginTransaction();
        
        try {
            $updatePlaceholders = implode(',', array_fill(0, count($validIds), '?'));
            
            // A. Update crm_leads
            $sql = "UPDATE crm_leads SET cliente_id = ? WHERE id IN ($updatePlaceholders)";
            // El primer param es $clienteId, luego mergeamos los IDs
            $params = array_merge([$clienteId], $validIds);
            
            $stmtUpdate = $db->prepare($sql);
            $stmtUpdate->execute($params);
            $updatedCount = $stmtUpdate->rowCount();
            
            // B. (Opcional) Insertar en historial si quieres rastrear asignaciones
            // Insertamos un registro en crm_lead_status_history, aunque no cambie el estado, 
            // sirve como log. O podrías crear una tabla separada.
            // Por simplicidad y performance, a veces se omite en bulk assigns masivos.
            // Si quieres log, descomenta esto:
            /*
            $sqlHist = "INSERT INTO crm_lead_status_history (lead_id, estado_anterior, estado_nuevo, cambiado_por, observaciones) VALUES (?, 'ASIGNACION', 'ASIGNACION', ?, ?)";
            $stmtHist = $db->prepare($sqlHist);
            foreach ($validIds as $vid) {
                $stmtHist->execute([
                    $vid, 
                    $globalUserId, 
                    "Cliente asignado: $nombreCliente ($clienteId). $observaciones"
                ]);
            }
            */
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // 6. Respuesta Optimizada (Solo mostrar fallos si existen)
    $response = [
        'success' => true,
        'message' => "Operación completada. $updatedCount asignados a '$nombreCliente'.",
        'updated' => $updatedCount,
        'failed' => count($failedDetails),
        'total_processed' => count($leadIds)
    ];
    
    // Solo agregamos failed_details si hay errores, para optimizar el payload
    if (!empty($failedDetails)) {
        $response['failed_details'] = $failedDetails;
        // Si fallaron todos, quizás cambiar success a false o mantener true parcial
        if ($updatedCount === 0) {
            $response['success'] = false;
            $response['message'] = "No se pudieron asignar leads. Revisa los errores.";
        } else {
            $response['message'] .= " (" . count($failedDetails) . " fallos)";
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
