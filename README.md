# Empaque

Sistema web interno para registrar ordenes de produccion, controlar resultados por maquina y generar reportes operativos en planta.

Este proyecto fue desarrollado para digitalizar una operatoria que normalmente se resolvia con registros manuales o planillas sueltas. La aplicacion centraliza la carga de OPs, el seguimiento por operario y turno, la consulta historica y la exportacion de reportes listos para produccion.

## Propuesta de valor

- Reduce errores de carga al validar datos clave en el momento de registrar una OP.
- Mejora la trazabilidad de produccion por fecha, turno, operario, maquina y codigo.
- Permite detectar rapidamente excedentes, desvio de calidad y volumen por operario.
- Agiliza la toma de decisiones con reportes filtrables y exportacion a Excel.

## Funcionalidades principales

- Carga unificada de ordenes de produccion con multiples lineas por OP.
- Registro por tipo de codigo (`LS` y `F`), maquina, cantidades buenas, malas y excedente.
- Validaciones de negocio para evitar registros incompletos o inconsistentes.
- Reporte diario de produccion por maquina y turno.
- Reportes por operario con totales y detalle por rango de fechas.
- Reporte de excedentes por caja.
- Totales consolidados por OP.
- Editor de registros con auditoria de cambios en altas, ediciones y eliminaciones.
- Gestion de empleados con acceso restringido.
- Exportaciones compatibles con Excel para uso operativo.

## Stack tecnico

- PHP
- MySQL / MariaDB
- HTML
- Bootstrap 5
- JavaScript vanilla

## Arquitectura resumida

- `index.php`: menu principal.
- `add_unificado.php`: carga de OPs y lineas de produccion.
- `reportes.php`: acceso central a consultas y reportes.
- `report_ops.php`, `reporte_diario.php`, `report_operarios.php`, `report_excedentes_caja.php`, `report_totales_op.php`: modulos de analitica operativa.
- `editor_registros.php`, `edit_header.php`, `edit_item.php`, `delete_header.php`, `delete_item.php`: mantenimiento de datos y trazabilidad.
- `empleados.php`: ABM de empleados.
- `assets/`: estilos y scripts del frontend.

## Lo que muestra bien en un CV

Este proyecto muestra experiencia real resolviendo problemas de operacion, no solo pantallas CRUD. Refleja trabajo con reglas de negocio, modelado relacional, reporteria, exportacion de datos, control de acceso y mejora de procesos internos.

Si lo queres describir en un CV o LinkedIn, una version corta podria ser:

> Desarrolle un sistema web interno para gestion de produccion en planta de empaque, con carga de ordenes, trazabilidad por operario/turno/maquina, reportes operativos y exportacion a Excel usando PHP, MySQL y Bootstrap.

## Instalacion local

1. Clonar o copiar el proyecto en un entorno con PHP y MySQL.
2. Crear `config.local.php` a partir de `config.example.php`.
3. Completar credenciales de base de datos y claves de acceso.
4. Importar una base propia en MySQL/MariaDB.
5. Levantar el proyecto en un servidor local como XAMPP, Laragon o Apache + PHP.

## Nota sobre datos sensibles

Para una publicacion en GitHub orientada a portfolio, la configuracion local y los dumps reales de base de datos quedaron excluidos del repositorio. La idea es mostrar la solucion tecnica sin exponer credenciales ni datos operativos.
