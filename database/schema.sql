-- =============================================
-- AQUATIQ - Sistema de Evaluación de Natación
-- Script de creación de base de datos
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- Tabla: usuarios
-- ---------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` ENUM('superadmin', 'admin', 'monitor', 'coordinador', 'padre') NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: niveles
-- ---------------------------------------------
DROP TABLE IF EXISTS `niveles`;
CREATE TABLE `niveles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: grupos
-- ---------------------------------------------
DROP TABLE IF EXISTS `grupos`;
CREATE TABLE `grupos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `nivel_id` INT UNSIGNED NULL,
    `horario` VARCHAR(100) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`nivel_id`) REFERENCES `niveles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: alumnos
-- ---------------------------------------------
DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE `alumnos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `numero_usuario` VARCHAR(20) NULL,
    `nombre` VARCHAR(50) NOT NULL,
    `apellido1` VARCHAR(50) NOT NULL,
    `apellido2` VARCHAR(50) NULL,
    `fecha_nacimiento` DATE NULL,
    `grupo_id` INT UNSIGNED NULL,
    `padre_id` INT UNSIGNED NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`grupo_id`) REFERENCES `grupos`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`padre_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: monitores_grupos (relación N:M)
-- ---------------------------------------------
DROP TABLE IF EXISTS `monitores_grupos`;
CREATE TABLE `monitores_grupos` (
    `monitor_id` INT UNSIGNED NOT NULL,
    `grupo_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`monitor_id`, `grupo_id`),
    FOREIGN KEY (`monitor_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`grupo_id`) REFERENCES `grupos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: plantillas_evaluacion
-- ---------------------------------------------
DROP TABLE IF EXISTS `plantillas_evaluacion`;
CREATE TABLE `plantillas_evaluacion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nivel_id` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`nivel_id`) REFERENCES `niveles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: items_evaluacion
-- ---------------------------------------------
DROP TABLE IF EXISTS `items_evaluacion`;
CREATE TABLE `items_evaluacion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `texto` TEXT NOT NULL,
    `seccion` VARCHAR(100) NULL DEFAULT NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `activo` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`plantilla_id`) REFERENCES `plantillas_evaluacion`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: evaluaciones
-- ---------------------------------------------
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `alumno_id` INT UNSIGNED NOT NULL,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `monitor_id` INT UNSIGNED NOT NULL,
    `periodo` VARCHAR(20) NOT NULL COMMENT 'Ej: enero_2025, mayo_2025',
    `fecha` DATE NOT NULL,
    `recomendacion_nivel_id` INT UNSIGNED NULL,
    `observaciones` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`alumno_id`) REFERENCES `alumnos`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plantilla_id`) REFERENCES `plantillas_evaluacion`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`monitor_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recomendacion_nivel_id`) REFERENCES `niveles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- Tabla: respuestas
-- ---------------------------------------------
DROP TABLE IF EXISTS `respuestas`;
CREATE TABLE `respuestas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluacion_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `valor` ENUM('si', 'no', 'a_veces') NOT NULL,
    UNIQUE KEY `evaluacion_item` (`evaluacion_id`, `item_id`),
    FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items_evaluacion`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DATOS INICIALES
-- =============================================

-- ---------------------------------------------
-- Usuario superadmin por defecto
-- Password: admin123 (cambiar en producción)
-- ---------------------------------------------
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`) VALUES
('Administrador', 'admin@aquatiq.es', '$2y$12$1REus8EmMY.dyLbyuTzqM.btToTcktDI0EtSECBgXAR3UM9TrIIuy', 'superadmin');

-- ---------------------------------------------
-- Niveles
-- ---------------------------------------------
INSERT INTO `niveles` (`nombre`, `orden`) VALUES
('Burbujita', 1),
('Medusa', 2),
('Medusa Avanzado', 3),
('Tortuga', 4),
('Tortuga Avanzado', 5),
('Pececito', 6),
('Pececito Avanzado', 7),
('Tiburón', 8),
('Tiburón Avanzado', 9),
('Delfín', 10),
('Delfín Avanzado', 11),
('Crol', 12),
('Espalda', 13),
('Braza', 14),
('Mariposa', 15);

