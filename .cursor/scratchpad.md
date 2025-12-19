# Aquatiq - Sistema de Evaluación de Natación

## Background and Motivation

**Aquatiq** es una aplicación web para evaluar el progreso de alumnos (niños y adultos) en clases de natación. 

### Contexto del negocio
- **Periodos de evaluación**: Enero y Mayo se entrega una planilla con ítems a cada padre
- **Formato de respuestas**: Sí / No / A veces
- **Tipo de preguntas**: "Se tira de cabeza correctamente", habilidades específicas por nivel
- **Final de curso**: Se da una evaluación con recomendación de nivel
- **Flexibilidad**: Un alumno puede recibir evaluaciones de múltiples niveles si progresa rápido (ej: entra en Tortuga, pero si destaca puede recibir evaluación de Tortuga + Tortuga Avanzado + Pececito)

### Stack tecnológico
- **Backend**: PHP vanilla (sin frameworks)
- **Base de datos**: MySQL
- **Frontend**: HTML/CSS/JS básico

### Roles del sistema
1. **Superadmin**: Control total del sistema
2. **Admin**: Gestión de alumnos, grupos, monitores, plantillas de evaluación
3. **Monitor**: Evaluar alumnos de sus grupos asignados
4. **Padre/Tutor**: Ver evaluaciones de sus hijos (solo lectura)

---

## Key Challenges and Analysis

### Retos identificados

1. **Evaluaciones multinivel**: Un alumno puede tener evaluaciones de diferentes niveles en el mismo periodo
2. **Plantillas dinámicas**: Cada nivel tiene sus propios ítems de evaluación
3. **Histórico**: Mantener trazabilidad del progreso a lo largo del tiempo
4. **Generación de informes**: PDFs para entregar a padres

### Modelo de datos (propuesta)

```
usuarios (id, nombre, email, password, rol, activo)
niveles (id, nombre, orden) -- Tortuga, Tortuga Avanzado, Pececito, Estilos...
grupos (id, nombre, nivel_id, horario)
alumnos (id, nombre, fecha_nacimiento, grupo_id, padre_id)
plantillas_evaluacion (id, nivel_id, nombre)
items_evaluacion (id, plantilla_id, texto, orden)
evaluaciones (id, alumno_id, plantilla_id, periodo, fecha, monitor_id, recomendacion_nivel_id)
respuestas (id, evaluacion_id, item_id, valor) -- 'si', 'no', 'a_veces'
monitores_grupos (monitor_id, grupo_id) -- relación N:M
```

### Niveles identificados (11 niveles + Estilos pendiente)

| # | Nivel | Ítems |
|---|-------|-------|
| 1 | Burbujita | 10 |
| 2 | Medusa | 12 |
| 3 | Medusa Avanzado | 11 |
| 4 | Tortuga | 10 |
| 5 | Tortuga Avanzado | 10 |
| 6 | Pececito | 10 |
| 7 | Pececito Avanzado | 11 |
| 8 | Tiburón | 11 |
| 9 | Tiburón Avanzado | 10 |
| 10 | Delfín | 8 |
| 11 | Delfín Avanzado | 10 |
| 12 | Estilos | *(pendiente de recibir)* |

**Progresión**: Burbujita → Medusa → Medusa Avanzado → Tortuga → ... → Delfín Avanzado

---

## High-level Task Breakdown

### Fase 1: Infraestructura base ✅ COMPLETADA
- [x] 1.1 Estructura de carpetas del proyecto
- [x] 1.2 Configuración de conexión MySQL
- [x] 1.3 Script SQL de creación de tablas (con 11 niveles + 113 ítems precargados)
- [x] 1.4 Sistema de autenticación básico (login/logout/sesiones)
- [x] 1.5 Layout base con logo y navegación según rol

### Fase 2: Gestión de datos maestros (Admin) ✅ COMPLETADA
- [x] 2.1 CRUD de Niveles
- [x] 2.2 CRUD de Grupos
- [x] 2.3 CRUD de Alumnos (con importación CSV)
- [x] 2.4 CRUD de Monitores (usuarios con rol monitor)
- [x] 2.5 Asignación monitores ↔ grupos

