// ------- Utilidades -------
function toYMD(d){ return d.toISOString().slice(0,10); }

// ------- Elementos base -------
const turnoSel = document.getElementById('turno');
const nightWarn = document.getElementById('nightWarn');
const fechaInput = document.getElementById('fecha');
const setHoyBtn = document.getElementById('setHoy');
const setAyerBtn = document.getElementById('setAyer');

const container = document.getElementById('linesContainer');
const clearAllBtn = document.getElementById('clearAll');
const genNBtn = document.getElementById('genN');
const genNVal = document.getElementById('genNVal');
const tipoGlobalSel = document.getElementById('tipo_global');

const counterDisplay = document.getElementById('counterDisplay');
const cantidadInicial = document.getElementById('cantidadInicial');
const btnMinus = document.getElementById('btnMinus');
const btnPlus  = document.getElementById('btnPlus');

// ------- Lógica Turno Noche -------
turnoSel.addEventListener('change', () => {
  nightWarn.classList.toggle('d-none', turnoSel.value !== '3');
});
setHoyBtn?.addEventListener('click', () => { fechaInput.value = toYMD(new Date()); });
setAyerBtn?.addEventListener('click', () => { const d=new Date(); d.setDate(d.getDate()-1); fechaInput.value = toYMD(d); });

// ------- Habilitar / deshabilitar agregar hasta elegir tipo -------
function updateAddEnabled(){
  const enabled = !!tipoGlobalSel.value;
  genNBtn.disabled   = !enabled;
  btnMinus.disabled  = !enabled;
  btnPlus.disabled   = !enabled;
}
tipoGlobalSel.addEventListener('change', () => {
  tipoGlobalSel.setCustomValidity('');
  tipoGlobalSel.classList.remove('is-invalid');
  updateAddEnabled();
});
updateAddEnabled(); // estado inicial

// ------- Contador grande -------
function setCount(n){
  const v = Math.max(1, parseInt(n||'1',10));
  cantidadInicial.value = v;
  counterDisplay.textContent = v;
  genNVal.textContent = v;
}
btnMinus.addEventListener('click', () => setCount(parseInt(cantidadInicial.value,10)-1));
btnPlus .addEventListener('click', () => setCount(parseInt(cantidadInicial.value,10)+1));
setCount(1);

// ------- Helpers para inputs numéricos -------
function attachZeroHandlers(scope){
  scope.querySelectorAll('.num-auto0').forEach(el => {
    el.addEventListener('focus', () => { if (el.value === '0') el.value = ''; });
    el.addEventListener('blur',  () => { if (String(el.value).trim() === '') el.value = '0'; });
  });
}

// Uppercase dinámico para descripción del trabajo
document.addEventListener('input', (e) => {
  const el = e.target;
  if (el.matches('input[name^="linea"][name$="[desc_trabajo]"]')) {
    const pos = el.selectionStart;
    el.value = el.value.toLocaleUpperCase('es-AR');
    try { el.setSelectionRange(pos, pos); } catch(_) {}
  }
});

// ------- Generación de líneas -------
let idx = 0;
const accents = ['accent-1','accent-2','accent-3','accent-4','accent-5','accent-6','accent-7','accent-8'];

