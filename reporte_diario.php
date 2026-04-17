<?php
// reporte_diario.php (con claves mÃºltiples y responsable mostrado)
// -------------------------------------------------
session_start();
include("conexion.php"); // define $con (mysqli)

/* === Seguridad simple por clave (m??ltiples) === */
$config = require __DIR__ . '/config.local.php';
$ACCESS_KEYS = $config['access_keys'] ?? [];

if (isset($_GET['logout'])) {            // cerrar sesiÃ³n
    unset($_SESSION['diario_ok'], $_SESSION['diario_user']);
    header("Location: reporte_diario.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clave'])) {
    $clave = trim($_POST['clave'] ?? '');
    if (isset($ACCESS_KEYS[$clave])) {
        $_SESSION['diario_ok']   = true;
        $_SESSION['diario_user'] = $ACCESS_KEYS[$clave]; // responsable
        header("Location: reporte_diario.php");
        exit;
    } else {
        $login_error = "Clave incorrecta.";
    }
}
if (empty($_SESSION['diario_ok'])) {
    // Pantalla de login
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Acceso Â· Reporte diario</title>
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center" style="min-height:100vh;">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-12 col-md-4">
            <div class="card shadow-sm">
              <div class="card-body">
                <h1 class="h5 text-center mb-3">ðŸ” Reporte diario</h1>
                <?php if (!empty($login_error)): ?>
                  <div class="alert alert-danger"><?=$login_error?></div>
                <?php endif; ?>
                <form method="post">
                  <div class="mb-3">
                    <label class="form-label">Clave de acceso</label>
                    <input type="password" name="clave" class="form-control" autofocus required>
                  </div>
                  <button class="btn btn-primary w-100" type="submit">Ingresar</button>
                </form>
                <div class="mt-3 text-center">
                  <a href="reportes.php" class="btn btn-sm btn-outline-secondary">â† Volver</a>
                </div>
              </div>
            </div>
            <p class="text-center text-muted mt-3" style="font-size:.85rem">
            </p>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}
// === Fin seguridad ===

/* --------- Helpers / Filtros ---------- */
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t){ return $t==1?'MaÃ±ana':($t==2?'Tarde':($t==3?'Noche':'-')); }

/* ---- Filtros ---- */
$fecha  = g('fecha', '');
$maq    = g('maquina', '');      // '', 'SRI-1'..'SRI-4'
$turno  = g('turno', '');        // '', '1'..'3'
$doCsv  = g('csv', '');
$metric = g('metric','buenas');  // 'buenas' | 'malas' | 'excedente'

// Sanitizar metric
if (!in_array($metric, ['buenas','malas','excedente'], true)) {
  $metric = 'buenas';
}

if ($fecha==='') $fecha = date('Y-m-d');

/* ---- Consulta: por MÃ¡quina, Turno, Tipo (LS/F), OP y DescripciÃ³n ---- */
$where = "h.fecha = STR_TO_DATE('".mysqli_real_escape_string($con,$fecha)."','%Y-%m-%d')";
if ($maq!=='')   $where .= " AND i.maquina = '".mysqli_real_escape_string($con,$maq)."'";
if ($turno!=='') $where .= " AND h.turno = ".intval($turno);

$sql = "
  SELECT
    i.maquina,
    h.turno,
    i.tipo_codigo,
    h.op,
    COALESCE(NULLIF(TRIM(i.desc_trabajo),''),'(Sin descripciÃ³n)') AS desc_trabajo,
    SUM(COALESCE(i.buenas,0))    AS buenas,
    SUM(COALESCE(i.malas,0))     AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente,
    -- cajas informadas (por si hay mÃ¡s de una)
    GROUP_CONCAT(
      DISTINCT NULLIF(TRIM(i.caja_excedente),'')
      ORDER BY i.caja_excedente
      SEPARATOR ', '
    ) AS cajas_excedente
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  GROUP BY i.maquina, h.turno, i.tipo_codigo, h.op, desc_trabajo
  ORDER BY
    CASE i.maquina
      WHEN 'SRI-1' THEN 1
      WHEN 'SRI-2' THEN 2
      WHEN 'SRI-3' THEN 3
      WHEN 'SRI-4' THEN 4
      ELSE 9
    END,
    h.turno,
    i.tipo_codigo,
    h.op,
    desc_trabajo
