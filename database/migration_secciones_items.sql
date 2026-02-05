-- =============================================
-- AQUATIQ - Migración: Añadir secciones a ítems de evaluación
-- Añade columna `seccion` y asigna valores a los estilos de natación
-- =============================================

SET NAMES utf8mb4;

-- ---------------------------------------------
-- 1. Añadir columna seccion a items_evaluacion
-- ---------------------------------------------
ALTER TABLE `items_evaluacion` ADD COLUMN `seccion` VARCHAR(100) NULL DEFAULT NULL AFTER `texto`;

-- ---------------------------------------------
-- 2. Asignar secciones: CROL
-- ---------------------------------------------
SET @p_crol = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Crol');

UPDATE `items_evaluacion` SET `seccion` = 'POSICIÓN' WHERE `plantilla_id` = @p_crol AND `orden` IN (1, 2);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Entrada' WHERE `plantilla_id` = @p_crol AND `orden` IN (3, 4);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Tracción' WHERE `plantilla_id` = @p_crol AND `orden` IN (5, 6, 7);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Recobro' WHERE `plantilla_id` = @p_crol AND `orden` IN (8, 9, 10);
UPDATE `items_evaluacion` SET `seccion` = 'PIERNAS' WHERE `plantilla_id` = @p_crol AND `orden` IN (11, 12);
UPDATE `items_evaluacion` SET `seccion` = 'RESPIRACIÓN' WHERE `plantilla_id` = @p_crol AND `orden` IN (13, 14, 15);

-- ---------------------------------------------
-- 3. Asignar secciones: ESPALDA
-- ---------------------------------------------
SET @p_espalda = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Espalda');

UPDATE `items_evaluacion` SET `seccion` = 'POSICIÓN' WHERE `plantilla_id` = @p_espalda AND `orden` IN (1, 2, 3);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Entrada' WHERE `plantilla_id` = @p_espalda AND `orden` IN (4, 5, 6);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Tracción' WHERE `plantilla_id` = @p_espalda AND `orden` IN (7, 8);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Recobro' WHERE `plantilla_id` = @p_espalda AND `orden` IN (9, 10, 11, 12);
UPDATE `items_evaluacion` SET `seccion` = 'PIERNAS' WHERE `plantilla_id` = @p_espalda AND `orden` IN (13, 14);
UPDATE `items_evaluacion` SET `seccion` = 'COORDINACIÓN' WHERE `plantilla_id` = @p_espalda AND `orden` IN (15);

-- ---------------------------------------------
-- 4. Asignar secciones: BRAZA
-- ---------------------------------------------
SET @p_braza = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Braza');

UPDATE `items_evaluacion` SET `seccion` = 'POSICIÓN' WHERE `plantilla_id` = @p_braza AND `orden` IN (1);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS' WHERE `plantilla_id` = @p_braza AND `orden` IN (2, 3, 4);
UPDATE `items_evaluacion` SET `seccion` = 'PIERNAS - Batido' WHERE `plantilla_id` = @p_braza AND `orden` IN (5, 6);
UPDATE `items_evaluacion` SET `seccion` = 'PIERNAS - Recobro' WHERE `plantilla_id` = @p_braza AND `orden` IN (7);
UPDATE `items_evaluacion` SET `seccion` = 'COORDINACIÓN' WHERE `plantilla_id` = @p_braza AND `orden` IN (8, 9);

-- ---------------------------------------------
-- 5. Asignar secciones: MARIPOSA
-- ---------------------------------------------
SET @p_mariposa = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Mariposa');

UPDATE `items_evaluacion` SET `seccion` = 'POSICIÓN' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (1, 2);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Entrada' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (3);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Tracción' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (4);
UPDATE `items_evaluacion` SET `seccion` = 'BRAZOS - Recobro' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (5);
UPDATE `items_evaluacion` SET `seccion` = 'PIERNAS' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (6, 7);
UPDATE `items_evaluacion` SET `seccion` = 'COORDINACIÓN' WHERE `plantilla_id` = @p_mariposa AND `orden` IN (8, 9);