-- ---------------------------------------------
-- Plantillas de evaluación (una por nivel)
-- ---------------------------------------------
INSERT INTO `plantillas_evaluacion` (`nivel_id`, `nombre`) VALUES
(1, 'Evaluación Burbujita'),
(2, 'Evaluación Medusa'),
(3, 'Evaluación Medusa Avanzado'),
(4, 'Evaluación Tortuga'),
(5, 'Evaluación Tortuga Avanzado'),
(6, 'Evaluación Pececito'),
(7, 'Evaluación Pececito Avanzado'),
(8, 'Evaluación Tiburón'),
(9, 'Evaluación Tiburón Avanzado'),
(10, 'Evaluación Delfín'),
(11, 'Evaluación Delfín Avanzado'),
(12, 'Evaluación Crol'),
(13, 'Evaluación Espalda'),
(14, 'Evaluación Braza'),
(15, 'Evaluación Mariposa');

-- ---------------------------------------------
-- Ítems de evaluación por nivel
-- ---------------------------------------------

-- BURBUJITA (plantilla_id = 1)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(1, 'Está familiarizado/a con el medio acuático.', 1),
(1, 'Entra en el agua sin protestar.', 2),
(1, 'Le molesta el agua que salpica.', 3),
(1, 'Adapta instintivamente la respiración al medio acuático.', 4),
(1, 'Entra desde el borde con ayuda.', 5),
(1, 'Se adapta al uso del material.', 6),
(1, 'Se desplaza por el medio cogido de las manos.', 7),
(1, 'Se desplaza por el medio con ayuda de material y de su acompañante.', 8),
(1, 'Se desplaza por el medio con ayuda de material solo.', 9),
(1, 'Flota de espaldas con ayuda.', 10);

-- MEDUSA (plantilla_id = 2)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(2, 'Entra en el agua por sí solo.', 1),
(2, 'Le molesta el agua que salpica.', 2),
(2, 'Está familiarizado/a con el medio acuático.', 3),
(2, 'Adapta instintivamente la respiración al medio acuático.', 4),
(2, 'Se ha iniciado correctamente en la propulsión.', 5),
(2, 'Se desplaza con material de forma autónoma.', 6),
(2, 'Salta desde el borde con ayuda del profesor.', 7),
(2, 'Salta desde el borde sin ayuda del profesor.', 8),
(2, 'Sale a flote sin ayuda después de un salto.', 9),
(2, 'Se desplaza 1m sin material con ayuda del monitor.', 10),
(2, 'Recoge objetos a 20cm de profundidad sin ayuda.', 11),
(2, 'Coopera y respeta las normas, compañeros, material, etc.', 12);

-- MEDUSA AVANZADO (plantilla_id = 3)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(3, 'Le molesta meter la cara dentro del agua.', 1),
(3, 'Adapta instintivamente la respiración al medio acuático.', 2),
(3, 'Realiza correctamente el movimiento de propulsión.', 3),
(3, 'Ha conseguido la posición horizontal durante la propulsión.', 4),
(3, 'Se desplaza con material de forma autónoma 12m en el vaso grande.', 5),
(3, 'Salta desde el borde sin ayuda del profesor.', 6),
(3, 'Sale a flote sin ayuda después de un salto.', 7),
(3, 'Se desplaza 5m sin material con ayuda del monitor.', 8),
(3, 'Flota de espaldas con ayuda de material.', 9),
(3, 'Recoge objetos a 50cm de profundidad sin ayuda.', 10),
(3, 'Coopera y respeta las normas, compañeros, material, etc.', 11);