";
$res = mysqli_query($con, $sql);

/* ---- Estructura: $data[mÃ¡quina][turno][] = filas ---- */
$data = [];
$machines = ['SRI-1','SRI-2','SRI-3','SRI-4'];

if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $m = $r['maquina'];
    $t = (int)$r['turno'];

    // valor de la mÃ©trica elegida
    $valMetric = (int)$r[$metric];

    // Si es malas o excedente y el total es 0, NO la mostramos
    if ($metric !== 'buenas' && $valMetric <= 0) {
      continue;
    }

    $data[$m][$t][] = [
      'tipo'       => $r['tipo_codigo'],
      'op'         => $r['op'],
      'desc'       => $r['desc_trabajo'],
      'buenas'     => (int)$r['buenas'],
      'malas'      => (int)$r['malas'],
      'excedente'  => (int)$r['excedente'],
      'cajas'      => $r['cajas_excedente'], // puede ser NULL o string con varias cajas
    ];
  }
}

/* ---- CSV (usa la mÃ©trica elegida) ---- */
if ($doCsv==='1') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="reporte_diario_'.$fecha.'.csv"');
  $out = fopen('php://output', 'w');

  $colName = ucfirst($metric); // Buenas / Malas / Excedente
  fputcsv($out, ['Fecha', 'MÃ¡quina', 'Turno', 'Tipo', 'OP', 'DescripciÃ³n', $colName]);

  foreach ($machines as $m) {
    if (empty($data[$m])) continue;
    foreach ([1,2,3] as $t) {
      if (empty($data[$m][$t])) continue;
      foreach ($data[$m][$t] as $row) {
        $valMetric = (int)$row[$metric];
        // De nuevo, para malas/excedente, si es 0 no exportamos
        if ($metric !== 'buenas' && $valMetric <= 0) continue;

        fputcsv($out, [
          $fecha,
          $m,
          turnoName($t),
          $row['tipo'],
          $row['op'],
          $row['desc'],
          $valMetric
        ]);
      }
    }
  }
  fclose($out);
  exit;
}

$RESPONSABLE = $_SESSION['diario_user'] ?? 'â€”';

// etiqueta y clase de la columna segÃºn mÃ©trica
$metricLabel = 'Buenas';
$metricClass = 'text-success';
if ($metric === 'malas') {
  $metricLabel = 'Malas';
  $metricClass = 'text-danger';
} elseif ($metric === 'excedente') {
  $metricLabel = 'Excedente';
  $metricClass = 'text-warning';
}

