-- =============================================
-- AQUATIQ - Migración: Evaluaciones de Estilos de Natación
-- Añade 4 nuevos niveles (Crol, Espalda, Braza, Mariposa)
-- con sus plantillas de evaluación e ítems
-- =============================================

SET NAMES utf8mb4;

-- ---------------------------------------------
-- 1. Nuevos niveles
-- ---------------------------------------------
INSERT INTO `niveles` (`nombre`, `orden`) VALUES
('Crol', 12),
('Espalda', 13),
('Braza', 14),
('Mariposa', 15);

-- ---------------------------------------------
-- 2. Plantillas de evaluación (una por nivel)
-- ---------------------------------------------
INSERT INTO `plantillas_evaluacion` (`nivel_id`, `nombre`) VALUES
((SELECT id FROM niveles WHERE nombre = 'Crol'), 'Evaluación Crol'),
((SELECT id FROM niveles WHERE nombre = 'Espalda'), 'Evaluación Espalda'),
((SELECT id FROM niveles WHERE nombre = 'Braza'), 'Evaluación Braza'),
((SELECT id FROM niveles WHERE nombre = 'Mariposa'), 'Evaluación Mariposa');

-- ---------------------------------------------
-- 3. Ítems: CROL (15 ítems)
-- ---------------------------------------------
SET @plantilla_crol = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Crol');

INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
-- POSICIÓN
(@plantilla_crol, 'Lo más posible horizontalmente.', 1),
(@plantilla_crol, 'Rotación lateral de la cabeza y hombros (rolido 35-45º).', 2),
-- BRAZOS - Entrada
(@plantilla_crol, 'La mano entra suavemente y delante del hombro.', 3),
(@plantilla_crol, 'Se extiende el brazo hacia adelante antes de iniciar la tracción.', 4),
-- BRAZOS - Tracción
(@plantilla_crol, 'El codo se mantiene más alto que la mano durante las fases de agarre y tracción (propulsión efectiva).', 5),
(@plantilla_crol, 'Movimiento recto hacia atrás paralelo al cuerpo.', 6),
(@plantilla_crol, 'Extensión completa del brazo al final de la tracción.', 7),
-- BRAZOS - Recobro
(@plantilla_crol, 'El codo es el primero en salir del agua.', 8),
(@plantilla_crol, 'Posición alta codo con respecto a la mano.', 9),
(@plantilla_crol, 'Mano relajada.', 10),
-- PIERNAS
(@plantilla_crol, 'El movimiento es alternado, rítmico, y se origina desde la cadera (efecto látigo).', 11),
(@plantilla_crol, 'Pies en flexión plantar (pies estirados) y ligera rotación interna.', 12),
-- RESPIRACIÓN
(@plantilla_crol, 'El giro de la cabeza es lateral, solo para sacar la boca del agua, manteniendo un ojo en el agua.', 13),
(@plantilla_crol, 'Exhala totalmente (burbujea) de forma continua y completa bajo el agua antes de girar para inspirar.', 14),
(@plantilla_crol, 'La toma de aire se realiza en el momento de máximo rolido y cuando el brazo del lado que respira está iniciando el recobro.', 15);

-- ---------------------------------------------
-- 4. Ítems: ESPALDA (15 ítems)
-- ---------------------------------------------
SET @plantilla_espalda = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Espalda');

INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
-- POSICIÓN
(@plantilla_espalda, 'Mantiene el cuerpo plano y horizontal en la superficie. La cadera se mantiene alta (no hundida).', 1),
(@plantilla_espalda, 'La cabeza está quieta, alineada con la columna vertebral (mirada al techo, orejas en el agua).', 2),
(@plantilla_espalda, 'Existe una rotación lateral adecuada del tronco y hombros (aprox. 45°), que ayuda a la tracción y el recobro.', 3),
-- BRAZOS - Entrada
(@plantilla_espalda, 'Brazo en prolongación del hombro.', 4),
(@plantilla_espalda, 'Giro de la mano (palma) hacia afuera.', 5),
(@plantilla_espalda, 'Dedo meñique es el primero en entrar.', 6),
-- BRAZOS - Tracción
(@plantilla_espalda, 'El brazo inicia el agarre con el codo flexionado bajo el agua y pegado al cuerpo (para generar propulsión).', 7),
(@plantilla_espalda, 'La mano realiza un empuje completo hacia atrás, terminando el movimiento cerca del muslo.', 8),
-- BRAZOS - Recobro
(@plantilla_espalda, 'El primero en salir es el pulgar.', 9),
(@plantilla_espalda, 'Brazo extendido y relajado.', 10),
(@plantilla_espalda, 'El brazo pasa cerca de la oreja.', 11),
(@plantilla_espalda, 'Giro de la palma hacia afuera.', 12),
-- PIERNAS
(@plantilla_espalda, 'El movimiento es alternado, continuo y se origina desde la cadera (látigo).', 13),
(@plantilla_espalda, 'La patada rompe ligeramente la superficie del agua (salpicadura mínima), sin gran amplitud, con los pies en flexión plantar.', 14),
-- COORDINACIÓN
(@plantilla_espalda, 'Correcta oposición de brazos: un brazo está entrando al agua mientras el otro está a punto de empujar.', 15);

