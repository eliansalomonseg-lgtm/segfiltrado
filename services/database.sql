CREATE DATABASE IF NOT EXISTS `seg`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `seg`;

CREATE TABLE IF NOT EXISTS `escuelas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CCT` VARCHAR(50) NOT NULL UNIQUE,
  `NOMBRECT` VARCHAR(255) NOT NULL,
  `DOMICILIO` VARCHAR(255) NULL,
  `NOMBREMUN` VARCHAR(255) NULL,
  `NOMBRELOC` VARCHAR(255) NULL,
  `STATUS` VARCHAR(20) NULL,
  `SUBNIVEL` VARCHAR(100) NULL,
  `NIVEL` VARCHAR(100) NULL,
  `HOMO` VARCHAR(30) NULL,
  `TURNO` VARCHAR(100) NULL,
  `ZONA` VARCHAR(50) NULL,
  `SECTOR` VARCHAR(50) NULL,
  `ORIGEN` VARCHAR(80) NULL,
  `TIPOCT` TEXT NULL,
  `TURNO_CV` TEXT NULL,
  `TURNO2` TEXT NULL,
  `TURNO2_DES` TEXT NULL,
  `STATUS_DES` TEXT NULL,
  `MPIO` TEXT NULL,
  `LOC` TEXT NULL,
  `AMBITO` TEXT NULL,
  `COLONIA` TEXT NULL,
  `NOMBRECOL` TEXT NULL,
  `ENTRECALLE` TEXT NULL,
  `YCALLE` TEXT NULL,
  `CALLEPOST` TEXT NULL,
  `CODPOST` TEXT NULL,
  `LATITUD` TEXT NULL,
  `LONGITUD` TEXT NULL,
  `CV_INMUEBLE` TEXT NULL,
  `MARGINACION` TEXT NULL,
  `CCT_ZONA` TEXT NULL,
  `CCT_SECTOR` TEXT NULL,
  `SERREG` TEXT NULL,
  `CCT_SERREG` TEXT NULL,
  `TIPO` TEXT NULL,
  `SERVICIO` TEXT NULL,
  `SERVICIO_DES` TEXT NULL,
  `CV_CARACT` TEXT NULL,
  `CARACTERISTICA` TEXT NULL,
  `SOST_CONTROL` TEXT NULL,
  `SOSTENIMIENTO` TEXT NULL,
  `SOSTENIMIENTO_DES` TEXT NULL,
  `NOM_DIR` TEXT NULL,
  `APELLIDO1` TEXT NULL,
  `APELLIDO2` TEXT NULL,
  `CURP` TEXT NULL,
  `RFC` TEXT NULL,
  `TELEFONO1` TEXT NULL,
  `CELULAR1` TEXT NULL,
  `CORREOELE` TEXT NULL,
  `PAGINAWEB` TEXT NULL,
  `ADM_DES` TEXT NULL,
  `NOR_DES` TEXT NULL,
  `OPERAT_DES` TEXT NULL,
  `FECHAFUNDA` TEXT NULL,
  `FECHAALTA` TEXT NULL,
  `FECHACLAUS` TEXT NULL,
  `FECHAREAPE` TEXT NULL,
  `FECHAACTUA` TEXT NULL,
  `CLAVE_ALTERNA` TEXT NULL,
  `CV_TURNO` TEXT NULL,
  `CV_MUN` TEXT NULL,
  `CV_LOC` TEXT NULL,
  `C_NOM_VIALIDAD` TEXT NULL,
  `N_EXTNUM` TEXT NULL,
  `CONTROL` TEXT NULL,
  `SUBCONTROL` TEXT NULL,
  `C_CARACTERIZAN2` TEXT NULL,
  `JEFSEC` TEXT NULL,
  `SERVREG` TEXT NULL,
  `REGION` TEXT NULL,
  `CV_ESTATUS_CAPTURA` TEXT NULL,
  `HOMBRE` TEXT NULL,
  `MUJER` TEXT NULL,
  `TOTAL` TEXT NULL,
  `GRUPOS` TEXT NULL,
  `LENGUA` TEXT NULL,
  `CLASIFICACION` VARCHAR(120) NULL,
  `DATOS_SEG_JSON` LONGTEXT NULL,
  `DATOS_OFICIALIZACION_JSON` LONGTEXT NULL,
  INDEX `idx_escuelas_subnivel` (`SUBNIVEL`),
  INDEX `idx_escuelas_status` (`STATUS`),
  INDEX `idx_escuelas_localidad_municipio` (`NOMBRELOC`, `NOMBREMUN`),
  INDEX `idx_escuelas_municipio` (`NOMBREMUN`),
  INDEX `idx_escuelas_municipio_localidad` (`NOMBREMUN`, `NOMBRELOC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `catalogo_columnas` (
  `fuente` VARCHAR(40) NOT NULL,
  `posicion` INT NOT NULL,
  `columna_original` VARCHAR(255) NOT NULL,
  `columna_bd` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`fuente`, `posicion`),
  UNIQUE KEY `uniq_catalogo_columna` (`fuente`, `columna_bd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_precarga` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `RPU` VARCHAR(20) NOT NULL,
  `nombre_cfe` VARCHAR(255) NULL,
  `poblacion_cfe` VARCHAR(255) NULL,
  `tarifa_cfe` VARCHAR(10) NULL,
  `periodo_vence` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `escuelas_rpu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `CCT` VARCHAR(50) NOT NULL,
  `escuela_id` BIGINT UNSIGNED NULL,
  `RPU` VARCHAR(20) NOT NULL,
  `nombre_recibo_cfe` VARCHAR(255) NULL,
  `poblacion_cfe` VARCHAR(255) NULL,
  `tarifa_cfe` VARCHAR(10) NULL,
  UNIQUE KEY `uniq_escuela_rpu` (`CCT`, `RPU`),
  KEY `idx_escuelas_rpu_rpu` (`RPU`),
  KEY `idx_escuelas_rpu_escuela_id` (`escuela_id`),
  FOREIGN KEY (`CCT`) REFERENCES `escuelas`(`CCT`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `escuelas_fuentes` (
  `escuela_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `catalogo_seg_id` BIGINT NULL,
  `catalogo_oficializacion_id` BIGINT NULL,
  KEY `idx_escuelas_fuentes_seg` (`catalogo_seg_id`),
  KEY `idx_escuelas_fuentes_oficializacion` (`catalogo_oficializacion_id`),
  FOREIGN KEY (`escuela_id`) REFERENCES `escuelas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_reportes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `archivo` VARCHAR(255) NOT NULL,
  `anio` INT NOT NULL,
  `mes` INT NOT NULL,
  `modo_periodo` VARCHAR(20) NOT NULL,
  `total_registros` INT NOT NULL DEFAULT 0,
  `con_alerta` INT NOT NULL DEFAULT 0,
  `severos` INT NOT NULL DEFAULT 0,
  `periodo_correcto` INT NOT NULL DEFAULT 0,
  `ajuste_muchos_dias` INT NOT NULL DEFAULT 0,
  `periodo_correcto_con_aumento` INT NOT NULL DEFAULT 0,
  `sin_alerta_con_aumento` INT NOT NULL DEFAULT 0,
  `importe_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cfe_reportes_periodo` (`anio`, `mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_consumos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reporte_id` INT NOT NULL,
  `RPU` VARCHAR(20) NOT NULL,
  `CCT` VARCHAR(50) NULL,
  `escuela_id` BIGINT UNSIGNED NULL,
  `division_cfe` VARCHAR(80) NULL,
  `nombre_cfe` VARCHAR(255) NULL,
  `direccion_cfe` VARCHAR(255) NULL,
  `poblacion_cfe` VARCHAR(255) NULL,
  `tarifa_cfe` VARCHAR(10) NULL,
  `tipo_periodo` VARCHAR(20) NULL,
  `desde` DATE NULL,
  `hasta` DATE NULL,
  `dias` INT NULL,
  `consumo` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `demanda` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `reactivos` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `factor_potencia` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `factor_carga` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `energia` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `iva` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `dap` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `cargos_depositos` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `creditos_redondeos` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `formula_validacion` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `diferencia` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `severidad` INT NOT NULL DEFAULT 0,
  `alertas` TEXT NULL,
  `tipo_movimiento` VARCHAR(2) NULL,
  `tipo_facturacion` VARCHAR(30) NULL,
  `medidor` VARCHAR(100) NULL,
  `lectura_anterior` DECIMAL(18,4) NULL,
  `lectura_actual` DECIMAL(18,4) NULL,
  `multiplicador` DECIMAL(18,4) NULL,
  `adeudo_anterior` DECIMAL(14,2) NULL,
  `numero_adeudo` VARCHAR(100) NULL,
  `fecha_facturacion` DATE NULL,
  `fecha_limite_pago` DATE NULL,
  `enriquecido_plano` TINYINT(1) NOT NULL DEFAULT 0,
  `archivo_plano_id` INT NULL,
  `enriquecido_plano_en` DATETIME NULL,
  INDEX `idx_cfe_consumos_rpu` (`RPU`),
  INDEX `idx_cfe_consumos_rpu_id` (`RPU`, `id`),
  INDEX `idx_cfe_consumos_cct` (`CCT`),
  INDEX `idx_cfe_consumos_reporte` (`reporte_id`),
  INDEX `idx_cfe_consumos_rpu_cct_hasta` (`RPU`, `CCT`, `hasta`),
  INDEX `idx_cfe_consumos_rpu_reporte_id` (`RPU`, `reporte_id`, `id`),
  INDEX `idx_cfe_consumos_rpu_periodo` (`RPU`, `desde`, `hasta`),
  INDEX `idx_cfe_consumos_archivo_plano` (`archivo_plano_id`),
  FOREIGN KEY (`reporte_id`) REFERENCES `cfe_reportes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`CCT`) REFERENCES `escuelas`(`CCT`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_archivos_planos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_archivo` VARCHAR(255) NOT NULL,
  `hash_archivo` CHAR(64) NOT NULL,
  `anio` SMALLINT NULL,
  `mes` TINYINT NULL,
  `usuario_id` INT NULL,
  `total_registros` INT NOT NULL DEFAULT 0,
  `conciliados` INT NOT NULL DEFAULT 0,
  `no_conciliados` INT NOT NULL DEFAULT 0,
  `historicos_sin_factura` INT NOT NULL DEFAULT 0,
  `con_diferencia_consumo` INT NOT NULL DEFAULT 0,
  `con_diferencia_total` INT NOT NULL DEFAULT 0,
  `errores_formato` INT NOT NULL DEFAULT 0,
  `movimientos_01` INT NOT NULL DEFAULT 0,
  `movimientos_04` INT NOT NULL DEFAULT 0,
  `movimientos_06` INT NOT NULL DEFAULT 0,
  `movimientos_09` INT NOT NULL DEFAULT 0,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cfe_archivos_planos_hash` (`hash_archivo`),
  INDEX `idx_cfe_archivos_planos_periodo` (`anio`, `mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_plano_conciliaciones` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `archivo_plano_id` INT NOT NULL,
  `fila_origen` INT NOT NULL,
  `consumo_id` INT NULL,
  `estado` VARCHAR(30) NOT NULL,
  `RPU` VARCHAR(20) NULL,
  `desde` DATE NULL,
  `hasta` DATE NULL,
  `consumo_plano` DECIMAL(14,2) NULL,
  `total_plano` DECIMAL(14,2) NULL,
  `tipo_movimiento` VARCHAR(2) NULL,
  `diferencia_consumo` DECIMAL(14,2) NULL,
  `diferencia_total` DECIMAL(14,2) NULL,
  `detalle` VARCHAR(255) NULL,
  `datos_json` LONGTEXT NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cfe_plano_archivo_fila` (`archivo_plano_id`, `fila_origen`),
  INDEX `idx_cfe_plano_conciliaciones_consumo` (`consumo_id`),
  INDEX `idx_cfe_plano_conciliaciones_estado` (`estado`),
  INDEX `idx_cfe_plano_conciliaciones_rpu` (`RPU`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_lecturas_medidores` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `archivo_plano_id` INT NOT NULL,
  `fila_origen` INT NOT NULL,
  `consumo_id` INT NULL,
  `RPU` VARCHAR(20) NOT NULL,
  `tipo_medidor` VARCHAR(12) NOT NULL,
  `posicion` TINYINT UNSIGNED NOT NULL,
  `numero_medidor` VARCHAR(100) NULL,
  `caratula` VARCHAR(50) NULL,
  `ley_medidor` VARCHAR(100) NULL,
  `anomalia` VARCHAR(100) NULL,
  `lectura_anterior` DECIMAL(18,4) NULL,
  `lectura_actual` DECIMAL(18,4) NULL,
  `diferencia_lectura` DECIMAL(18,4) NULL,
  `multiplicador` DECIMAL(18,4) NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cfe_lecturas_medidor_origen` (`archivo_plano_id`, `fila_origen`, `tipo_medidor`, `posicion`),
  INDEX `idx_cfe_lecturas_medidores_consumo` (`consumo_id`),
  INDEX `idx_cfe_lecturas_medidores_rpu_numero` (`RPU`, `numero_medidor`),
  INDEX `idx_cfe_lecturas_medidores_archivo` (`archivo_plano_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_plano_detalles` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `archivo_plano_id` INT NOT NULL,
  `fila_origen` INT NOT NULL,
  `consumo_id` INT NULL,
  `RPU` VARCHAR(20) NOT NULL,
  `direccion_plano` VARCHAR(255) NULL,
  `poblacion_plano` VARCHAR(255) NULL,
  `municipio_plano` VARCHAR(255) NULL,
  `estado_plano` VARCHAR(100) NULL,
  `colonia_plano` VARCHAR(255) NULL,
  `calle_1` VARCHAR(255) NULL,
  `calle_2` VARCHAR(255) NULL,
  `carga_contratada` DECIMAL(18,4) NULL,
  `carga_conectada` DECIMAL(18,4) NULL,
  `medidores_instalados_declarados` TINYINT UNSIGNED NULL,
  `medidores_retirados_declarados` TINYINT UNSIGNED NULL,
  `tipo_estimacion` VARCHAR(50) NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cfe_plano_detalles_origen` (`archivo_plano_id`, `fila_origen`),
  INDEX `idx_cfe_plano_detalles_consumo` (`consumo_id`),
  INDEX `idx_cfe_plano_detalles_rpu` (`RPU`),
  INDEX `idx_cfe_plano_detalles_ubicacion` (`poblacion_plano`, `municipio_plano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_plano_historico` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `archivo_plano_id` INT NOT NULL,
  `fila_origen` INT NOT NULL,
  `RPU` VARCHAR(20) NOT NULL,
  `desde` DATE NOT NULL,
  `hasta` DATE NOT NULL,
  `fecha_facturacion` DATE NULL,
  `fecha_limite_pago` DATE NULL,
  `division_cfe` VARCHAR(100) NULL,
  `nombre_cfe` VARCHAR(255) NULL,
  `direccion_cfe` VARCHAR(255) NULL,
  `poblacion_cfe` VARCHAR(255) NULL,
  `tarifa_cfe` VARCHAR(30) NULL,
  `tipo_facturacion` VARCHAR(30) NULL,
  `tipo_movimiento` VARCHAR(2) NULL,
  `consumo` DECIMAL(14,2) NULL,
  `energia` DECIMAL(14,2) NULL,
  `iva` DECIMAL(14,2) NULL,
  `dap` DECIMAL(14,2) NULL,
  `cargos_depositos` DECIMAL(14,2) NULL,
  `creditos_redondeos` DECIMAL(14,2) NULL,
  `total` DECIMAL(14,2) NULL,
  `adeudo_anterior` DECIMAL(14,2) NULL,
  `numero_adeudo` VARCHAR(100) NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cfe_plano_historico_origen` (`archivo_plano_id`, `fila_origen`),
  INDEX `idx_cfe_plano_historico_rpu_periodo` (`RPU`, `desde`, `hasta`),
  INDEX `idx_cfe_plano_historico_archivo` (`archivo_plano_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cfe_documentos_pdf` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `anio` SMALLINT UNSIGNED NOT NULL,
  `mes` TINYINT UNSIGNED NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `ruta_archivo` VARCHAR(255) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  `tamano_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `usuario_id` INT UNSIGNED NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cfe_documentos_periodo` (`anio`, `mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(30) NOT NULL,
  `descripcion` VARCHAR(120) NULL,
  UNIQUE KEY `uniq_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(60) NOT NULL,
  `contrasena_hash` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido_paterno` VARCHAR(100) NOT NULL,
  `apellido_materno` VARCHAR(100) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_usuarios_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `usuario_roles` (
  `usuario_id` INT UNSIGNED NOT NULL,
  `rol_id` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`usuario_id`, `rol_id`),
  CONSTRAINT `fk_usuario_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`nombre`, `descripcion`) VALUES
  ('admin', 'Acceso completo, incluida consolidacion'),
  ('consultor', 'Consulta y operacion sin consolidacion')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);

INSERT INTO `usuarios` (`usuario`, `contrasena_hash`, `nombre`, `apellido_paterno`, `apellido_materno`)
SELECT 'admin', '$2y$10$BhrtHSS9m0V25cjgjWvocuqgSNTZCtFuaMHNQy2EZsnfjscSxYixm', 'Administrador', 'SEG', ''
WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `usuario` = 'admin');

INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`)
SELECT u.id, r.id
FROM `usuarios` u
INNER JOIN `roles` r ON r.nombre = 'admin'
WHERE u.usuario = 'admin'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);
