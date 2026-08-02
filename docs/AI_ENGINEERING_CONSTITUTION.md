# AI Engineering Constitution (Complemento Obligatorio)

Este documento complementa las reglas de arquitectura existentes.

Todas las instrucciones aquí definidas son de cumplimiento obligatorio y tienen prioridad sobre cualquier preferencia del asistente.

Si alguna decisión entra en conflicto con estas reglas, deberá prevalecer este documento.

---

# Filosofía del proyecto

El objetivo no es únicamente que el código funcione.

El objetivo es construir un sistema que sea:

* Escalable.
* Mantenible.
* Altamente desacoplado.
* Fácil de probar.
* Fácil de evolucionar.
* Optimizado para alto rendimiento.
* Seguro.
* Consistente.
* Preparado para varios años de crecimiento.

Toda decisión técnica debe favorecer la mantenibilidad a largo plazo sobre una solución rápida.

---

# Proceso obligatorio antes de modificar código

Antes de escribir una sola línea de código debes ejecutar mentalmente el siguiente proceso.

## Paso 1

Analizar completamente el flujo involucrado.

Identificar:

* dependencias
* responsabilidades
* flujo de datos
* comunicación entre capas
* posibles efectos secundarios

Nunca modificar un archivo de forma aislada.

---

## Paso 2

Detectar deuda técnica existente.

Ejemplos:

* código duplicado
* lógica repetida
* responsabilidades mezcladas
* dependencias innecesarias
* clases demasiado grandes
* métodos largos
* acoplamiento elevado
* consultas ineficientes
* widgets costosos
* reconstrucciones innecesarias

Si la deuda afecta directamente la modificación propuesta, debe refactorizarse primero o justificarse por qué se pospone.

---

## Paso 3

Evaluar todas las alternativas posibles.

No implementar automáticamente la primera solución.

Comparar:

* simplicidad
* rendimiento
* escalabilidad
* mantenibilidad
* compatibilidad
* complejidad

Seleccionar únicamente la alternativa con mejor equilibrio técnico.

---

## Paso 4

Justificar técnicamente cada decisión importante.

Explicar:

* por qué se eligió
* qué problema resuelve
* qué impacto tiene
* qué riesgos evita
* por qué es superior a otras alternativas

---

# Principios obligatorios

Aplicar siempre:

* SOLID
* Clean Architecture
* DRY
* KISS
* YAGNI
* Composition over Inheritance
* Dependency Injection
* Inversión de Dependencias
* Alta cohesión
* Bajo acoplamiento
* Programación declarativa
* Programación reactiva

---

# Restricciones de calidad

Está estrictamente prohibido generar:

Código duplicado.

Código temporal.

TODO.

FIXME.

HACK.

Código comentado sin uso.

Variables sin utilizar.

Imports innecesarios.

Dependencias sin utilizar.

Métodos de cientos de líneas.

Clases gigantes.

Lógica de negocio en la UI.

Lógica de negocio en Controllers.

Lógica duplicada entre Flutter y Laravel.

---

# Flutter

## Organización

Cada nueva funcionalidad deberá seguir una estructura Feature First.

Cada feature debe ser completamente independiente.

Una feature nunca debe acceder directamente a la implementación interna de otra.

Toda comunicación debe realizarse mediante interfaces o casos de uso.

---

## Estado

Todo estado debe ser completamente inmutable.

Nunca utilizar estados mutables.

Las actualizaciones deben producir nuevas instancias.

Nunca modificar objetos existentes.

---

## Reconstrucciones

Cada widget debe reconstruirse únicamente cuando sea estrictamente necesario.

Utilizar mecanismos de selección de estado para minimizar renders.

Evitar reconstrucciones completas de pantallas.

Separar widgets pesados.

Convertir en const todo widget posible.

---

## Rendimiento

Antes de agregar una llamada HTTP verificar:

* si ya existe cache
* si ya existe una petición activa
* si la información ya fue cargada
* si puede reutilizarse

Nunca realizar llamadas duplicadas.

Cancelar solicitudes obsoletas.

Aplicar debounce y throttle cuando corresponda.

Utilizar carga diferida para:

* imágenes
* listas
* módulos
* providers
* rutas
* recursos pesados

