<?php
// export_excel_excedentes.php
// Lista de líneas con excedente > 0, con fórmula BUSCARV para descripción de catálogo

session_start();
include("conexion.php"); // define $con (mysqli)

// Helpers
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t){ return $t==1?'Mañana':($t==2?'Tarde':($t==3?'Noche':'-')); }

// ===== Filtros =====
$fecha = g('fecha','');
$maq   = g('maquina','');
$turno = g('turno','');

if ($fecha==='') $fecha = date('Y-m-d');

// ===== WHERE =====
$where = "h.fecha = STR_TO_DATE('".mysqli_real_escape_string($con,$fecha)."','%Y-%m-%d')";
if ($maq!=='')   $where .= " AND i.maquina = '".mysqli_real_escape_string($con,$maq)."'";
if ($turno!=='') $where .= " AND h.turno = ".intval($turno);

// Solo líneas con excedente > 0
$where .= " AND i.excedente > 0";

// ===== Consulta base =====
$sql = "
  SELECT
    h.fecha,
    i.maquina,
    h.turno,
    i.tipo_codigo,
    h.op,
    i.codigo,
    i.excedente,
    i.caja_excedente,
    COALESCE(NULLIF(TRIM(i.desc_trabajo),''),'(Sin descripción)') AS desc_trabajo
  FROM op_item i
  JOIN op_header h ON h.id = i.header_id
  WHERE $where
  ORDER BY
    h.fecha ASC,
    i.maquina ASC,
    h.turno ASC,
    i.tipo_codigo ASC,
    h.op ASC,
    i.codigo ASC
";
$res = mysqli_query($con, $sql);

// ===== Cabeceras Excel =====
$filename = "reporte_excedentes_{$fecha}.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Ruta del archivo de códigos para la fórmula
$pathCodigos = "\\\\win08\\Archivos\\Label\\LABEL SOLUTIONS\\ARTICULOS\\[CodigoLS.xls]CODIGOS";

?>
<!DOCTYPE html>
<html lang="es">
<meta charset="utf-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; }
  .title { font-weight: 800; font-size: 16px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border:1px solid #d1d5db; padding:6px 8px; font-size:12px; }
  thead th { background:#f1f5f9; color:#0f172a; }
  .t-right { text-align:right; }
  .t-center{ text-align:center; }
</style>

<body>
  <div class="title">SRI · Reporte diario de EXCEDENTES</div>
  <div>
    Fecha: <b><?=h($fecha)?></b>
    &nbsp; | &nbsp; Máquina:
    <b><?= $maq ? h($maq) : 'Todas' ?></b>
    &nbsp; | &nbsp; Turno:
    <b><?= $turno ? turnoName((int)$turno) : 'Todos' ?></b>
  </div>
  <br>

  <table>
    <thead>
      <tr>
        <th>Fecha</th>               <!-- A -->
        <th>Máquina</th>             <!-- B -->
        <th>Turno</th>               <!-- C -->
        <th>Tipo</th>                <!-- D -->
        <th>OP</th>                  <!-- E -->
        <th>Código</th>              <!-- F -->
        <th>Excedente</th>           <!-- G -->
        <th>Caja excedente</th>      <!-- H -->
        <th>Descripción cargada</th> <!-- I -->
        <th>Descripción catálogo</th><!-- J (fórmula) -->
      </tr>
    </thead>
    <tbody>
<?php
$totalExcedente = 0;

// Queremos que el PRIMER dato quede en fila 5
// 1: título, 2: línea de filtros, 3: espacio, 4: encabezados, 5+: datos
$excelRow = 4;

if ($res && mysqli_num_rows($res) > 0):
  while ($row = mysqli_fetch_assoc($res)):
    $excelRow++; // 5, 6, 7...

    // Celda del código (columna F)
    $cellCodigo = 'F'.$excelRow;

    // Fórmula de descripción de catálogo, basada SIEMPRE en la columna F
    $formulaDesc = '=+SI(ESBLANCO('.$cellCodigo.');"";BUSCARV('
                  .$cellCodigo.
                  ';\''
                  .$pathCodigos
                  .'\'!$A$1:$J$65536;7;0))';

    $totalExcedente += (int)$row['excedente'];
?>
      <tr>
        <td><?= h($row['fecha']) ?></td>
        <td><?= h($row['maquina']) ?></td>
        <td><?= turnoName((int)$row['turno']) ?></td>
        <td><?= h($row['tipo_codigo']) ?></td>
        <td><?= h($row['op']) ?></td>
        <td><?= h($row['codigo']) ?></td>
        <td class="t-right"><?= (int)$row['excedente'] ?></td>
        <td><?= h($row['caja_excedente']) ?></td>
        <td><?= h($row['desc_trabajo']) ?></td>
        <!-- NO escapamos la fórmula, para que Excel la ejecute -->
        <td><?= $formulaDesc ?></td>
      </tr>
<?php
  endwhile;
else:
?>
      <tr>
        <td colspan="10" class="t-center">Sin datos de excedente para los filtros seleccionados.</td>
      </tr>
<?php
endif;
?>
      <?php if ($totalExcedente > 0): ?>
      <tr>
        <td colspan="6" class="t-right"><b>Total excedente</b></td>
        <td class="t-right"><b><?= (int)$totalExcedente ?></b></td>
        <td colspan="3"></td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
