<?php
session_start();
include("conexion.php"); // Debe definir $con (mysqli)

function p($name, $default=''){ return isset($_POST[$name]) ? $_POST[$name] : $default; }

$ok_msg = $err_msg = '';
$header_id = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_op'])) {
    // CABECERA
    $turno       = (int) p('turno', 1);
    $operario    = (int) p('operario', 0);
    $fecha       = mysqli_real_escape_string($con, trim(p('fecha', '')));
    $op          = mysqli_real_escape_string($con, trim(p('op')));
    $tipo_global = mysqli_real_escape_string($con, trim(p('tipo_global', ''))); // LS | F

    if (!$op || !$operario || !$fecha || !in_array($tipo_global, ['LS','F'])) {
        $err_msg = "Turno, Operario, Fecha, Tipo y OP son obligatorios.";
    } else {
        // 'hora' puede ser NULL. created_at lo setea MySQL
        $sqlH = "INSERT INTO op_header(fecha, hora, operario, turno, op)
                 VALUES (STR_TO_DATE('$fecha','%Y-%m-%d'), NULL, $operario, $turno, '$op')";
        if (mysqli_query($con, $sqlH)) {
            $header_id = mysqli_insert_id($con);

            // LÍNEAS (sin verificación de sumas; con campos separados)
            $lineas   = (isset($_POST['linea']) && is_array($_POST['linea'])) ? $_POST['linea'] : [];
            $ok_lines = 0;
            $line_num = 0;

            foreach ($lineas as $ln) {
                $line_num++;

                // Campos
                $codigo         = trim($ln['codigo']          ?? '');
                $maquina        = trim($ln['maquina']         ?? '');
                $cant_total_in  = trim((string)($ln['cantidad_total'] ?? ''));
                $buenas_in      = trim((string)($ln['buenas']         ?? ''));
                $malas_in       = trim((string)($ln['malas']          ?? ''));
                $exced_in       = trim((string)($ln['excedente']      ?? ''));
                $desc_trabajo   = mb_strtoupper(trim($ln['desc_trabajo'] ?? ''), 'UTF-8');
                $obs_txt        = trim($ln['obs']             ?? ''); // OPCIONAL
                $caja_exc       = trim($ln['caja_excedente']  ?? '');

                // Obligatorios básicos (obs NO es obligatorio)
                if ($codigo==='' || $maquina==='' || $cant_total_in==='' || $buenas_in==='' || $malas_in==='' || $exced_in==='') {
                    $err_msg = "Faltan campos obligatorios en la línea #$line_num.";
                    break;
                }

                // Casteo a enteros
                $cantidad_total = (int)$cant_total_in;
                $buenas         = (int)$buenas_in;
                $malas          = (int)$malas_in;
                $excedente      = (int)$exced_in;

                // Validaciones NUEVAS: cantidad_total y buenas deben ser > 0
                if ($cantidad_total <= 0) {
                    $err_msg = "La cantidad total de la línea #$line_num debe ser mayor a 0.";
                    break;
                }
                if ($buenas <= 0) {
                    $err_msg = "Las buenas de la línea #$line_num deben ser mayor a 0.";
                    break;
                }

                // Si hay excedente, exigir caja excedente
                if ($excedente > 0 && $caja_exc==='') {
                    $err_msg = "Indicá en qué caja guardar el excedente en la línea #$line_num.";
                    break;
                }

                // Escape strings
                $codigo        = mysqli_real_escape_string($con, $codigo);
                $maquina       = mysqli_real_escape_string($con, $maquina);
                $desc_trabajo  = mysqli_real_escape_string($con, $desc_trabajo);
                $obs_esc       = mysqli_real_escape_string($con, $obs_txt);
                $caja_esc      = mysqli_real_escape_string($con, $caja_exc);

                // INSERT (columnas separadas)
                $sqlI = "INSERT INTO op_item
                        (header_id, tipo_codigo, codigo, maquina, cantidad_total, buenas, malas, excedente, desc_trabajo, obs, caja_excedente)
                        VALUES
                        ($header_id, '$tipo_global', '$codigo', '$maquina', $cantidad_total, $buenas, $malas, $excedente, '$desc_trabajo', ".($obs_txt===''?"NULL":"'".$obs_esc."'").", ".($excedente>0 ? "'$caja_esc'" : "NULL").")";
                if (mysqli_query($con, $sqlI)) {
                    $ok_lines++;
                } else {
                    $err_msg = 'Error guardando línea #'.$line_num.': '.mysqli_error($con);
                    break;
                }
            }

            // Si hubo error en alguna línea, limpio el header para no dejarlo huérfano
            if (!empty($err_msg)) {
                if ($header_id) { mysqli_query($con, "DELETE FROM op_header WHERE id=$header_id"); }
            } else {
                $ok_msg = "✅ OP guardada. Líneas insertadas: $ok_lines.";
            }

        } else {
            $err_msg = "❌ Error guardando encabezado: ".mysqli_error($con);
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Empaque · SRI</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Nueva OP SRI</h1>
            <a href="index.php" class="btn btn-outline-secondary">← Volver</a>
        </div>

  <?php if($ok_msg): ?><div class="alert alert-success"><?=$ok_msg?></div><?php endif; ?>
  <?php if($err_msg): ?><div class="alert alert-danger"><?=$err_msg?></div><?php endif; ?>

  <form method="post" id="opForm">

    <!-- CABECERA -->
    <div class="section-card compact mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">OP (Orden de Producción)</label>
          <input type="text" name="op" class="form-control" placeholder="00000" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">N° Operario</label>
          <input type="number" name="operario" id="operario" class="form-control" min="0" placeholder="N° Legajo" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Turno</label>
          <select name="turno" id="turno" class="form-select" required>
            <option value="">-- Elegir turno --</option>
            <option value="1">1 - Mañana</option>
            <option value="2">2 - Tarde</option>
            <option value="3">3 - Noche</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" id="fecha" class="form-control" required>
        </div>
      </div>
    </div>

    <!-- ALERTA TURNO NOCHE -->
    <div class="col-12 d-none" id="nightWarn">
      <div class="alert-night d-flex p-3 mt-2">
        <div class="icon-pill me-3">🌙</div>
        <div>
          <h6 class="mb-1">Atención: Turno Noche</h6>
          <p class="mb-2">
            Si el trabajo comenzó antes de medianoche y siguió después de las 00:00,
            <b>no cambies la fecha</b>. Podés usar “Usar ayer”.
          </p>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-dark" id="setHoy">Usar hoy</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="setAyer">Usar ayer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- GENERADOR -->
    <div class="section-card generator-card compact mb-3">
      <div class="row g-4 align-items-center">
        <div class="col-12 col-lg-3">
          <label class="form-label">Tipo de códigos</label>
          <select name="tipo_global" id="tipo_global" class="form-select big-control" required>
            <option value="" selected disabled>Elegir tipo de código</option>
            <option value="LS">LS</option>
            <option value="F">F</option>
          </select>
        </div>

        <div class="col-12 col-lg-5">
          <label class="form-label d-block">¿Cuántos códigos querés agregar?</label>
          <div class="counter-wrap">
            <button class="counter-btn minus" type="button" id="btnMinus">−</button>
            <div class="counter-display" id="counterDisplay">1</div>
            <button class="counter-btn plus" type="button" id="btnPlus">+</button>
          </div>
          <input type="hidden" id="cantidadInicial" value="1">
        </div>

        <div class="col-12 col-lg-4 d-flex gap-2">
          <button class="btn btn-outline-primary flex-fill big-control" type="button" id="genN">
            Agregar <span id="genNVal">1</span> líneas
          </button>
          <button class="btn btn-danger-solid flex-fill big-control" type="button" id="clearAll">
            Vaciar todas
          </button>
        </div>
      </div>
    </div>

    <!-- LÍNEAS -->
    <div class="section-card compact mb-3">
      <div id="linesContainer" class="d-grid gap-3"></div>
    </div>

    <!-- Botones -->
    <div class="actions-bar d-flex flex-wrap gap-3">
      <button class="btn btn-primary px-4" name="save_op" type="submit">💾 Guardar OP</button>
      <a href="index.php" class="btn btn-outline-secondary px-4">Cancelar</a>
    </div>

  </form>
</div>

<script src="assets/app.js"></script>
</body>
</html>