$grandTotal = 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte diario Â· Empaque (<?=$metricLabel?>)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
    <style>
    .report-card { border: 1px solid #e5e7eb; background: #fff; border-radius: 12px; padding: 16px; }
    .table thead th { background: #f1f5f9; color: #0f172a; border-bottom: 1px solid #e5e7eb; }
    .table tbody tr:nth-child(even) { background: #fafafa; }
    .badge-turno { font-weight: 600; }
    .badge-m { background:#dcfce7; color:#166534; border:1px solid #16a34a; }
    .badge-t { background:#e0f2fe; color:#075985; border:1px solid #0ea5e9; }
    .badge-n { background:#fee2e2; color:#991b1b; border:1px solid #ef4444; }
    .row-subtotal { background:#f8fafc; font-weight:600; }
    .row-maq-total { background:#eef2ff; font-weight:700; }
    .row-grand { background:#fde68a; font-weight:800; }
    .hdr { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .hdr .title { font-size:1.25rem; font-weight:800; }
    .smallmuted { color:#6b7280; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">

        <div class="hdr mb-3">
            <div>
              <div class="title">Reporte diario Â· SRI (<?=$metricLabel?>)</div>
              <div class="text-muted" style="font-size:.9rem">Responsable: <b><?=h($RESPONSABLE)?></b></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="reportes.php?logout=1">â† Volver</a>
                <a class="btn btn-outline-danger" href="reporte_diario.php?logout=1">Cerrar acceso</a>
            </div>
        </div>

<!-- Filtros -->
<div class="report-card mb-3">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3">
      <label class="form-label">Fecha</label>
      <input type="date" name="fecha" value="<?=h($fecha)?>" class="form-control" required>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">MÃ¡quina</label>
      <select name="maquina" class="form-select">
        <option value="">Todas</option>
        <?php foreach ($machines as $m): ?>
          <option value="<?=$m?>" <?=$maq===$m?'selected':''?>><?=$m?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">Turno</label>
      <select name="turno" class="form-select">
        <option value="">Todos</option>
        <option value="1" <?=$turno==='1'?'selected':''?>>MaÃ±ana</option>
        <option value="2" <?=$turno==='2'?'selected':''?>>Tarde</option>
        <option value="3" <?=$turno==='3'?'selected':''?>>Noche</option>
      </select>
    </div>

    <div class="col-6 col-md-3">
      <label class="form-label">Tipo de cantidad</label>
      <select name="metric" class="form-select">
        <option value="buenas"    <?=$metric==='buenas'?'selected':''?>>Buenas</option>
        <option value="malas"     <?=$metric==='malas'?'selected':''?>>Malas</option>
        <option value="excedente" <?=$metric==='excedente'?'selected':''?>>Excedentes</option>
      </select>
    </div>

    <!-- Columna exclusiva para BUSCAR -->
    <div class="col-6 col-md-2">
      <label class="form-label d-block">&nbsp;</label>
      <button class="btn btn-primary w-100" type="submit" style="height:38px;">Buscar</button>
    </div>
  </form>

  <!-- Botonera abajo -->
  <div class="d-flex flex-wrap gap-2 mt-3">
    <a class="btn btn-outline-success btn-sm"
       href="export_excel_buenas_simple.php?fecha=<?=h($fecha)?>&maquina=<?=h($maq)?>&turno=<?=h($turno)?>">
       Exportar Excel (simplificado Buenas)
    </a>

    <!-- Excedentes (Excel) -->
    <a class="btn btn-outline-warning btn-sm"
       href="export_excel_excedentes.php?fecha=<?=h($fecha)?>&maquina=<?=h($maq)?>&turno=<?=h($turno)?>">
       Excedentes (Excel)
    </a>

    <!-- CSV genÃ©rico segÃºn mÃ©trica -->
    <a class="btn btn-outline-secondary btn-sm"
       href="reporte_diario.php?<?= http_build_query([
          'fecha'=>$fecha,
          'maquina'=>$maq,
          'turno'=>$turno,
          'metric'=>$metric,
          'csv'=>1
       ]) ?>">
       Descargar CSV (<?=$metricLabel?>)
    </a>

    <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">Imprimir</button>
  </div>
</div>


        <!-- Tabla -->
        <div class="report-card">
            <div class="smallmuted mb-2">
                Ordenado por <b>MÃ¡quina â†’ Turno</b>. Se listan <b>cargas separadas</b> por <b>Tipo (LS/F)</b>, <b>OP</b> y <b>DescripciÃ³n</b>.
                Se muestra la columna <b><?= strtolower($metricLabel) ?></b>.
                <?php if ($metric!=='buenas'): ?>
                  Solo se incluyen filas con <?= strtolower($metricLabel) ?> &gt; 0.
                <?php endif; ?>
                <?php if ($metric==='excedente'): ?>
                  &nbsp;AdemÃ¡s se indican las cajas informadas (si las hay).
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:110px">MÃ¡quina</th>
                            <th style="width:110px">Turno</th>
                            <th style="width:70px">Tipo</th>
                            <th style="width:120px">OP</th>
                            <th>DescripciÃ³n</th>
                            <?php if ($metric==='excedente'): ?>
                              <th style="width:160px">Caja excedente</th>
                              <th style="width:110px">Â¿Con caja?</th>
                            <?php endif; ?>
                            <th class="<?=$metricClass?> text-end" style="width:120px"><?=$metricLabel?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grandTotal = 0;
                        $hayDatos = false;

                        // cuÃ¡ntas columnas tiene la tabla para armar colspans dinÃ¡micos
                        $baseCols = 5; // maq, turno, tipo, op, desc
                        $extraCols = ($metric==='excedente') ? 2 : 0;
                        $totalCols = $baseCols + $extraCols + 1; // +1 por la mÃ©trica

                        foreach ($machines as $m) {
                          if (empty($data[$m])) continue;

                          $maqTotal = 0; // total por mÃ¡quina (mÃ©trica elegida)

                          foreach ([1,2,3] as $t) {
                            if (empty($data[$m][$t])) continue;
                            $hayDatos = true;

                            $badge = $t==1?'badge-m':($t==2?'badge-t':'badge-n');
                            $subTotal = 0;

                            foreach ($data[$m][$t] as $row) {
                              $valMetric = (int)$row[$metric];
                              // Por seguridad, para malas/excedente no mostramos 0
                              if ($metric !== 'buenas' && $valMetric <= 0) continue;

                              $subTotal += $valMetric;

                              $cajas = trim((string)($row['cajas'] ?? ''));
                              $hasCaja = ($cajas !== '' && $cajas !== '0');

                              echo '<tr>';
                              echo '<td><span class="badge bg-secondary">'.$m.'</span></td>';
                              echo '<td><span class="badge badge-turno '.$badge.'">'.turnoName($t).'</span></td>';
                              echo '<td><span class="badge '.($row['tipo']==='LS'?'text-bg-primary':'text-bg-warning').'">'.$row['tipo'].'</span></td>';
                              echo '<td><code>'.h($row['op']).'</code></td>';
                              echo '<td>'.h($row['desc']).'</td>';

                              if ($metric==='excedente') {
                                echo '<td>'.($cajas !== '' ? h($cajas) : '<span class="text-muted">â€”</span>').'</td>';
                                echo '<td>'.(
                                  $hasCaja
                                    ? '<span class="badge text-bg-success">SÃ­</span>'
                                    : '<span class="badge text-bg-danger">No</span>'
                                ).'</td>';
                              }

                              echo '<td class="text-end '.$metricClass.'">'.number_format($valMetric,0,',','.').'</td>';
                              echo '</tr>';
                            }

                            if ($subTotal > 0) {
                              // Subtotal (MÃ¡quina, Turno)
                              echo '<tr class="row-subtotal">';
                              echo '<td colspan="'.($totalCols-1).'">Subtotal '.$m.' Â· '.turnoName($t).'</td>';
                              echo '<td class="text-end '.$metricClass.'">'.number_format($subTotal,0,',','.').'</td>';
                              echo '</tr>';

                              $maqTotal += $subTotal;
                            }
                          }

                          if ($maqTotal > 0) {
                            // total por mÃ¡quina
                            echo '<tr class="row-maq-total">';
                            echo '<td colspan="'.($totalCols-1).'">Total '.$m.'</td>';
                            echo '<td class="text-end '.$metricClass.'">'.number_format($maqTotal,0,',','.').'</td>';
                            echo '</tr>';

                            $grandTotal += $maqTotal;
                          }
                        }

                        if (!$hayDatos || $grandTotal===0) {
                          echo '<tr><td colspan="'.$totalCols.'" class="text-center text-muted py-4">Sin datos para los filtros seleccionados.</td></tr>';
                        } else {
                          echo '<tr class="row-grand">';
                          echo '<td colspan="'.($totalCols-1).'">TOTAL GENERAL ('.$metricLabel.')</td>';
                          echo '<td class="text-end '.$metricClass.'">'.number_format($grandTotal,0,',','.').'</td>';
                          echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