### Fase 3: Gestión de evaluaciones (Admin) ✅ COMPLETADA
- [x] 3.1 CRUD de Plantillas de evaluación por nivel
- [x] 3.2 CRUD de Ítems dentro de cada plantilla

### Fase 4: Panel del Monitor ✅ COMPLETADA
- [x] 4.1 Ver grupos asignados y sus alumnos
- [x] 4.2 Crear evaluación para un alumno (seleccionar plantilla/nivel)
- [x] 4.3 Rellenar evaluación (marcar Sí/No/A veces por ítem)
- [x] 4.4 Añadir recomendación de nivel
- [x] 4.5 Guardar y editar evaluaciones
- [x] 4.6 Ver historial de evaluaciones

### Fase 5: Panel del Padre ✅ COMPLETADA
- [x] 5.1 Ver hijos asignados
- [x] 5.2 Ver evaluaciones de cada hijo con detalle completo

### Fase 6: Panel Superadmin ✅ COMPLETADA
- [x] 6.1 Gestión completa de usuarios (CRUD todos los roles)

### Fase 7: Mejoras incluidas ✅
- [x] 7.1 Importación masiva de alumnos desde CSV
- [x] 7.2 Filtros y búsquedas en listados
- [x] 7.3 Dashboard con estadísticas
- [x] 7.4 Diseño moderno con animaciones y gradientes

---

## Project Status Board

### Pendiente
- [ ] Recibir plantilla de "Estilos" (cuando esté disponible)
- [ ] Generación de PDF para padres (futura mejora)
- [ ] Acceso público a evaluaciones (implementado, falta probar end-to-end)

### En progreso
- [ ] Usuario probando en aquatiq.ebone.es

### Completado ✅
- [x] Infraestructura base completa
- [x] Diseño CSS moderno con gradientes y animaciones
- [x] CRUD Niveles, Grupos, Alumnos, Monitores
- [x] Importación CSV de alumnos
- [x] Gestión de Plantillas de evaluación con ítems
- [x] Panel Monitor: ver grupos, alumnos, crear/editar evaluaciones
- [x] Panel Padre: ver hijos y evaluaciones completas
- [x] Panel Superadmin: gestión de usuarios
- [x] Dashboard con estadísticas
- [x] 11 niveles + 113 ítems precargados en BD

---

## Executor's Feedback or Assistance Requests

**Pendiente del usuario:**
1. ~~Contenido del PDF "EVALUACIONES INFANTILES.pdf"~~ ✅ Recibido en info_aquatiq.txt
2. ~~Confirmación de los niveles existentes~~ ✅ 11 niveles confirmados
3. ~~Listado de alumnos para ver formato de importación~~ ✅ CSV con columnas: N.º USUARIO, APELLIDO 1, APELLIDO 2, NOMBRE, CURSO/GRUPO, MONITOR/A
4. Plantilla de evaluación "Estilos"

**Notas del executor (19-Dec-2025):**
- Añadido acceso público sin login:
  - `/evaluaciones.php`: formulario (número de usuario + nombre alumno) que busca alumno activo, guarda id en sesión pública y lista sus evaluaciones.
  - `/evaluacion.php`: detalle de evaluación validando que pertenece al alumno identificado en la sesión pública.
- Falta probar flujo completo y validar con datos reales en entorno.

## Configuración del entorno

- **Repositorio**: GitHub (usuario sube manualmente)
- **Dominio producción/pruebas**: aquatiq.ebone.es
- **Nota**: Los ítems por nivel pueden cambiar, diseño flexible

---

## Lessons

*(Se irán añadiendo durante el desarrollo)*

---

## Referencias

- Logo: `/logo-aquatiq.png`
- Evaluaciones: `/EVALUACIONES INFANTILES.pdf` (pendiente extraer contenido)