-- TORTUGA (plantilla_id = 4)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(4, 'Adapta instintivamente la respiración al medio acuático.', 1),
(4, 'Se ha iniciado correctamente en la propulsión.', 2),
(4, 'Ha adquirido la posición horizontal durante la propulsión.', 3),
(4, 'Se desplaza con material de forma autónoma al menos 12m en el vaso grande.', 4),
(4, 'Se desplaza sin ayuda de forma autónoma al menos 10m.', 5),
(4, 'Salta desde el borde sin ayuda de material auxiliar.', 6),
(4, 'Inicia el nado después del salto.', 7),
(4, 'Flota de frente y de espaldas con ayuda de material.', 8),
(4, 'Recoge objetos a 60cm de profundidad.', 9),
(4, 'Coopera y respeta las normas, compañeros, material, etc.', 10);

-- TORTUGA AVANZADO (plantilla_id = 5)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(5, 'Adapta instintivamente la respiración al medio acuático.', 1),
(5, 'Movimiento de propulsión correcto en posición horizontal.', 2),
(5, 'Se desplaza con material de forma autónoma al menos 25m en el vaso grande.', 3),
(5, 'Se desplaza sin ayuda de forma autónoma al menos 15m en el vaso grande.', 4),
(5, 'Salta desde el borde sin ayuda de material auxiliar.', 5),
(5, 'Inicia el nado después del salto.', 6),
(5, 'Conoce la "entrada de cabeza" y la practica a nivel básico.', 7),
(5, 'Flota de frente y de espaldas sin ayuda de material.', 8),
(5, 'Recoge objetos a 1m de profundidad.', 9),
(5, 'Coopera y respeta las normas, compañeros, material, etc.', 10);

-- PECECITO (plantilla_id = 6)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(6, 'Se propulsa ventral y dorsalmente correctamente en posición horizontal.', 1),
(6, 'Reconoce el estilo crol y lo practica a nivel básico.', 2),
(6, 'Reconoce el estilo espalda y lo practica a nivel básico.', 3),
(6, 'Está iniciado/a en la respiración lateral en el estilo crol.', 4),
(6, 'Se desplaza sin problemas 25m a crol.', 5),
(6, 'Se desplaza sin problemas 25m a espalda.', 6),
(6, 'Bucea 10m sin problemas.', 7),
(6, 'Recoge objetos a 1,5m de profundidad.', 8),
(6, 'Conoce la "entrada de cabeza" y la practica sin problemas.', 9),
(6, 'Coopera y respeta las normas, compañeros, material, etc.', 10);

-- PECECITO AVANZADO (plantilla_id = 7)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(7, 'Controla la respiración boca – boca/nariz.', 1),
(7, 'Reconoce el estilo crol y lo practica coordinadamente.', 2),
(7, 'Utiliza la respiración lateral en el estilo crol.', 3),
(7, 'Reconoce el estilo espalda y lo practica coordinadamente.', 4),
(7, 'Reconoce el estilo braza y lo practica a nivel básico.', 5),
(7, 'Se desplaza sin problemas 50m a crol con respiración lateral.', 6),
(7, 'Se desplaza sin problemas 50m a espalda.', 7),
(7, 'Se desplaza sin problemas 25m a braza.', 8),
(7, 'Recoge objetos a cualquier profundidad.', 9),
(7, 'Realiza la "entrada de cabeza" correctamente.', 10),
(7, 'Coopera y respeta las normas, compañeros, material, etc.', 11);

-- TIBURÓN (plantilla_id = 8)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(8, 'Controla la respiración boca – boca/nariz.', 1),
(8, 'Nada estilo crol coordinadamente con respiración bilateral.', 2),
(8, 'Se desplaza 50m sin problemas crol con respiración bilateral.', 3),
(8, 'Nada estilo espalda coordinadamente.', 4),
(8, 'Se desplaza a espalda sin ayuda de los brazos.', 5),
(8, 'Se desplaza 50m sin problemas espalda.', 6),
(8, 'Reconoce el estilo braza y lo practica a nivel básico.', 7),
(8, 'Se desplaza 50m sin problemas braza.', 8),
(8, 'Realiza la entrada de cabeza correctamente.', 9),
(8, 'Las inmersiones a cualquier profundidad no le suponen dificultad.', 10),
(8, 'Coopera y respeta las normas, compañeros, material, etc.', 11);

