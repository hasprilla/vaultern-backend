# Checklist de PR — refactor incremental

## Compatibilidad

- [ ] Contratos API sin breaking changes (o versión aditiva)
- [ ] Eventos/canales realtime sin cambios de protocolo
- [ ] Comportamiento UI observable igual (salvo bugfix documentado)

## Arquitectura

- [ ] Capas respetadas (Flutter presentation/domain/data; Laravel Http/Application/Domain/Infrastructure)
- [ ] Sin lógica de negocio en UI / Controllers
- [ ] Modelos nuevos inmutables (Freezed) + codegen

## Rendimiento

- [ ] Sin HTTP/SQL duplicado innecesario
- [ ] Eager load donde haya relaciones
- [ ] Invalidaciones de cache/providers selectivas

## Verificación

- [ ] Smoke del flujo tocado (p. ej. checkout Wompi, login, lista familia)
- [ ] Realtime connect/disconnect si se tocó `core/realtime`
- [ ] `dart run build_runner build` si hubo anotaciones
- [ ] Pint / analyzer sin errores nuevos críticos

## Plantilla de descripción

```
### Problema
### Mejora
### Riesgos
### Compatibilidad
### Archivos
### Cómo verificar
```
