-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: paquetes_apppack
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `api_doc_historial`
--

DROP TABLE IF EXISTS `api_doc_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_doc_historial` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL COMMENT 'T├¡tulo del documento generado',
  `empresa_cliente` varchar(255) NOT NULL COMMENT 'Empresa/cliente destinatario',
  `url_base` varchar(500) NOT NULL COMMENT 'URL base del API documentada',
  `secciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array de secciones incluidas' CHECK (json_valid(`secciones`)),
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Configuraci├│n completa del documento (todos los datos del wizard)' CHECK (json_valid(`config_json`)),
  `html_generado` longtext NOT NULL COMMENT 'HTML del documento generado (para re-exportar)',
  `id_usuario` int(11) DEFAULT NULL COMMENT 'ID del admin que gener├│ el documento',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_cliente`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de documentos de API generados por el wizard';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditoria_cambios`
--

DROP TABLE IF EXISTS `auditoria_cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria_cambios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tabla` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre de la tabla afectada',
  `id_registro` int(11) NOT NULL COMMENT 'ID del registro modificado',
  `accion` enum('crear','actualizar','eliminar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de operaci├│n',
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Usuario que realiz├│ la acci├│n',
  `session_id` varchar(100) DEFAULT NULL,
  `user_role` varchar(100) DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Estado anterior del registro',
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Estado nuevo del registro',
  `url_endpoint` varchar(500) DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Direcci├│n IP del cliente',
  `pais_origen` varchar(255) DEFAULT NULL,
  `is_proxy` tinyint(1) DEFAULT 0,
  `device_os` varchar(50) DEFAULT NULL,
  `device_browser` varchar(50) DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User agent del navegador',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha del cambio',
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_tabla` (`tabla`),
  KEY `idx_auditoria_registro` (`tabla`,`id_registro`),
  KEY `idx_auditoria_usuario` (`id_usuario`),
  KEY `idx_auditoria_accion` (`accion`),
  KEY `idx_auditoria_fecha` (`created_at`),
  KEY `idx_auditoria_tabla_fecha` (`tabla`,`created_at`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `barrios`
--

DROP TABLE IF EXISTS `barrios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barrios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `id_municipio` int(11) NOT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_municipio` (`id_municipio`),
  CONSTRAINT `barrios_ibfk_1` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27791 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `caex_poblados`
--

DROP TABLE IF EXISTS `caex_poblados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caex_poblados` (
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `nombre_normalizado` varchar(200) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`codigo`),
  KEY `idx_nombre` (`nombre_normalizado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias_productos`
--

DROP TABLE IF EXISTS `categorias_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias_productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `padre_id` int(11) DEFAULT NULL COMMENT 'Para categor├¡as anidadas (subcategor├¡as)',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categoria_activa` (`activo`),
  KEY `idx_categoria_padre` (`padre_id`),
  CONSTRAINT `categorias_productos_ibfk_1` FOREIGN KEY (`padre_id`) REFERENCES `categorias_productos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categor├¡as de productos con soporte para jerarqu├¡a';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `codigos_postales`
--

DROP TABLE IF EXISTS `codigos_postales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `codigos_postales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pais` int(11) NOT NULL,
  `codigo_postal` varchar(20) NOT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `id_municipio` int(11) DEFAULT NULL,
  `id_barrio` int(11) DEFAULT NULL,
  `nombre_localidad` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pais_cp_barrio` (`id_pais`,`codigo_postal`,`id_barrio`),
  KEY `idx_cp_departamento` (`id_departamento`),
  KEY `idx_cp_municipio` (`id_municipio`),
  KEY `fk_cp_barrio` (`id_barrio`),
  CONSTRAINT `fk_cp_barrio` FOREIGN KEY (`id_barrio`) REFERENCES `barrios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `fk_cp_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id`),
  CONSTRAINT `fk_cp_pais` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_bulk_jobs`
--

DROP TABLE IF EXISTS `crm_bulk_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_bulk_jobs` (
  `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `lead_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'queued',
  `total_leads` int(11) NOT NULL,
  `processed_leads` int(11) DEFAULT 0,
  `successful_leads` int(11) DEFAULT 0,
  `failed_leads` int(11) DEFAULT 0,
  `failed_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_inbox`
--

DROP TABLE IF EXISTS `crm_inbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_inbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` enum('proveedor','cliente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('pending','processed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_source_key` (`source`,`idempotency_key`),
  KEY `idx_status_received` (`status`,`received_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_integrations`
--

DROP TABLE IF EXISTS `crm_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `kind` enum('cliente','proveedor') NOT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `secret` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_kind` (`user_id`,`kind`),
  CONSTRAINT `fk_crm_integrations_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_lead_status_history`
--

DROP TABLE IF EXISTS `crm_lead_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_lead_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_crm_status_actor` (`actor_user_id`),
  KEY `idx_lead_created` (`lead_id`,`created_at`),
  KEY `idx_estado_nuevo` (`estado_nuevo`,`created_at`),
  CONSTRAINT `fk_crm_status_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_crm_status_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_leads`
--

DROP TABLE IF EXISTS `crm_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `proveedor_lead_id` varchar(120) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `producto` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `estado_actual` enum('CANCELADO','APROBADO','CONFIRMADO','EN_TRANSITO','EN_BODEGA','EN_ESPERA') DEFAULT 'EN_ESPERA',
  `duplicado` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_proveedor_lead` (`proveedor_id`,`proveedor_lead_id`),
  KEY `idx_cliente_fecha` (`cliente_id`,`fecha_hora`),
  KEY `idx_proveedor_fecha` (`proveedor_id`,`fecha_hora`),
  KEY `idx_telefono` (`telefono`),
  KEY `idx_crm_estado_actual` (`estado_actual`),
  KEY `idx_crm_fecha_hora` (`fecha_hora`),
  KEY `idx_crm_created_at` (`created_at`),
  KEY `idx_crm_proveedor_id` (`proveedor_id`),
  KEY `idx_crm_cliente_id` (`cliente_id`),
  KEY `idx_crm_estado_created` (`estado_actual`,`created_at`),
  KEY `idx_crm_leads_cliente_id` (`cliente_id`),
  KEY `idx_crm_leads_estado` (`estado_actual`),
  KEY `idx_crm_leads_id_cliente_estado` (`id`,`cliente_id`,`estado_actual`),
  CONSTRAINT `fk_crm_leads_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_crm_leads_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=403 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_notifications`
--

DROP TABLE IF EXISTS `crm_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_lead_id` int(11) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`),
  KEY `idx_lead` (`related_lead_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=261 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_outbox`
--

DROP TABLE IF EXISTS `crm_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_outbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('SEND_TO_CLIENT','SEND_TO_PROVIDER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `destination_user_id` int(11) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('pending','sending','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_intentos` int(11) NOT NULL DEFAULT 5,
  `next_retry_at` datetime DEFAULT NULL,
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_crm_outbox_lead` (`lead_id`),
  KEY `fk_crm_outbox_destination` (`destination_user_id`),
  KEY `idx_status_retry` (`status`,`next_retry_at`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_crm_outbox_destination` FOREIGN KEY (`destination_user_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_crm_outbox_lead` FOREIGN KEY (`lead_id`) REFERENCES `crm_leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departamentos`
--

DROP TABLE IF EXISTS `departamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_pais` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pais` (`id_pais`),
  CONSTRAINT `departamentos_ibfk_1` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=259 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `entregas`
--

DROP TABLE IF EXISTS `entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entregas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_repartidor` int(11) NOT NULL,
  `fecha_asignacion` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `id_estado_entrega` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_repartidor` (`id_repartidor`),
  KEY `fk_estado_entrega` (`id_estado_entrega`),
  CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`id_repartidor`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_estado_entrega` FOREIGN KEY (`id_estado_entrega`) REFERENCES `estados_entrega` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estados_entrega`
--

DROP TABLE IF EXISTS `estados_entrega`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_entrega` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estados_pedidos`
--

DROP TABLE IF EXISTS `estados_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forwarding_api_fields`
--

DROP TABLE IF EXISTS `forwarding_api_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forwarding_api_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_provider` int(11) NOT NULL,
  `field_path` varchar(255) NOT NULL,
  `label` varchar(150) NOT NULL,
  `field_type` enum('string','int','float','boolean','array') NOT NULL DEFAULT 'string',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`id_provider`),
  CONSTRAINT `fk_faf_provider` FOREIGN KEY (`id_provider`) REFERENCES `forwarding_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=216 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forwarding_api_mappings`
--

DROP TABLE IF EXISTS `forwarding_api_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forwarding_api_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_api_field` int(11) NOT NULL,
  `internal_key` varchar(255) NOT NULL,
  `transform_rule` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field_mapping` (`id_api_field`),
  CONSTRAINT `fk_fam_field` FOREIGN KEY (`id_api_field`) REFERENCES `forwarding_api_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forwarding_log`
--

DROP TABLE IF EXISTS `forwarding_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forwarding_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL COMMENT 'FK ÔåÆ pedidos.id',
  `id_provider` int(11) NOT NULL COMMENT 'FK ÔåÆ forwarding_providers.id',
  `id_rule` int(11) NOT NULL COMMENT 'FK ÔåÆ forwarding_rules.id',
  `request_payload` text DEFAULT NULL COMMENT 'JSON enviado al proveedor externo',
  `response_payload` text DEFAULT NULL COMMENT 'JSON recibido del proveedor externo',
  `http_status` int(11) DEFAULT NULL COMMENT 'C├│digo HTTP de la respuesta',
  `status` enum('success','failed','pending','cancelled') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL COMMENT 'Mensaje de error si fall├│',
  `external_order_id` varchar(100) DEFAULT NULL COMMENT 'ID de la orden en el sistema externo',
  `attempts` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_provider_log` (`id_provider`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de cada intento de forwarding a proveedores externos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forwarding_providers`
--

DROP TABLE IF EXISTS `forwarding_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forwarding_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre visible (ej: LogisPro M├®xico)',
  `slug` varchar(50) NOT NULL COMMENT 'Identificador de c├│digo (ej: logispro)',
  `base_url` varchar(255) NOT NULL COMMENT 'URL base de la API del proveedor',
  `auth_endpoint` varchar(255) NOT NULL DEFAULT '/api/AccountApi' COMMENT 'Ruta de autenticaci├│n',
  `order_endpoint` varchar(255) NOT NULL DEFAULT '/api/Orders/OrderAndOrderDetail' COMMENT 'Ruta de creaci├│n de orden',
  `payload_format` enum('json','xml','soap') NOT NULL DEFAULT 'json',
  `auth_method` enum('bearer_jwt','api_key','basic') NOT NULL DEFAULT 'bearer_jwt' COMMENT 'M├®todo de autenticaci├│n',
  `credentials` text NOT NULL COMMENT 'JSON con credenciales: {"userName":"...","password":"..."}',
  `default_config` text DEFAULT NULL COMMENT 'JSON con configuraci├│n extra del proveedor',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cat├ílogo de proveedores externos para forwarding de pedidos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forwarding_rules`
--

DROP TABLE IF EXISTS `forwarding_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forwarding_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL COMMENT 'FK ÔåÆ clientes.ID_Cliente',
  `id_provider` int(11) NOT NULL COMMENT 'FK ÔåÆ forwarding_providers.id',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `config_override` text DEFAULT NULL COMMENT 'JSON con configuraci├│n espec├¡fica para este cliente (override)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cliente_provider` (`id_cliente`,`id_provider`),
  KEY `idx_cliente` (`id_cliente`),
  KEY `idx_provider` (`id_provider`),
  CONSTRAINT `fk_fwdrule_provider` FOREIGN KEY (`id_provider`) REFERENCES `forwarding_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reglas que determinan qu├® clientes reenv├¡an pedidos a qu├® proveedor externo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historial_accesos`
--

DROP TABLE IF EXISTS `historial_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `pais_origen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `is_proxy` tinyint(1) DEFAULT 0,
  `device_os` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `device_browser` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `tipo` enum('gui','api') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'gui',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`created_at`),
  KEY `idx_ha_tipo` (`tipo`),
  KEY `idx_ha_pais` (`pais_origen`),
  CONSTRAINT `fk_ha_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1373 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `importaciones_csv`
--

DROP TABLE IF EXISTS `importaciones_csv`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `importaciones_csv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_importacion` datetime DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  `archivo_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_size_bytes` int(10) unsigned DEFAULT NULL,
  `tipo_plantilla` enum('basico','avanzado','ejemplo','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'custom',
  `filas_totales` int(10) unsigned DEFAULT 0,
  `filas_exitosas` int(10) unsigned DEFAULT 0,
  `filas_error` int(10) unsigned DEFAULT 0,
  `filas_advertencias` int(10) unsigned DEFAULT 0,
  `tiempo_procesamiento_segundos` decimal(10,3) DEFAULT NULL,
  `valores_defecto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Valores por defecto usados durante la importaci├│n: {estado, proveedor, moneda, vendedor}',
  `productos_creados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Lista de nombres de productos creados autom├íticamente durante la importaci├│n',
  `errores_detallados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de errores con l├¡neas y descripciones',
  `estado` enum('completado','parcial','fallido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'completado',
  `archivo_errores` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del archivo CSV con filas err├│neas (si existe)',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha_importacion`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `importaciones_csv_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario`
--

DROP TABLE IF EXISTS `inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `ubicacion` varchar(100) DEFAULT 'Principal' COMMENT 'Ubicaci├│n f├¡sica del inventario',
  `cantidad_disponible` int(11) DEFAULT 0 COMMENT 'Cantidad disponible para venta',
  `cantidad_reservada` int(11) DEFAULT 0 COMMENT 'Cantidad reservada en pedidos pendientes',
  `costo_promedio` decimal(10,2) DEFAULT NULL COMMENT 'Costo promedio ponderado del inventario',
  `ultima_entrada` timestamp NULL DEFAULT NULL COMMENT 'Fecha de ├║ltima entrada de stock',
  `ultima_salida` timestamp NULL DEFAULT NULL COMMENT 'Fecha de ├║ltima salida de stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_producto_ubicacion` (`id_producto`,`ubicacion`),
  KEY `idx_inventario_producto` (`id_producto`),
  KEY `idx_inventario_ubicacion` (`ubicacion`),
  KEY `idx_inventario_disponible` (`cantidad_disponible`),
  KEY `idx_inventario_producto_ubicacion` (`id_producto`,`ubicacion`),
  KEY `idx_inventario_disponible_producto` (`cantidad_disponible`,`id_producto`),
  CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3444 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventario consolidado por producto y ubicaci├│n';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logistics_queue`
--

DROP TABLE IF EXISTS `logistics_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistics_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo: generar_guia, actualizar_tracking, validar_direccion, notificar_estado',
  `pedido_id` int(11) NOT NULL COMMENT 'ID del pedido asociado',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Datos adicionales necesarios para procesar el trabajo',
  `status` enum('pending','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Estado actual del trabajo',
  `error_message` text DEFAULT NULL,
  `attempts` int(11) DEFAULT 0 COMMENT 'N├║mero de intentos realizados',
  `max_intentos` int(11) DEFAULT 5 COMMENT 'M├íximo de intentos permitidos',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora del pr├│ximo reintento',
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '├Ültimo mensaje de error capturado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de creaci├│n del trabajo',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '├Ültima actualizaci├│n',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora de procesamiento exitoso',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_job_type` (`job_type`),
  KEY `idx_pedido` (`pedido_id`),
  KEY `idx_retry` (`status`,`next_retry_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_composite_processing` (`status`,`next_retry_at`,`attempts`),
  CONSTRAINT `fk_logistics_queue_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1610 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monedas`
--

DROP TABLE IF EXISTS `monedas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monedas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `tasa_usd` decimal(10,4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `municipios`
--

DROP TABLE IF EXISTS `municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `municipios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_departamento` int(11) NOT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_departamento` (`id_departamento`),
  CONSTRAINT `municipios_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5264 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificaciones_logistica`
--

DROP TABLE IF EXISTS `notificaciones_logistica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones_logistica` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Usuario que recibe la notificaci├│n',
  `tipo` varchar(60) NOT NULL COMMENT 'pedido_creado|estado_cambiado|asignado|devuelto|reprogramado|comentario|incidencia',
  `titulo` varchar(255) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `pedido_id` int(10) unsigned DEFAULT NULL COMMENT 'FK l├│gica a pedidos.id',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Datos extra (estado_anterior, estado_nuevo, etc.)' CHECK (json_valid(`payload`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_pedido` (`pedido_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=82248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones internas del m├│dulo de log├¡stica';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paises`
--

DROP TABLE IF EXISTS `paises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo_iso` varchar(10) NOT NULL,
  `prefijo_postal` varchar(5) DEFAULT NULL,
  `id_moneda_local` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_paises_moneda` (`id_moneda_local`),
  CONSTRAINT `fk_paises_moneda` FOREIGN KEY (`id_moneda_local`) REFERENCES `monedas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedido_reservas_stock`
--

DROP TABLE IF EXISTS `pedido_reservas_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedido_reservas_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `liberada` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pedido_producto` (`id_pedido`,`id_producto`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_producto` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=11353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control de idempotencia para reservas de stock por pedido';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp(),
  `numero_orden` bigint(20) NOT NULL,
  `destinatario` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `precio_local` decimal(10,2) DEFAULT NULL,
  `observaciones_combo` text DEFAULT NULL,
  `precio_usd` decimal(10,2) DEFAULT NULL,
  `precio_total_local` decimal(10,2) DEFAULT NULL,
  `precio_total_usd` decimal(10,2) DEFAULT NULL,
  `tasa_conversion_usd` decimal(10,4) DEFAULT NULL,
  `subtotal_usd` decimal(10,2) DEFAULT NULL COMMENT 'Suma de subtotales de productos',
  `total_usd` decimal(10,2) DEFAULT NULL COMMENT 'Total final del pedido',
  `es_combo` tinyint(1) DEFAULT 1,
  `direccion` text DEFAULT NULL,
  `municipalitiesName` varchar(150) DEFAULT NULL COMMENT 'Nombre del municipio (pedidos especiales)',
  `postalCode` varchar(20) DEFAULT NULL COMMENT 'C├│digo postal (pedidos especiales)',
  `departmentName` varchar(150) DEFAULT NULL COMMENT 'Nombre del departamento (pedidos especiales)',
  `Location` varchar(255) DEFAULT NULL COMMENT 'Ubicaci├│n (pedidos especiales)',
  `betweenStreets` varchar(255) DEFAULT NULL COMMENT 'Entre calles (pedidos especiales)',
  `codigo_postal` varchar(20) DEFAULT NULL,
  `id_codigo_postal` int(11) DEFAULT NULL,
  `zona` text DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `coordenadas` point DEFAULT NULL,
  `id_estado` int(11) DEFAULT 1,
  `fecha_entrega` date DEFAULT NULL,
  `fecha_liquidacion` date DEFAULT NULL,
  `id_moneda` int(11) DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_municipio` int(11) DEFAULT NULL,
  `id_barrio` int(11) DEFAULT NULL,
  `id_pais` int(11) DEFAULT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `repartidor_updated_at` datetime DEFAULT NULL COMMENT 'Timestamp when repartidor made their one-time status update',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `bloqueado_edicion` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si es 1, solo admin puede editar el pedido. Si es 0, proveedor puede editar seg├║n reglas de negocio',
  `courier_service` varchar(150) DEFAULT NULL COMMENT 'Nombre del servicio courier para el env├¡o (ej: DHL, FedEx, UPS)',
  `code_city` int(11) NOT NULL COMMENT 'Codigos de ciudades',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pedidos_cliente_numero` (`id_cliente`,`numero_orden`),
  KEY `id_moneda` (`id_moneda`),
  KEY `fk_pedidos_municipio` (`id_municipio`),
  KEY `fk_pedidos_barrio` (`id_barrio`),
  KEY `fk_pedidos_pais` (`id_pais`),
  KEY `fk_pedidos_departamento` (`id_departamento`),
  KEY `idx_pedidos_estado_fecha` (`id_estado`,`fecha_ingreso`),
  KEY `idx_pedidos_proveedor_estado` (`id_proveedor`,`id_estado`),
  KEY `idx_pedidos_vendedor_estado` (`id_vendedor`,`id_estado`),
  KEY `idx_pedidos_fecha_prioridad` (`fecha_ingreso`),
  KEY `idx_pedidos_total` (`total_usd`),
  KEY `idx_pedidos_updated` (`updated_at`),
  KEY `idx_pedidos_telefono` (`telefono`),
  KEY `idx_pedidos_es_combo` (`es_combo`),
  KEY `idx_vendedor` (`id_vendedor`),
  KEY `idx_proveedor` (`id_proveedor`),
  KEY `idx_estado` (`id_estado`),
  KEY `idx_moneda` (`id_moneda`),
  KEY `idx_estado_fecha` (`id_estado`,`fecha_ingreso`),
  KEY `idx_proveedor_fecha` (`id_proveedor`,`fecha_ingreso`),
  KEY `id_cliente` (`id_cliente`),
  KEY `idx_bloqueado_edicion` (`bloqueado_edicion`),
  KEY `idx_pedidos_id_cp` (`id_codigo_postal`),
  CONSTRAINT `fk_pedidos_barrio` FOREIGN KEY (`id_barrio`) REFERENCES `barrios` (`id`),
  CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pedidos_cp` FOREIGN KEY (`id_codigo_postal`) REFERENCES `codigos_postales` (`id`),
  CONSTRAINT `fk_pedidos_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `fk_pedidos_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id`),
  CONSTRAINT `fk_pedidos_pais` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`),
  CONSTRAINT `fk_pedidos_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_estado`) REFERENCES `estados_pedidos` (`id`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_moneda`) REFERENCES `monedas` (`id`),
  CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15427 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Pedidos con campos de totales y prioridad';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_pedido_update_estado` AFTER UPDATE ON `pedidos` FOR EACH ROW BEGIN

    IF OLD.id_estado <> NEW.id_estado OR (OLD.id_estado IS NULL AND NEW.id_estado IS NOT NULL) THEN

        INSERT INTO pedidos_historial_estados (

            id_pedido,

            id_estado_anterior,

            id_estado_nuevo,

            id_usuario,

            observaciones

        ) VALUES (

            NEW.id,

            OLD.id_estado,

            NEW.id_estado,

            COALESCE(@current_user_id, 1),

            COALESCE(@current_observaciones, 'Estado cambiado autom├íticamente')

        );

    END IF;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `pedidos_historial_estados`
--

DROP TABLE IF EXISTS `pedidos_historial_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos_historial_estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_estado_anterior` int(11) DEFAULT NULL COMMENT 'Estado previo (NULL si es el primer estado)',
  `id_estado_nuevo` int(11) NOT NULL COMMENT 'Nuevo estado asignado',
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Usuario que realiz├│ el cambio',
  `observaciones` text DEFAULT NULL COMMENT 'Notas o comentarios sobre el cambio',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP desde donde se realiz├│ el cambio',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_estado_anterior` (`id_estado_anterior`),
  KEY `idx_historial_pedido` (`id_pedido`),
  KEY `idx_historial_estado_nuevo` (`id_estado_nuevo`),
  KEY `idx_historial_usuario` (`id_usuario`),
  KEY `idx_historial_fecha` (`created_at`),
  KEY `idx_historial_pedido_fecha` (`id_pedido`,`created_at`),
  KEY `idx_historial_estado_fecha` (`id_estado_nuevo`,`created_at`),
  CONSTRAINT `pedidos_historial_estados_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_historial_estados_ibfk_2` FOREIGN KEY (`id_estado_anterior`) REFERENCES `estados_pedidos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_historial_estados_ibfk_3` FOREIGN KEY (`id_estado_nuevo`) REFERENCES `estados_pedidos` (`id`),
  CONSTRAINT `pedidos_historial_estados_ibfk_4` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de cambios de estado de pedidos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedidos_productos`
--

DROP TABLE IF EXISTS `pedidos_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos_productos` (
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario_usd` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio unitario en USD al momento de la compra',
  `descuento_porcentaje` decimal(5,2) DEFAULT 0.00 COMMENT 'Descuento aplicado en porcentaje',
  `subtotal_usd` decimal(10,2) GENERATED ALWAYS AS ((`cantidad` - coalesce(`cantidad_devuelta`,0)) * `precio_unitario_usd` * (1 - coalesce(`descuento_porcentaje`,0) / 100)) STORED COMMENT 'Subtotal calculado autom├íticamente',
  `notas` text DEFAULT NULL COMMENT 'Notas espec├¡ficas del producto en el pedido',
  `cantidad_devuelta` int(11) DEFAULT 0,
  PRIMARY KEY (`id_pedido`,`id_producto`),
  KEY `idx_pedidos_productos_pedido` (`id_pedido`),
  KEY `idx_pedidos_productos_producto` (`id_producto`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_producto` (`id_producto`),
  CONSTRAINT `pedidos_productos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `pedidos_productos_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(100) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `unidad_medida` enum('unidad','kg','litro','metro','caja','paquete') DEFAULT 'unidad',
  `stock_minimo` int(11) DEFAULT 10 COMMENT 'Stock m├¡nimo para alerta',
  `stock_maximo` int(11) DEFAULT 100 COMMENT 'Stock m├íximo recomendado',
  `activo` tinyint(1) DEFAULT 1,
  `es_combo` tinyint(1) DEFAULT 0 COMMENT 'Flag que indica si el producto es un combo pre-empaquetado',
  `imagen_url` varchar(500) DEFAULT NULL,
  `id_usuario_creador` int(11) DEFAULT NULL COMMENT 'ID del usuario creador',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_usd` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_producto_categoria` (`categoria_id`),
  KEY `idx_producto_activo` (`activo`),
  KEY `idx_producto_sku` (`sku`),
  KEY `idx_producto_marca` (`marca`),
  KEY `idx_productos_categoria_activo` (`categoria_id`,`activo`),
  KEY `idx_productos_stock_activo` (`stock_minimo`,`activo`),
  KEY `idx_producto_usuario_creador` (`id_usuario_creador`),
  KEY `idx_productos_es_combo` (`es_combo`),
  CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_productos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_producto_usuario_creador` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL COMMENT 'FK l├│gica a usuarios.id',
  `endpoint` text NOT NULL COMMENT 'URL endpoint del push service',
  `p256dh` varchar(255) NOT NULL COMMENT 'Clave p├║blica del cliente (base64url)',
  `auth` varchar(255) NOT NULL COMMENT 'Auth secret del cliente (base64url)',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User-Agent del navegador al suscribirse',
  `contexto` varchar(50) DEFAULT NULL COMMENT 'logistica|admin|crm|etc',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_activo` (`id_usuario`,`activo`),
  KEY `idx_activo` (`activo`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suscripciones Web Push por usuario';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste','devolucion','transferencia') NOT NULL DEFAULT 'entrada',
  `referencia_tipo` enum('pedido','compra','ajuste_manual','devolucion','transferencia') DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL COMMENT 'ID del documento que gener├│ el movimiento',
  `motivo` varchar(255) DEFAULT NULL COMMENT 'Descripci├│n del motivo del movimiento',
  `ubicacion_origen` varchar(100) DEFAULT NULL,
  `ubicacion_destino` varchar(100) DEFAULT 'Principal',
  `costo_unitario` decimal(10,2) DEFAULT NULL COMMENT 'Costo unitario al momento del movimiento',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_stock_tipo_movimiento` (`tipo_movimiento`),
  KEY `idx_stock_referencia` (`referencia_tipo`,`referencia_id`),
  KEY `idx_stock_producto_fecha` (`id_producto`,`created_at`),
  KEY `idx_stock_ubicacion_destino` (`ubicacion_destino`),
  KEY `idx_stock_created_at` (`created_at`),
  KEY `idx_stock_producto_tipo_fecha` (`id_producto`,`tipo_movimiento`,`created_at`),
  KEY `idx_stock_fecha_tipo` (`created_at`,`tipo_movimiento`),
  CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`),
  CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3531 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Movimientos de inventario con trazabilidad completa';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_stock_insert` AFTER INSERT ON `stock` FOR EACH ROW BEGIN
    
    
    INSERT INTO inventario (id_producto, ubicacion, cantidad_disponible, ultima_entrada, ultima_salida, updated_at)
    VALUES (
        NEW.id_producto,
        'Principal',
        NEW.cantidad,
        IF(NEW.cantidad > 0, NOW(), NULL),
        IF(NEW.cantidad < 0, NOW(), NULL),
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        cantidad_disponible = cantidad_disponible + NEW.cantidad,
        ultima_entrada = IF(NEW.cantidad > 0, NOW(), ultima_entrada),
        ultima_salida = IF(NEW.cantidad < 0, NOW(), ultima_salida),
        updated_at = NOW();
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `id_pais` int(11) DEFAULT NULL,
  `id_moneda_local` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `id_estado` int(11) DEFAULT NULL,
  `token_enlace_publico` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `id_pais` (`id_pais`),
  KEY `idx_usuarios_moneda` (`id_moneda_local`),
  CONSTRAINT `fk_usuarios_moneda` FOREIGN KEY (`id_moneda_local`) REFERENCES `monedas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios_roles`
--

DROP TABLE IF EXISTS `usuarios_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_roles` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `fk_ur_rol` (`id_rol`),
  CONSTRAINT `fk_ur_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhooks_clientes`
--

DROP TABLE IF EXISTS `webhooks_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhooks_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `url_login` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `url_webhook` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `auth_user` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `auth_password` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `customers_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cliente` (`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhooks_log`
--

DROP TABLE IF EXISTS `webhooks_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhooks_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_webhook_cliente` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `numero_orden` varchar(50) DEFAULT NULL,
  `estado_enviado` varchar(100) DEFAULT NULL,
  `request_body` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `status` enum('ok','error','pending') DEFAULT 'pending',
  `intentos` int(11) DEFAULT 0,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `enviado_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_pedido` (`id_pedido`)
) ENGINE=InnoDB AUTO_INCREMENT=7105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'paquetes_apppack'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-24 10:41:48