-- TIBURÓN AVANZADO (plantilla_id = 9)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(9, 'Controla la respiración boca – boca/nariz.', 1),
(9, 'Nada estilo crol correctamente con respiración bilateral.', 2),
(9, 'Se desplaza 100m crol sin problemas.', 3),
(9, 'Nada estilo espalda coordinadamente, manteniendo la posición horizontal del cuerpo.', 4),
(9, 'Se desplaza 100m espalda sin problemas.', 5),
(9, 'Reconoce el estilo braza y lo practica coordinadamente.', 6),
(9, 'Se desplaza 100m braza sin problemas.', 7),
(9, 'Realiza la "entrada de cabeza" desde el poyete o trampolín de forma correcta.', 8),
(9, 'Reconoce y practica de forma básica el viraje de crol.', 9),
(9, 'Coopera y respeta las normas, compañeros, material, etc.', 10);

-- DELFÍN (plantilla_id = 10)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(10, 'Controla la respiración boca – boca/nariz.', 1),
(10, 'Nada 200m crol respirando coordinadamente cada 3 brazadas, con respiración bilateral.', 2),
(10, 'Nada 200m espalda con una fase aérea de la brazada correcta, posición corporal horizontal estable y estilo coordinado.', 3),
(10, 'Nada 100m braza coordinando brazos, piernas y respiración.', 4),
(10, 'Realiza la "entrada de cabeza" desde el poyete o trampolín correctamente.', 5),
(10, 'Reconoce y practica correctamente el viraje de crol.', 6),
(10, 'Reconoce y practica de forma básica el viraje de espalda.', 7),
(10, 'Coopera y respeta las normas, compañeros, material, etc.', 8);

-- DELFÍN AVANZADO (plantilla_id = 11)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(11, 'Nada 300m crol coordinadamente con respiración bilateral utilizando el viraje.', 1),
(11, 'Mantiene una correcta posición lateral en el rolido durante la respiración.', 2),
(11, 'Batido de piernas correcto y continuo en el estilo crol.', 3),
(11, 'Entrada de la mano en la línea hombro/muñeca en el estilo crol.', 4),
(11, 'Nada 300m espalda con posición corporal horizontal estable y estilo coordinado.', 5),
(11, 'Fase aérea de la brazada correcta.', 6),
(11, 'Batido de piernas correcto y continuo en el estilo espalda.', 7),
(11, 'Reconoce y practica correctamente el viraje de espalda.', 8),
(11, 'Nada 100m braza coordinando correctamente brazos, piernas y respiración.', 9),
(11, 'Coopera y respeta las normas, compañeros, material, etc.', 10);

-- CROL (plantilla_id = 12)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(12, 'Lo más posible horizontalmente.', 1),
(12, 'Rotación lateral de la cabeza y hombros (rolido 35-45º).', 2),
(12, 'La mano entra suavemente y delante del hombro.', 3),
(12, 'Se extiende el brazo hacia adelante antes de iniciar la tracción.', 4),
(12, 'El codo se mantiene más alto que la mano durante las fases de agarre y tracción (propulsión efectiva).', 5),
(12, 'Movimiento recto hacia atrás paralelo al cuerpo.', 6),
(12, 'Extensión completa del brazo al final de la tracción.', 7),
(12, 'El codo es el primero en salir del agua.', 8),
(12, 'Posición alta codo con respecto a la mano.', 9),
(12, 'Mano relajada.', 10),
(12, 'El movimiento es alternado, rítmico, y se origina desde la cadera (efecto látigo).', 11),
(12, 'Pies en flexión plantar (pies estirados) y ligera rotación interna.', 12),
(12, 'El giro de la cabeza es lateral, solo para sacar la boca del agua, manteniendo un ojo en el agua.', 13),
(12, 'Exhala totalmente (burbujea) de forma continua y completa bajo el agua antes de girar para inspirar.', 14),
(12, 'La toma de aire se realiza en el momento de máximo rolido y cuando el brazo del lado que respira está iniciando el recobro.', 15);

