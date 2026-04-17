<?php
// reportes.php
session_start();
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Reportes · Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
    }

    .page-title {
      font-size: 1.4rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.8rem;
    }

    .card-choice {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      transition: all .2s ease;
      text-align: center;
      padding: 2rem;
      background: #fff;
      height: 100%;
    }

    .card-choice:hover {
      border-color: #3b82f6;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
      transform: translateY(-2px);
    }

    .card-choice h2 {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: .5rem;
    }

    .card-choice p {
      color: #64748b;
      font-size: .9rem;
      margin-bottom: 1rem;
    }

    .btn {
      font-size: .9rem;
    }
  </style>
</head>

<body>
  <div class="container py-4">

    <h1 class="page-title">Seleccionar tipo de Reporte</h1>

    <div class="row g-4 justify-content-center">
      <div class="col-12 col-md-4">
        <a href="report_ops.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Reporte para revisar</h2>
          <p>Visualiza y consulta todas las OPs cargadas, con detalle de lineas y cajas.</p>
          <span class="btn btn-outline-primary px-4">Abrir</span>
        </a>
      </div>
      <div class="col-12 col-md-4">
        <a href="reporte_diario.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Reporte para produccion</h2>
          <p>Genera el informe diario de produccion agrupado por maquina y turno.</p>
          <span class="btn btn-outline-success px-4">Abrir</span>
        </a>
      </div>
      <div class="col-12 col-md-4">
        <a href="editor_registros.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Editor de registros</h2>
          <p>Edita cabeceras y lineas de OPs ya cargadas, con filtros por OP o fecha.</p>
          <span class="btn btn-outline-warning px-4">Abrir</span>
        </a>
      </div>

      <div class="col-12 col-md-4">
        <a href="report_operarios.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Reporte por operario</h2>
          <p>Totales y detalle por operario, con filtros avanzados y resumen por rango.</p>
          <span class="btn btn-outline-primary px-4">Abrir</span>
        </a>
      </div>

      <div class="col-12 col-md-4">
        <a href="report_excedentes_caja.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Reporte por caja excedente</h2>
          <p>Busca movimientos con excedente por numero de caja exacto o por rango.</p>
          <span class="btn btn-outline-warning px-4">Abrir</span>
        </a>
      </div>

      <div class="col-12 col-md-4">
        <a href="report_totales_op.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Totales por OP</h2>
          <p>Suma buenas, malas y excedente por OP. Permite filtrar por OP y por codigo LS/F.</p>
          <span class="btn btn-outline-primary px-4">Abrir</span>
        </a>
      </div>

      <div class="col-12 col-md-4">
        <a href="empleados.php" class="card-choice d-block text-decoration-none text-dark">
          <h2>Gestion de empleados</h2>
          <p>Lista empleados y permite agregar o quitar registros de la base.</p>
          <span class="btn btn-outline-secondary px-4">Abrir</span>
        </a>
      </div>
    </div>

    <div class="mt-4 text-center">
      <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
    </div>

  </div>
</body>

</html>