-- ---------------------------------------------
-- 5. Ítems: BRAZA (9 ítems)
-- ---------------------------------------------
SET @plantilla_braza = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Braza');

INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
-- POSICIÓN
(@plantilla_braza, 'Cuerpo horizontal en el momento del deslizamiento. Cabeza alineada, mirando hacia abajo.', 1),
-- BRAZOS
(@plantilla_braza, 'Las manos se juntan rápidamente debajo del pecho o barbilla y se proyectan hacia adelante, manteniendo el cuerpo hidrodinámico.', 2),
(@plantilla_braza, 'Las manos se dirigen hacia afuera y un poco hacia abajo. Los codos se mantienen altos inicialmente.', 3),
(@plantilla_braza, 'Manos y antebrazos barren vigorosamente hacia el centro, sin pasar de la línea de los hombros.', 4),
-- PIERNAS - Batido
(@plantilla_braza, 'Los pies están en flexión dorsal (tobillos flexionados) y rotados hacia afuera al iniciar el barrido.', 5),
(@plantilla_braza, 'El empuje es fuerte hacia afuera y luego atrás (movimiento circular), terminando con las piernas estiradas y pies juntos.', 6),
-- PIERNAS - Recobro
(@plantilla_braza, 'Flexión de rodillas y aproximación de los talones a los glúteos con ligera rotación externa.', 7),
-- COORDINACIÓN
(@plantilla_braza, 'Los brazos terminan su recobro antes de que las piernas inicien su empuje propulsor.', 8),
(@plantilla_braza, 'Sigue la secuencia: Brazos y Respiración → Deslizamiento → Patada → Deslizamiento (máxima pausa).', 9);

-- ---------------------------------------------
-- 6. Ítems: MARIPOSA (9 ítems)
-- ---------------------------------------------
SET @plantilla_mariposa = (SELECT id FROM plantillas_evaluacion WHERE nombre = 'Evaluación Mariposa');

INSERT INTO `items_evaluacion` (`plantilla_id`, `texto`, `orden`) VALUES
-- POSICIÓN
(@plantilla_mariposa, 'Posición horizontal.', 1),
(@plantilla_mariposa, 'Movimiento ondulatorio (cabeza, tronco y piernas).', 2),
-- BRAZOS - Entrada
(@plantilla_mariposa, 'Los brazos entran simultáneamente a la anchura de los hombros, con las palmas ligeramente hacia afuera.', 3),
-- BRAZOS - Tracción
(@plantilla_mariposa, 'La trayectoria subacuática es un doble barrido lateral (hacia afuera y luego hacia adentro) para maximizar la propulsión.', 4),
-- BRAZOS - Recobro
(@plantilla_mariposa, 'Los brazos salen del agua simultáneamente y se proyectan rectos hacia adelante, justo por encima de la superficie.', 5),
-- PIERNAS
(@plantilla_mariposa, 'El movimiento de látigo es potente y continuo, originándose principalmente desde la cadera y el torso.', 6),
(@plantilla_mariposa, 'Pies relajados y tobillos en extensión plantar para un batido efectivo (como una aleta).', 7),
-- COORDINACIÓN
(@plantilla_mariposa, 'Primera Patada: Fuerte, se realiza cuando las manos entran en el agua. Segunda Patada: Más ligera, se realiza al final del empuje de los brazos, justo antes del recobro.', 8),
(@plantilla_mariposa, 'Respiración: la cabeza se levanta hacia adelante (o ligeramente lateral) de forma natural, en sincronía con el inicio del recobro de los brazos.', 9);