-- ESPALDA (plantilla_id = 13)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(13, 'Mantiene el cuerpo plano y horizontal en la superficie. La cadera se mantiene alta (no hundida).', 1),
(13, 'La cabeza está quieta, alineada con la columna vertebral (mirada al techo, orejas en el agua).', 2),
(13, 'Existe una rotación lateral adecuada del tronco y hombros (aprox. 45°), que ayuda a la tracción y el recobro.', 3),
(13, 'Brazo en prolongación del hombro.', 4),
(13, 'Giro de la mano (palma) hacia afuera.', 5),
(13, 'Dedo meñique es el primero en entrar.', 6),
(13, 'El brazo inicia el agarre con el codo flexionado bajo el agua y pegado al cuerpo (para generar propulsión).', 7),
(13, 'La mano realiza un empuje completo hacia atrás, terminando el movimiento cerca del muslo.', 8),
(13, 'El primero en salir es el pulgar.', 9),
(13, 'Brazo extendido y relajado.', 10),
(13, 'El brazo pasa cerca de la oreja.', 11),
(13, 'Giro de la palma hacia afuera.', 12),
(13, 'El movimiento es alternado, continuo y se origina desde la cadera (látigo).', 13),
(13, 'La patada rompe ligeramente la superficie del agua (salpicadura mínima), sin gran amplitud, con los pies en flexión plantar.', 14),
(13, 'Correcta oposición de brazos: un brazo está entrando al agua mientras el otro está a punto de empujar.', 15);

-- BRAZA (plantilla_id = 14)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(14, 'Cuerpo horizontal en el momento del deslizamiento. Cabeza alineada, mirando hacia abajo.', 1),
(14, 'Las manos se juntan rápidamente debajo del pecho o barbilla y se proyectan hacia adelante, manteniendo el cuerpo hidrodinámico.', 2),
(14, 'Las manos se dirigen hacia afuera y un poco hacia abajo. Los codos se mantienen altos inicialmente.', 3),
(14, 'Manos y antebrazos barren vigorosamente hacia el centro, sin pasar de la línea de los hombros.', 4),
(14, 'Los pies están en flexión dorsal (tobillos flexionados) y rotados hacia afuera al iniciar el barrido.', 5),
(14, 'El empuje es fuerte hacia afuera y luego atrás (movimiento circular), terminando con las piernas estiradas y pies juntos.', 6),
(14, 'Flexión de rodillas y aproximación de los talones a los glúteos con ligera rotación externa.', 7),
(14, 'Los brazos terminan su recobro antes de que las piernas inicien su empuje propulsor.', 8),
(14, 'Sigue la secuencia: Brazos y Respiración → Deslizamiento → Patada → Deslizamiento (máxima pausa).', 9);

-- MARIPOSA (plantilla_id = 15)
INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
(15, 'Posición horizontal.', 1),
(15, 'Movimiento ondulatorio (cabeza, tronco y piernas).', 2),
(15, 'Los brazos entran simultáneamente a la anchura de los hombros, con las palmas ligeramente hacia afuera.', 3),
(15, 'La trayectoria subacuática es un doble barrido lateral (hacia afuera y luego hacia adentro) para maximizar la propulsión.', 4),
(15, 'Los brazos salen del agua simultáneamente y se proyectan rectos hacia adelante, justo por encima de la superficie.', 5),
(15, 'El movimiento de látigo es potente y continuo, originándose principalmente desde la cadera y el torso.', 6),
(15, 'Pies relajados y tobillos en extensión plantar para un batido efectivo (como una aleta).', 7),
(15, 'Primera Patada: Fuerte, se realiza cuando las manos entran en el agua. Segunda Patada: Más ligera, se realiza al final del empuje de los brazos, justo antes del recobro.', 8),
(15, 'Respiración: la cabeza se levanta hacia adelante (o ligeramente lateral) de forma natural, en sincronía con el inicio del recobro de los brazos.', 9);
