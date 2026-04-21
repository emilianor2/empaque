# 🏭 Empaque – Sistema de Gestión de Producción

## 📌 Descripción

Sistema web interno para registrar órdenes de producción, controlar resultados por máquina y generar reportes operativos en planta.

Desarrollado para reemplazar registros manuales y planillas dispersas, centralizando toda la operatoria en una sola aplicación.

---

## 🚀 Propuesta de valor

* ✔️ Reduce errores de carga mediante validaciones en tiempo real
* ✔️ Mejora la trazabilidad por fecha, turno, operario, máquina y código
* ✔️ Permite detectar desviaciones de calidad y excedentes rápidamente
* ✔️ Agiliza la toma de decisiones con reportes filtrables y exportación a Excel

---

## ⚙️ Funcionalidades principales

* Carga unificada de órdenes de producción (OP) con múltiples líneas
* Registro por tipo de código (LS y F), máquina y cantidades
* Validaciones de negocio para evitar inconsistencias
* Reportes diarios por máquina y turno
* Reportes por operario con totales y detalle por fechas
* Reporte de excedentes por caja
* Totales consolidados por OP
* Editor de registros con auditoría de cambios
* Gestión de empleados con acceso restringido
* Exportación de datos compatible con Excel

---

## 🛠️ Stack técnico

* PHP
* MySQL / MariaDB
* HTML + Bootstrap 5
* JavaScript

---

## 🧱 Arquitectura (resumen)

* `index.php` → menú principal
* `add_unificado.php` → carga de producción
* `reportes.php` → acceso a reportes
* `report_*` → módulos de analítica
* `editor_registros.php` → edición con trazabilidad
* `empleados.php` → gestión de usuarios

---

## ⚙️ Instalación

```bash
git clone https://github.com/emilianor2/empaque
```

1. Crear `config.local.php` desde `config.example.php`
2. Configurar credenciales de base de datos
3. Importar base en MySQL/MariaDB
4. Ejecutar en XAMPP / Laragon / Apache

---

## 🎯 Estado del proyecto

✅ En uso real en entorno productivo

---

## 👨‍💻 Autor

Emiliano Rodríguez