function lineCard() {
  if (!tipoGlobalSel.value) return;

  idx++;
  const i = idx;
  const accent = accents[(i-1)%accents.length];
  const type = tipoGlobalSel.value || '';

  const card = document.createElement('div');
  card.className = `card-line line-accent compact ${accent}`;
  card.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="line-title">#${i} <span class="line-chip line-chip-big">${type || '—'}</span></div>
      <button type="button" class="btn btn-danger-solid px-3 py-2 btn-remove">Eliminar</button>
    </div>

    <div class="row g-2">
      <div class="col-12 col-md-3">
        <label class="form-label lbl-codigo">${type==='F' ? 'Código F' : 'Código LS'}</label>
        <input name="linea[${i}][codigo]" class="form-control form-control-sm codigo" placeholder="${type==='F'?'000000':'00000'}" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Máquina</label>
        <select name="linea[${i}][maquina]" class="form-select form-select-sm" required>
          <option value="" selected disabled>-- Seleccionar --</option>
          <option value="SRI-1">SRI-1</option>
          <option value="SRI-2">SRI-2</option>
          <option value="SRI-3">SRI-3</option>
          <option value="SRI-4">SRI-4</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Cantidad total</label>
        <!-- CAMBIO: min=1 y SIN num-auto0 -->
        <input type="number" min="1" name="linea[${i}][cantidad_total]"
               class="form-control form-control-sm text-end" placeholder="0" required>
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-12 col-md-6">
        <label class="form-label">Descripción del trabajo</label>
        <input name="linea[${i}][desc_trabajo]" class="form-control form-control-sm"
               placeholder="Marca/Etiqueta" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Observaciones</label>
        <input name="linea[${i}][obs]" class="form-control form-control-sm" placeholder="Notas adicionales">
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-4 col-md-2">
        <label class="form-label label-good">Buenas</label>
        <!-- CAMBIO: min=1 y SIN num-auto0 -->
        <input type="number" min="1" name="linea[${i}][buenas]"
               class="form-control form-control-sm text-end" placeholder="0" required>
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label label-bad">Malas</label>
        <input type="number" min="0" name="linea[${i}][malas]"
               class="form-control form-control-sm num-auto0 text-end" placeholder="0" required>
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label label-extra">Excedente</label>
        <input type="number" min="0" name="linea[${i}][excedente]"
               class="form-control form-control-sm num-auto0 text-end" placeholder="0" required>
      </div>
      <div class="col-12 col-md-6 d-none excedente-caja">
        <label class="form-label">Caja excedente</label>
        <input name="linea[${i}][caja_excedente]"
               class="form-control form-control-sm" placeholder="Caja N°">
      </div>
    </div>
  `;

  const excedenteInput = card.querySelector(`[name="linea[${i}][excedente]"]`);
  const cajaExDiv      = card.querySelector('.excedente-caja');
  const cajaExInput    = card.querySelector(`[name="linea[${i}][caja_excedente]"]`);
  function syncCajaExc(){
    const ex = parseInt(excedenteInput.value || '0', 10);
    if (ex > 0) {
      cajaExDiv.classList.remove('d-none');
      cajaExInput.required = true;
    } else {
      cajaExDiv.classList.add('d-none');
      cajaExInput.required = false;
      cajaExInput.value = '';
    }
  }
  excedenteInput.addEventListener('input', syncCajaExc);
  syncCajaExc();

  card.querySelector('.btn-remove').addEventListener('click', () => card.remove());
  container.appendChild(card);

  attachZeroHandlers(card); // solo afecta a los que tienen .num-auto0 (malas y excedente)
}

// Click en "Agregar N líneas"
genNBtn.addEventListener('click', () => {
  if (!tipoGlobalSel.value) {
    tipoGlobalSel.setCustomValidity('Elegí el tipo de código antes de agregar líneas.');
    tipoGlobalSel.reportValidity();
    tipoGlobalSel.classList.add('is-invalid');
    tipoGlobalSel.focus();
    return;
  }
  const n = Math.max(1, parseInt(cantidadInicial.value || '1', 10));
  for (let k=0;k<n;k++) lineCard();
});

// Vaciar todas
clearAllBtn.addEventListener('click', () => {
  container.innerHTML = '';
  idx = 0;
});

// ------- Completar con 0 *antes* de la validación del navegador -------
const form = document.getElementById('opForm');
const saveBtn = document.querySelector('button[name="save_op"]');

function fillZeros() {
  // SOLO los campos con .num-auto0 (malas y excedente) se autollenan con 0 si están vacíos
  document.querySelectorAll('.num-auto0').forEach(el => {
    if (String(el.value).trim() === '') el.value = '0';
  });
}

saveBtn?.addEventListener('click', fillZeros);
form?.addEventListener('submit', fillZeros);
