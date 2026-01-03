<?php
/**
 * Rate Limiting Strategies para Bulk Jobs
 * 
 * Implementa diferentes estrategias de límite de rate
 */

require_once __DIR__ . '/../modelo/conexion.php';

/**
 * Estrategia 1: Límite de Jobs Pendientes por Usuario
 * 
 * Máximo de jobs en cola/procesando simultáneamente
 */
function checkPendingJobsLimit($userId, $maxPending = 10) {
    $db = (new Conexion())->conectar();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM crm_bulk_jobs 
        WHERE user_id = :user_id 
        AND status IN ('queued', 'processing')
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $pendingJobs = $stmt->fetchColumn();
    
    if ($pendingJobs >= $maxPending) {
        return [
            'allowed' => false,
            'message' => "Límite alcanzado: tienes $pendingJobs jobs pendientes (máximo $maxPending)",
            'retry_after' => 60 // segundos
        ];
    }
    
    return ['allowed' => true];
}

/**
 * Estrategia 2: Límite de Jobs por Día
 * 
 * Máximo de jobs que puede crear un usuario por día
 */
function checkDailyJobsLimit($userId, $maxPerDay = 100) {
    $db = (new Conexion())->conectar();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM crm_bulk_jobs 
        WHERE user_id = :user_id 
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $jobsToday = $stmt->fetchColumn();
    
    if ($jobsToday >= $maxPerDay) {
        return [
            'allowed' => false,
            'message' => "Límite diario alcanzado: has creado $jobsToday jobs hoy (máximo $maxPerDay)",
            'reset_at' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ];
    }
    
    return [
        'allowed' => true,
        'remaining' => $maxPerDay - $jobsToday
    ];
}

/**
 * Estrategia 3: Límite de Leads Totales por Job
 * 
 * Evitar jobs extremadamente grandes
 */
function checkJobSizeLimit($leadCount, $maxLeadsPerJob = 10000) {
    if ($leadCount > $maxLeadsPerJob) {
        return [
            'allowed' => false,
            'message' => "Job demasiado grande: $leadCount leads (máximo $maxLeadsPerJob por job)",
            'suggestion' => "Divide tu request en múltiples jobs de $maxLeadsPerJob leads"
        ];
    }
    
    return ['allowed' => true];
}

/**
 * Estrategia 4: Límite de Leads Totales por Usuario por Día
 * 
 * Limitar volumen total de procesamiento
 */
function checkDailyLeadsLimit($userId, $leadCount, $maxLeadsPerDay = 50000) {
    $db = (new Conexion())->conectar();
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_leads), 0) FROM crm_bulk_jobs 
        WHERE user_id = :user_id 
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $leadsToday = $stmt->fetchColumn();
    
    if (($leadsToday + $leadCount) > $maxLeadsPerDay) {
        return [
            'allowed' => false,
            'message' => "Límite diario de leads alcanzado: has procesado $leadsToday leads hoy, este job agregaría $leadCount más (máximo $maxLeadsPerDay)",
            'reset_at' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ];
    }
    
    return [
        'allowed' => true,
        'remaining_leads' => $maxLeadsPerDay - $leadsToday
    ];
}

/**
 * Estrategia 5: Throttling por Tiempo (Cooldown)
 * 
 * Tiempo mínimo entre jobs del mismo usuario
 */
function checkCooldownPeriod($userId, $cooldownSeconds = 30) {
    $db = (new Conexion())->conectar();
    
    $stmt = $db->prepare("
        SELECT MAX(created_at) as last_job_time
        FROM crm_bulk_jobs 
        WHERE user_id = :user_id
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['last_job_time']) {
        $lastJobTime = strtotime($result['last_job_time']);
        $timeSinceLastJob = time() - $lastJobTime;
        
        if ($timeSinceLastJob < $cooldownSeconds) {
            $waitTime = $cooldownSeconds - $timeSinceLastJob;
            return [
                'allowed' => false,
                'message' => "Por favor espera $waitTime segundos antes de crear otro job",
                'retry_after' => $waitTime
            ];
        }
    }
    
    return ['allowed' => true];
}

/**
 * Aplicar TODAS las validaciones de rate limiting
 */
function enforceRateLimits($userId, $leadCount) {
    // Configuración de límites (puedes hacerlo configurable por rol)
    $limits = [
        'max_pending_jobs' => 10,
        'max_jobs_per_day' => 100,
        'max_leads_per_job' => 10000,
        'max_leads_per_day' => 50000,
        'cooldown_seconds' => 30
    ];
    
    // Validación 1: Jobs pendientes
    $check = checkPendingJobsLimit($userId, $limits['max_pending_jobs']);
    if (!$check['allowed']) return $check;
    
    // Validación 2: Jobs por día
    $check = checkDailyJobsLimit($userId, $limits['max_jobs_per_day']);
    if (!$check['allowed']) return $check;
    
    // Validación 3: Tamaño del job
    $check = checkJobSizeLimit($leadCount, $limits['max_leads_per_job']);
    if (!$check['allowed']) return $check;
    
    // Validación 4: Leads por día
    $check = checkDailyLeadsLimit($userId, $leadCount, $limits['max_leads_per_day']);
    if (!$check['allowed']) return $check;
    
    // Validación 5: Cooldown
    $check = checkCooldownPeriod($userId, $limits['cooldown_seconds']);
    if (!$check['allowed']) return $check;
    
    return ['allowed' => true];
}
