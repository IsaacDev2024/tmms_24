# Bloque Exploración de las Habilidades Socioemocionales (Moodle)

El bloque **TMMS-24** permite a estudiantes responder una prueba de **24 ítems** en escala **1–5** y obtener un perfil por **tres dimensiones** de inteligencia emocional:

- **Percepción**
- **Comprensión**
- **Regulación**

El resultado incluye puntajes (0–40 por dimensión en la UI), interpretaciones **con baremos dependientes del género** y una presentación “resumen + detalle” tanto en el bloque como en vistas dedicadas. Para docentes/administradores, incorpora un panel con métricas del curso y exportación.

Este repositorio incluye:
- Experiencia de estudiante con **guardado progresivo** (autosave) y validaciones antes de enviar.
- Herramientas docentes con **dashboard**, vista individual por estudiante y **exportación CSV/JSON**.

## Contenido

- [Funcionalidades](#funcionalidades)
- [Recorrido Visual](#recorrido-visual)
- [Sección técnica (modelo de datos, cálculo, flujos, permisos, endpoints)](#sección-técnica)
- [Instalación](#instalación)
- [Operación y soporte](#operación-y-soporte)
- [Contribuciones](#contribuciones)
- [Equipo de desarrollo](#equipo-de-desarrollo)

---

## Funcionalidades

### Para estudiantes
- **Aplicación del test** (24 ítems, escala 1–5) con:
  - Recolección de **edad** y **género** (requeridos al finalizar).
  - Guardado progresivo y reanudación.
  - Validación visual y scroll a campos pendientes.
- **Resultados**:
  - Puntajes por dimensión (sobre 40).
  - Interpretación breve y ampliada.
  - Resaltado de “dimensión/dimensiones estrella” y objetivos sugeridos.

### Para docentes / administradores
- **Vista del bloque** con acceso al panel de resultados.
- **Dashboard del curso** con:
  - **Conteos** (matriculados, completados, en progreso, tasa de finalización),
  - **Estadísticas y distribuciones** por dimensión,
  - **Tabla de participantes** (nombre, correo, estado, resultados).
  - Acceso a **vista individual** por estudiante.
  - Posibilidad de **eliminación** de resultados individuales.
  - **Exportación** de resultados completados del curso o individuales en **CSV** o **JSON**.
- Opción para **mostrar/ocultar** las descripciones en el bloque principal **(oculto por defecto)**.
- **Controles de privacidad**: acceso restringido por capacidades y por matrícula en el curso.
---

## Recorrido Visual

### 1. Experiencia del Estudiante

**Acceso Intuitivo y Llamado a la Acción**

El recorrido comienza con una invitación clara y directa. Desde el bloque principal del curso, el estudiante puede visualizar su estado actual y acceder al test con un solo click, facilitando la participación sin fricciones.
<p align="center">
  <img src="https://github.com/user-attachments/assets/a76dfd57-77c2-40f7-a04e-ce11ddeec7a2" alt="Invitación al Test" width="528">
</p>

**Interfaz de Evaluación Optimizada**

Se presenta un entorno de respuesta limpio y libre de distracciones. La interfaz ha sido diseñada para priorizar la legibilidad y la facilidad de uso, permitiendo que el estudiante se concentre totalmente en el proceso de autodescubrimiento.
<p align="center">
  <img src="https://github.com/user-attachments/assets/063cabd1-df7b-4c78-bca3-56d05700e610" alt="Formulario del Test" width="528">
</p>

**Asistencia y Validación en Tiempo Real**

Para garantizar la integridad de los datos, el sistema implementa una validación inteligente. Si el usuario olvida alguna respuesta, el sistema lo guía visualmente mediante alertas en rojo y un desplazamiento automático hacia los campos pendientes, asegurando una experiencia sin errores.

<p align="center">
  <img src="https://github.com/user-attachments/assets/ea26734d-d1ee-40f7-bdd6-96d18469c64c" alt="Validación" width="528">
</p>

**Persistencia de Progreso y Continuidad**

Entendemos que el tiempo es valioso. Si el estudiante debe interrumpir su sesión, el sistema guarda automáticamente su avance. Al regresar, el bloque muestra el porcentaje de progreso y permite reanudar el test exactamente donde se dejó, resaltando visualmente la siguiente pregunta a responder.
	
<p align="center">
  <img src="https://github.com/user-attachments/assets/acaca5d3-e995-4719-988f-2e322152ec46" alt="Progreso del Test" height="350">
  &nbsp;&nbsp;
  <img src="https://github.com/user-attachments/assets/c7b714ab-4499-4884-bfe7-67f26301c27c" alt="Continuar Test" height="350">
</p>

**Confirmación de Envío Pendiente**
Si el estudiante ha completado las 44 preguntas pero aún no ha procesado el envío, el bloque muestra una notificación clara y amigable, invitándolo a formalizar la entrega y conocer sus habilidades socio-emocionales.

<p align="center">
  <img src="https://github.com/user-attachments/assets/c8a4b08a-ac55-40b3-9e29-24cfe43ab8f0" alt="Confirmación de Test Completado" width="528">
</p>

**Análisis de Perfil y Recomendaciones Personalizadas**

Al finalizar, el estudiante recibe un diagnóstico de sus habilidades socioemocionales. La presentación muestra su puntaje y una interpretación clara de cada una de sus dimensiones destacadas, así como del resto de las dimensiones evaluadas. El estudiante tiene la opción de acceder a un informe detallado, donde puede consultar toda la información completa y profundizar en sus resultados.
<p align="center">
  <img src="https://github.com/user-attachments/assets/806c2408-0021-4d65-8bf1-5a3c3c571cb5" alt="Resultados del Estudiante" width="528">
</p>

<p align="center">
  <img src="https://github.com/user-attachments/assets/185a607d-c0d9-4cf6-8abc-6a2543f81072" alt="Resultados del Estudiante" width="800">
</p>

### 2. Experiencia del Profesor

**Dashboard de Control Rápido (Vista del Bloque)**

El profesor cuenta con una vista ejecutiva desde el bloque, donde puede monitorizar métricas clave, además de acceder a funciones avanzadas de administración.

<p align="center">
  <img src="https://github.com/user-attachments/assets/d688b027-bac4-49ba-abe0-ef0196986628" alt="Bloque del Profesor" width="528">
</p>

**Centro de Gestión y Analíticas**

Un panel de administración que centraliza el seguimiento grupal. Permite visualizar quiénes han completado el proceso, quiénes están en curso y gestionar los resultados colectivos para adaptar la estrategia pedagógica del aula.

<p align="center">
  <img src="https://github.com/user-attachments/assets/d4c67b35-91ee-499c-924d-9ba859c1e219" alt="Panel de Administración" width="800">
</p>

**Seguimiento Individualizado y Detallado**

El docente puede profundizar en las habilidades socioemocionales de cada estudiante. Esta vista permite comprender las necesidades particulares de cada alumno y las recomendaciones sugeridas por el sistema para brindar un apoyo docente más humano y dirigido.

- **Nota:** Esta vista es la misma que la del estudiante, pero accesible por el profesor para cualquier alumno del curso con explicaciones sobre el resultado.

---

## Sección técnica

Esta sección describe el comportamiento **tal como está implementado** en el bloque (cálculo, persistencia, flujos y controles de acceso).

### 1) Estructura del test y codificación de respuestas

- Total de ítems: **24**.
- Escala por ítem: **1–5**.
- Dimensiones (8 ítems cada una):
  - **Percepción**: ítems 1–8
  - **Comprensión**: ítems 9–16
  - **Regulación**: ítems 17–24
- Demografía: se solicita **edad** (10–100) y **género** (`M`, `F`, `prefiero_no_decir`).

### 2) Cálculo de puntajes por dimensión

El cálculo se centraliza en `TMMS24Facade::calculate_scores()` y se realiza por suma simple:

- `percepcion = sum(item1..item8)`
- `comprension = sum(item9..item16)`
- `regulacion = sum(item17..item24)`

En la UI, los puntajes se muestran sobre **40** (8 ítems × 5 puntos). En guardado final, el servidor exige que los 24 ítems estén respondidos con valores en **[1, 5]**.

### 3) Interpretación psicológica (baremos por género)

La interpretación se calcula con `TMMS24Facade::get_interpretation()` y depende del género informado. En código, `prefiero_no_decir` cae en el mismo baremo que el caso “no M” (misma rama que `F`).

**Percepción**

- `M`: ≤21 (dificultad), 22–32 (adecuada), ≥33 (atención excesiva)
- `F` / `prefiero_no_decir`: ≤24 (dificultad), 25–35 (adecuada), ≥36 (atención excesiva)

**Comprensión**

- `M`: ≤25 (dificultad), 26–35 (adecuada con dificultades), ≥36 (gran claridad)
- `F` / `prefiero_no_decir`: ≤23 (dificultad), 24–34 (adecuada con dificultades), ≥35 (gran claridad)

**Regulación**

- `M`: ≤23 (dificultad), 24–35 (equilibrio adecuado), ≥36 (gran capacidad)
- `F` / `prefiero_no_decir`: ≤23 (dificultad), 24–34 (equilibrio adecuado), ≥35 (gran capacidad)

Además de la interpretación breve, el bloque provee una variante ampliada vía `get_all_interpretations_long()`.

### 4) Normalización y “dimensiones estrella”

Para comparar dimensiones de forma más justa (dado que **Percepción** penaliza el exceso), el bloque calcula un puntaje normalizado (0–100) con `TMMS24Facade::get_normalized_score()`:

- **Percepción**: normalización “parabólica” con punto óptimo (27 `M` / 30 `F`) y rango adecuado (22–32 `M` / 25–35 `F`).
- **Comprensión** y **Regulación**: normalización “lineal” por rangos (gran/adecuada/dificultad).

Uso en el bloque:

- Se selecciona la(s) **dimensión(es) estrella** como aquella(s) con mayor normalizado (si hay empate, se muestran varias).
- Si las tres dimensiones quedan por debajo de 60 en normalizado, se usa el caso “all bad” (todas requieren mejora).

### 5) Guardado progresivo, validación y reanudación

**Guardado progresivo (autosave)**

- Implementado en el formulario de `view.php`.
- Tras cualquier cambio (ítems, edad, género), se encola un autosave y se ejecuta tras **400 milisegundos** de inactividad.
- El autosave envía un POST a `save.php` con `ajax=1` y `auto_save=1`.
- Comportamiento importante: si aún no existe registro y no hay datos (ni demografía ni respuestas), el autosave no crea filas.

**Validación al finalizar**

- Cliente: antes de enviar, marca campos inválidos y hace scroll al primer faltante.
- Servidor (`save.php`):
  - valida edad 10–100,
  - valida género en `M`, `F`, `prefiero_no_decir`,
  - valida que los 24 ítems estén en **[1, 5]**,
  - calcula puntajes y marca `is_completed = 1`.

**Reanudación**

- Si hay progreso parcial, al volver se hace scroll y resaltado temporal a la primera pregunta pendiente.
- Si todo está respondido pero el usuario viene desde el bloque para finalizar, se puede hacer scroll al botón de envío.

### 6) Modelo de datos (tabla principal)

Tabla: `tmms_24`

- `user` (índice **único**): el test se almacena **globalmente por usuario**.
- `age`, `gender`.
- `is_completed`: 0 (en progreso) / 1 (completado).
- `item1..item24`: respuestas individuales (1–5).
- Puntajes: `percepcion_score`, `comprension_score`, `regulacion_score`.
- Trazabilidad: `created_at`, `updated_at`.

Implicación importante:

- Al ser **único por usuario** y sin campo de curso, el resultado puede reutilizarse entre cursos. Las vistas docentes filtran participantes por **matrícula en el curso**, pero el registro del test pertenece al usuario a nivel global.

### 7) Vistas, endpoints y exportación

**Estudiante**

- Formulario del test y resultados propios: `view.php?cid=<courseid>`
- Guardado (autosave y envío final): `save.php` (POST con `sesskey`)

**Docente / Administrador**

- Panel del curso: `teacher_view.php?courseid=<courseid>`
- Resultados individuales: `student_results.php?courseid=<courseid>&userid=<userid>`
- Exportación CSV/JSON: `export.php?cid=<courseid>&format=csv|json[&userid=<userid>]`
- Eliminación de respuesta: `teacher_view.php?courseid=<courseid>&action=delete&id=<entryid>&sesskey=<sesskey>` (con confirmación)
- Eliminación directa (alternativa): `delete_response.php?courseid=<courseid>&id=<entryid>&sesskey=<sesskey>`

### 8) Permisos (capabilities) y controles de acceso

Capacidades definidas por el bloque:

- `block/tmms_24:taketest` (estudiante): permite tomar el test.
- `block/tmms_24:viewallresults` (docente/manager): permite ver el dashboard del curso, resultados de estudiantes y exportaciones.
- `block/tmms_24:manageresponses`: reservado para gestión avanzada (no es el gate principal de export en `export.php`).
- `block/tmms_24:addinstance` / `block/tmms_24:myaddinstance`: gestión de instancias.

Controles adicionales implementados:

- `view.php` impide que docentes/administradores tomen el test: si tienen `viewallresults`, redirige al panel docente.
- Escrituras protegidas con `sesskey`.
- Exportación y vista docente restringidas a `viewallresults`.
- Filtrado defensivo en panel/exportación: excluye site admins y usuarios con `viewallresults` aunque estén matriculados.
- Eliminación requiere `viewallresults` **y** `moodle/course:manageactivities`.

---

## Instalación

1. Descargar el plugin desde las *releases* del repositorio oficial: https://github.com/ISCOUTB/tmms_24
2. En Moodle (como administrador):
   - Ir a **Administración del sitio → Extensiones → Instalar plugins**.
   - Subir el archivo ZIP.
   - Completar el asistente de instalación.
3. En un curso, agregar el bloque **Exploración de Habilidades Socioemocionales** desde el selector de bloques.


---

## Operación y soporte

### Consideraciones de despliegue

- Compatibilidad declarada: Moodle **4.0+**.

### Resolución de problemas (rápido)

- **El estudiante no ve el test**: validar que tenga `block/tmms_24:taketest` en el contexto del curso.
- **El docente no ve el panel o exportaciones**: validar `block/tmms_24:viewallresults`.
- **El progreso no se guarda**: revisar `sesskey` en el POST y bloqueos del navegador/red.

---

## Contribuciones

¡Las contribuciones son bienvenidas! Si deseas mejorar este bloque, por favor sigue estos pasos:

1. Haz un fork del repositorio.
2. Crea una nueva rama para tu característica o corrección de errores.
3. Realiza tus cambios y asegúrate de que todo funcione correctamente.
4. Envía un pull request describiendo tus cambios.

---

## Equipo de desarrollo

- Jairo Enrique Serrano Castañeda
- Yuranis Henriquez Núñez
- Isaac David Sánchez Sánchez
- Santiago Andrés Orejuela Cueter
- María Valentina Serna González

<div align="center">
<strong>Desarrollado con ❤️ para la Universidad Tecnológica de Bolívar</strong>
</div>