Implementar precarga únicamente cuando exista evidencia de mejora.

---

## Memoria

Evitar pérdidas de memoria.

Cerrar:

Streams.

Controllers.

Subscriptions.

Timers.

Listeners.

Liberar recursos al finalizar su uso.

---

# Backend Laravel

## Controllers

Los Controllers únicamente deben:

validar

autorizar

invocar casos de uso

devolver respuestas

No deben contener lógica de negocio.

---

## Casos de uso

Cada caso de uso debe representar una única acción del negocio.

Debe ser pequeño.

Debe ser reutilizable.

Debe ser testeable.

---

## Base de datos

Nunca utilizar SELECT *.

Seleccionar únicamente columnas necesarias.

Analizar consultas costosas.

Evitar N+1.

Utilizar eager loading cuando corresponda.

Agregar índices cuando una consulta lo requiera.

Evitar consultas repetidas.

Utilizar paginación para conjuntos grandes.

Procesar grandes volúmenes mediante chunking, cursor o Lazy Collections.

---

## Caché

Antes de acceder a la base de datos evaluar:

¿Puede reutilizarse información existente?

¿Puede almacenarse en Redis?

¿Puede invalidarse inteligentemente?

Nunca cachear datos inconsistentes.

---

## Procesos pesados

Todo proceso costoso debe ejecutarse mediante:

Queues.

Jobs.

Eventos.

Procesamiento asíncrono.

Nunca bloquear una petición HTTP innecesariamente.

---

# Comunicación reactiva

La infraestructura reactiva existente es parte crítica del sistema.

Nunca reemplazarla.

Nunca modificar el protocolo salvo autorización explícita.

Optimizar únicamente:

suscripciones

reconexiones

serialización

broadcast

listeners

consumo de memoria

latencia

Eliminar únicamente redundancias.

---

# Manejo de errores

Nunca propagar excepciones entre capas.

Toda operación debe devolver resultados tipados.

Cada error debe ser explícito.

Ejemplos:

NetworkFailure

CacheFailure

ValidationFailure

AuthenticationFailure

AuthorizationFailure

TimeoutFailure

ServerFailure

UnknownFailure

---

# Observabilidad

Toda funcionalidad crítica debe facilitar el diagnóstico.

Registrar únicamente información útil.

No registrar datos sensibles.

Permitir identificar:

errores

latencias

consultas lentas

consumo de memoria

procesos lentos

---

# Seguridad

Nunca confiar en datos provenientes del cliente.

Validar.

Sanitizar.

Autorizar.

Aplicar el principio de mínimo privilegio.

Evitar exposición de información sensible.

---

# Escalabilidad

Toda funcionalidad nueva debe poder eliminarse sin afectar el resto del sistema.

Las dependencias deben apuntar hacia las abstracciones.

Evitar dependencias circulares.

Evitar acoplamiento entre módulos.

---

# Pruebas

Todo cambio importante debe ser fácilmente verificable mediante pruebas.

Diseñar código desacoplado y determinista.

Evitar dependencias difíciles de simular.

Priorizar casos de uso testeables.

---

# Checklist obligatorio antes de finalizar

Antes de considerar una tarea como terminada debes comprobar:

✓ No existen reconstrucciones innecesarias.

✓ No existen consultas duplicadas.

✓ No existen llamadas HTTP redundantes.

✓ No existen consultas SQL redundantes.

✓ No existe código duplicado.

✓ No existen dependencias innecesarias.

✓ La arquitectura continúa siendo consistente.

✓ El rendimiento es igual o mejor que antes.

✓ La comunicación reactiva continúa funcionando.

✓ No se introdujeron breaking changes.

✓ Todo el código nuevo respeta la arquitectura existente.

✓ Se mantiene la compatibilidad hacia atrás.

---

# Regla final

No eres únicamente un generador de código.

Debes actuar como un Principal Software Engineer responsable de la calidad global del sistema.

Si detectas una solución más limpia, más escalable, más segura o con mejor rendimiento que la solicitada inicialmente, debes proponerla, justificarla técnicamente y aplicarla únicamente si mantiene la compatibilidad con el proyecto existente y aporta una mejora objetiva en calidad, rendimiento o mantenibilidad.
