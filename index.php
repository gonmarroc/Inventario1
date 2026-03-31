<?php
$archivo = __DIR__ . '/inventario.json';

if (!file_exists($archivo)) {
    file_put_contents($archivo, '[]');
}

function leerInventario($ruta) {
    $contenido = @file_get_contents($ruta);
    if ($contenido === false || trim($contenido) === '') {
        return [];
    }
    $json = json_decode($contenido, true);
    return is_array($json) ? $json : [];
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(leerInventario($archivo), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $datos = json_decode($raw, true);

        if (!is_array($datos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'JSON no válido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo generar el JSON'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (@file_put_contents($archivo, $json, LOCK_EX) === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar inventario.json'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventario Fundación - Merchandising</title>
  <style>
    :root {
      --bg: #f4f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --success: #15803d;
      --danger: #dc2626;
      --warning: #d97706;
      --border: #e5e7eb;
      --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
      --radius: 18px;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, Arial, Helvetica, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 24px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    h1 {
      margin: 0;
      font-size: 2rem;
      line-height: 1.1;
    }

    .subtitle {
      color: var(--muted);
      margin-top: 6px;
    }

    .btn {
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.2s ease;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover { background: var(--primary-dark); }
    .btn-outline {
      background: white;
      border: 1px solid var(--border);
      color: var(--text);
    }

    .btn-success { background: var(--success); color: white; }
    .btn-danger { background: var(--danger); color: white; }
    .btn-warning { background: var(--warning); color: white; }
    .btn-small { padding: 8px 12px; font-size: 0.92rem; }

    .stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }

    .card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border: 1px solid rgba(229,231,235,0.8);
    }

    .stat-card {
      padding: 20px;
    }

    .stat-label {
      color: var(--muted);
      font-size: 0.95rem;
      margin-bottom: 10px;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
    }

    .toolbar {
      display: grid;
      grid-template-columns: 1.4fr 1fr 1fr auto auto;
      gap: 12px;
      padding: 18px;
      margin-bottom: 20px;
      align-items: center;
    }

    input, select, textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font: inherit;
      background: white;
    }

    textarea {
      min-height: 110px;
      resize: vertical;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px;
    }

    .product-card {
      overflow: hidden;
    }

    .product-image {
      width: 100%;
      height: 220px;
      object-fit: cover;
      display: block;
      background: #e5e7eb;
    }

    .product-body {
      padding: 18px;
    }

    .product-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: start;
    }

    .product-title {
      margin: 0 0 6px;
      font-size: 1.2rem;
    }

    .muted { color: var(--muted); }

    .badge {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 700;
      background: #eff6ff;
      color: #1d4ed8;
    }

    .badge-low {
      background: #fff7ed;
      color: #c2410c;
    }

    .stock {
      font-size: 1.8rem;
      font-weight: 700;
      margin: 16px 0 6px;
    }

    .product-actions, .move-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .move-controls {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px solid var(--border);
    }

    .move-controls label,
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .history {
      margin-top: 16px;
      border-top: 1px solid var(--border);
      padding-top: 14px;
    }

    .history-item {
      padding: 10px 0;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.95rem;
    }

    .empty {
      text-align: center;
      padding: 48px 20px;
      color: var(--muted);
    }

    .modal {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 50;
    }

    .modal.open { display: flex; }

    .modal-content {
      width: 100%;
      max-width: 760px;
      max-height: 92vh;
      overflow: auto;
      background: white;
      border-radius: 24px;
      box-shadow: var(--shadow);
      padding: 24px;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin-bottom: 18px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
    }

    .full { grid-column: 1 / -1; }

    .preview {
      width: 100%;
      max-height: 250px;
      object-fit: cover;
      border-radius: 16px;
      border: 1px solid var(--border);
      margin-top: 10px;
      display: none;
    }

    .footer-note {
      margin-top: 24px;
      color: var(--muted);
      font-size: 0.92rem;
      text-align: center;
    }

    .status {
      display: none;
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: 12px;
      font-weight: 600;
    }

    .status.ok {
      display: block;
      background: #ecfdf5;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .status.error {
      display: block;
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    @media (max-width: 1024px) {
      .stats { grid-template-columns: repeat(2, 1fr); }
      .grid { grid-template-columns: repeat(2, 1fr); }
      .toolbar { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 720px) {
      .stats, .grid, .form-grid, .toolbar { grid-template-columns: 1fr; }
      .product-top, .header { flex-direction: column; align-items: stretch; }
      .container { padding: 16px; }
      h1 { font-size: 1.6rem; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1>Inventario de Merchandising</h1>
        <div class="subtitle">Gestión sencilla para una Fundación: fotos, altas, bajas y control de stock.</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn btn-outline" onclick="exportData()">Exportar JSON</button>
        <label class="btn btn-outline" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
          Importar JSON
          <input type="file" accept="application/json" style="display:none;" onchange="importData(event)">
        </label>
        <button class="btn btn-primary" onclick="openModal()">+ Nuevo producto</button>
      </div>
    </div>

    <div id="statusBox" class="status"></div>

    <div class="stats">
      <div class="card stat-card">
        <div class="stat-label">Productos</div>
        <div class="stat-value" id="statProducts">0</div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Unidades totales</div>
        <div class="stat-value" id="statUnits">0</div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Valor estimado</div>
        <div class="stat-value" id="statValue">0 €</div>
      </div>
      <div class="card stat-card">
        <div class="stat-label">Con stock bajo</div>
        <div class="stat-value" id="statLow">0</div>
      </div>
    </div>

    <div class="card toolbar">
      <input id="searchInput" type="text" placeholder="Buscar por nombre, categoría o descripción" />
      <select id="categoryFilter"></select>
      <select id="sortFilter">
        <option value="updated">Más recientes</option>
        <option value="name">Nombre A-Z</option>
        <option value="stockAsc">Stock ascendente</option>
        <option value="stockDesc">Stock descendente</option>
      </select>
      <button class="btn btn-outline" onclick="seedDemoData()">Datos demo</button>
      <button class="btn btn-outline" onclick="resetData()">Vaciar</button>
    </div>

    <div id="productGrid" class="grid"></div>
    <div class="footer-note">Los cambios se guardan en <strong>inventario.json</strong> en el servidor.</div>
  </div>

  <div class="modal" id="productModal">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 id="modalTitle" style="margin:0;">Nuevo producto</h2>
          <div class="muted">Añade foto, stock y datos básicos del artículo.</div>
        </div>
        <button class="btn btn-outline btn-small" onclick="closeModal()">Cerrar</button>
      </div>

      <form id="productForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre</label>
            <input type="text" id="nombre" required />
          </div>
          <div class="form-group">
            <label>Categoría</label>
            <input type="text" id="categoria" placeholder="Ej. Ropa, Hogar, Accesorios" required />
          </div>
          <div class="form-group">
            <label>¿Este producto tiene tallas?</label>
            <select id="usaTallas" onchange="toggleSizesField()">
              <option value="no">No</option>
              <option value="si">Sí</option>
            </select>
          </div>
          <div class="form-group">
            <label>Stock inicial</label>
            <input type="number" id="stock" min="0" value="0" required />
          </div>
          <div class="form-group">
            <label>Stock mínimo</label>
            <input type="number" id="minimo" min="0" value="0" required />
          </div>
          <div class="form-group">
            <label>Precio (€)</label>
            <input type="number" id="precio" min="0" step="0.01" value="0" />
          </div>
          <div class="form-group full">
            <label>URL de imagen</label>
            <input type="text" id="imagen" placeholder="https://..." oninput="updatePreview()" />
          </div>
          <div class="form-group full">
            <label>O subir imagen</label>
            <input type="file" id="imagenFile" accept="image/*" onchange="handleImageUpload(event)" />
          </div>
          <div class="form-group full" id="sizesField" style="display:none;">
            <label>Tallas y stock por talla</label>
            <input type="text" id="tallas" placeholder="Ej. S:10, M:15, L:8, XL:4" />
            <div class="muted" style="margin-top:6px;">Formato: TALLA:cantidad, separadas por comas.</div>
          </div>
          <div class="form-group full">
            <label>Descripción</label>
            <textarea id="descripcion" placeholder="Notas internas, uso, proveedor, campaña..."></textarea>
          </div>
        </div>
        <img id="imagePreview" class="preview" alt="Vista previa" />
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar producto</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const API_URL = 'index.php?api=1';
    let items = [];
    let editingId = null;

    const demoItems = [
      {
        id: crypto.randomUUID(),
        nombre: 'Camiseta solidaria',
        categoria: 'Ropa',
        stock: 24,
        minimo: 8,
        precio: 12,
        descripcion: 'Camiseta oficial de la fundación para eventos.',
        imagen: 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=1200&auto=format&fit=crop',
        usaTallas: true,
        tallasStock: { S: 6, M: 8, L: 6, XL: 4 },
        historial: [],
        updatedAt: new Date().toISOString()
      },
      {
        id: crypto.randomUUID(),
        nombre: 'Taza institucional',
        categoria: 'Hogar',
        stock: 15,
        minimo: 5,
        precio: 8.5,
        descripcion: 'Taza con logo para acciones solidarias y regalos.',
        imagen: 'https://images.unsplash.com/photo-1514228742587-6b1558fcf93a?q=80&w=1200&auto=format&fit=crop',
        usaTallas: false,
        tallasStock: {},
        historial: [],
        updatedAt: new Date().toISOString()
      },
      {
        id: crypto.randomUUID(),
        nombre: 'Bolsa reutilizable',
        categoria: 'Accesorios',
        stock: 32,
        minimo: 10,
        precio: 4,
        descripcion: 'Bolsa de merchandising para ferias y campañas.',
        imagen: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1200&auto=format&fit=crop',
        usaTallas: false,
        tallasStock: {},
        historial: [],
        updatedAt: new Date().toISOString()
      }
    ];

    function showStatus(message, type) {
      const box = document.getElementById('statusBox');
      box.className = 'status ' + type;
      box.textContent = message;
      clearTimeout(showStatus.timer);
      showStatus.timer = setTimeout(() => {
        box.className = 'status';
        box.textContent = '';
      }, 3000);
    }

    async function loadItems() {
      try {
        const res = await fetch(API_URL, { cache: 'no-store' });
        if (!res.ok) throw new Error('No se pudo cargar');
        const data = await res.json();
        items = Array.isArray(data) ? data : [];
        renderProducts();
      } catch (err) {
        console.error(err);
        showStatus('No se pudo cargar el inventario del servidor.', 'error');
      }
    }

    async function saveItems() {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(items)
      });

      let data = {};
      try { data = await res.json(); } catch (e) {}

      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'No se pudo guardar');
      }
    }

    function formatCurrency(value) {
      return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
    }

    function formatDate(date) {
      return new Date(date).toLocaleString('es-ES');
    }

    function getItemTotalStock(item) {
      if (item.usaTallas && item.tallasStock && typeof item.tallasStock === 'object') {
        return Object.values(item.tallasStock).reduce((sum, qty) => sum + Number(qty || 0), 0);
      }
      return Number(item.stock || 0);
    }

    function updateStats() {
      const totalUnits = items.reduce((sum, item) => sum + getItemTotalStock(item), 0);
      const totalValue = items.reduce((sum, item) => sum + getItemTotalStock(item) * Number(item.precio || 0), 0);
      const lowStock = items.filter(item => getItemTotalStock(item) <= Number(item.minimo)).length;

      document.getElementById('statProducts').textContent = items.length;
      document.getElementById('statUnits').textContent = totalUnits;
      document.getElementById('statValue').textContent = formatCurrency(totalValue);
      document.getElementById('statLow').textContent = lowStock;
    }

    function getFilters() {
      return {
        search: document.getElementById('searchInput').value.toLowerCase().trim(),
        category: document.getElementById('categoryFilter').value,
        sort: document.getElementById('sortFilter').value
      };
    }

    function renderCategories() {
      const select = document.getElementById('categoryFilter');
      const categories = ['Todas', ...new Set(items.map(i => i.categoria).filter(Boolean))];
      const current = select.value || 'Todas';
      select.innerHTML = categories.map(cat => `<option value="${cat}">${cat}</option>`).join('');
      select.value = categories.includes(current) ? current : 'Todas';
    }

    function filteredItems() {
      const { search, category, sort } = getFilters();
      let list = items.filter(item => {
        const text = `${item.nombre} ${item.categoria} ${item.descripcion || ''}`.toLowerCase();
        const matchesSearch = !search || text.includes(search);
        const matchesCategory = category === 'Todas' || item.categoria === category;
        return matchesSearch && matchesCategory;
      });

      list.sort((a, b) => {
        if (sort === 'name') return a.nombre.localeCompare(b.nombre);
        if (sort === 'stockAsc') return getItemTotalStock(a) - getItemTotalStock(b);
        if (sort === 'stockDesc') return getItemTotalStock(b) - getItemTotalStock(a);
        return new Date(b.updatedAt) - new Date(a.updatedAt);
      });

      return list;
    }

    function renderProducts() {
      updateStats();
      renderCategories();
      const grid = document.getElementById('productGrid');
      const list = filteredItems();

      if (!list.length) {
        grid.innerHTML = '<div class="card empty" style="grid-column:1/-1;">No hay productos que coincidan con la búsqueda.</div>';
        return;
      }

      grid.innerHTML = list.map(item => {
        const totalStock = getItemTotalStock(item);
        const low = totalStock <= Number(item.minimo);
        const sizes = item.usaTallas && item.tallasStock ? Object.entries(item.tallasStock) : [];
        const history = (item.historial || []).slice(-4).reverse().map(h => `
          <div class="history-item">
            <strong>${h.tipo === 'entrada' ? 'Entrada' : 'Salida'}</strong> de ${h.cantidad} uds · ${formatDate(h.fecha)}<br>
            <span class="muted">${h.talla ? `Talla ${h.talla} · ` : ''}${h.nota || 'Sin nota'} · Stock resultante: ${h.stockResultante}</span>
          </div>
        `).join('');

        return `
          <div class="card product-card">
            <img class="product-image" src="${item.imagen || 'https://via.placeholder.com/800x500?text=Sin+imagen'}" alt="${escapeHtml(item.nombre)}" onerror="this.src='https://via.placeholder.com/800x500?text=Sin+imagen'">
            <div class="product-body">
              <div class="product-top">
                <div>
                  <h3 class="product-title">${escapeHtml(item.nombre)}</h3>
                  <div class="muted">${escapeHtml(item.categoria)}</div>
                </div>
                <span class="badge ${low ? 'badge-low' : ''}">${low ? 'Stock bajo' : 'Disponible'}</span>
              </div>

              <div class="stock">${totalStock}</div>
              <div class="muted">Mínimo recomendado: ${item.minimo} · Precio: ${formatCurrency(item.precio)}</div>
              ${sizes.length ? `<div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">${sizes.map(([size, qty]) => `<span class="badge">${escapeHtml(size)}: ${qty}</span>`).join('')}</div>` : ''}
              <p style="margin-top:12px;">${escapeHtml(item.descripcion || 'Sin descripción')}</p>

              <div class="move-controls">
                ${sizes.length ? `
                  <label>Talla</label>
                  <select id="size-${item.id}">
                    ${sizes.map(([size]) => `<option value="${escapeHtml(size)}">${escapeHtml(size)}</option>`).join('')}
                  </select>
                ` : ''}
                <label style="margin-top:${sizes.length ? '10px' : '0'};">Cantidad a mover</label>
                <input type="number" id="qty-${item.id}" min="1" value="1">
                <label style="margin-top:10px;">Nota del movimiento</label>
                <input type="text" id="note-${item.id}" placeholder="Ej. Venta en evento, reposición, donación...">
                <div class="move-row">
                  <button class="btn btn-success btn-small" onclick="changeStock('${item.id}', 'entrada')">+ Añadir</button>
                  <button class="btn btn-warning btn-small" onclick="changeStock('${item.id}', 'salida')">- Descontar</button>
                </div>
              </div>

              <div class="product-actions">
                <button class="btn btn-outline btn-small" onclick="editItem('${item.id}')">Editar</button>
                <button class="btn btn-danger btn-small" onclick="deleteItem('${item.id}')">Eliminar</button>
              </div>

              <div class="history">
                <strong>Últimos movimientos</strong>
                ${history || '<div class="history-item muted">Todavía no hay movimientos registrados.</div>'}
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    function toggleSizesField() {
      const useSizes = document.getElementById('usaTallas').value === 'si';
      document.getElementById('sizesField').style.display = useSizes ? 'block' : 'none';
      document.getElementById('stock').disabled = useSizes;
      document.getElementById('stock').style.opacity = useSizes ? '0.6' : '1';
    }

    function parseSizes(text) {
      const result = {};
      if (!text.trim()) return result;
      const pairs = text.split(',');
      for (const pair of pairs) {
        const [sizeRaw, qtyRaw] = pair.split(':');
        const size = (sizeRaw || '').trim();
        const qty = Number((qtyRaw || '').trim());
        if (size && !Number.isNaN(qty) && qty >= 0) {
          result[size] = qty;
        }
      }
      return result;
    }

    function sizesToText(obj) {
      if (!obj || typeof obj !== 'object') return '';
      return Object.entries(obj).map(([size, qty]) => `${size}:${qty}`).join(', ');
    }

    function updatePreview() {
      const img = document.getElementById('imagePreview');
      const url = document.getElementById('imagen').value.trim();
      if (url) {
        img.src = url;
        img.style.display = 'block';
      } else {
        img.style.display = 'none';
      }
    }

    function handleImageUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('imagen').value = e.target.result;
        updatePreview();
      };
      reader.readAsDataURL(file);
    }

    function openModal(id = null) {
      editingId = id;
      document.getElementById('productModal').classList.add('open');
      document.getElementById('productForm').reset();
      document.getElementById('imagePreview').style.display = 'none';
      document.getElementById('modalTitle').textContent = id ? 'Editar producto' : 'Nuevo producto';
      document.getElementById('usaTallas').value = 'no';
      document.getElementById('tallas').value = '';
      toggleSizesField();

      if (id) {
        const item = items.find(i => i.id === id);
        if (!item) return;
        document.getElementById('nombre').value = item.nombre || '';
        document.getElementById('categoria').value = item.categoria || '';
        document.getElementById('stock').value = item.stock || 0;
        document.getElementById('minimo').value = item.minimo || 0;
        document.getElementById('precio').value = item.precio || 0;
        document.getElementById('imagen').value = item.imagen || '';
        document.getElementById('descripcion').value = item.descripcion || '';
        document.getElementById('usaTallas').value = item.usaTallas ? 'si' : 'no';
        document.getElementById('tallas').value = sizesToText(item.tallasStock || {});
        toggleSizesField();
        updatePreview();
      }
    }

    function closeModal() {
      document.getElementById('productModal').classList.remove('open');
      editingId = null;
    }

    async function persistAndRender(message) {
      try {
        await saveItems();
        renderProducts();
        if (message) showStatus(message, 'ok');
      } catch (err) {
        console.error(err);
        showStatus(err.message || 'No se pudo guardar en el servidor.', 'error');
      }
    }

    document.getElementById('productForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      const usaTallas = document.getElementById('usaTallas').value === 'si';
      const tallasStock = usaTallas ? parseSizes(document.getElementById('tallas').value) : {};
      const stockTotal = usaTallas
        ? Object.values(tallasStock).reduce((a, b) => a + Number(b || 0), 0)
        : Number(document.getElementById('stock').value || 0);

      const data = {
        nombre: document.getElementById('nombre').value.trim(),
        categoria: document.getElementById('categoria').value.trim(),
        stock: stockTotal,
        minimo: Number(document.getElementById('minimo').value || 0),
        precio: Number(document.getElementById('precio').value || 0),
        imagen: document.getElementById('imagen').value.trim(),
        descripcion: document.getElementById('descripcion').value.trim(),
        usaTallas,
        tallasStock,
        updatedAt: new Date().toISOString()
      };

      if (!data.nombre || !data.categoria) {
        alert('Completa al menos el nombre y la categoría.');
        return;
      }

      if (usaTallas && Object.keys(tallasStock).length === 0) {
        alert('Añade tallas en formato como S:10, M:15, L:8');
        return;
      }

      if (editingId) {
        items = items.map(item => item.id === editingId ? { ...item, ...data } : item);
      } else {
        items.unshift({
          id: crypto.randomUUID(),
          ...data,
          historial: []
        });
      }

      await persistAndRender('Producto guardado correctamente.');
      closeModal();
    });

    function editItem(id) {
      openModal(id);
    }

    async function deleteItem(id) {
      const item = items.find(i => i.id === id);
      if (!item) return;
      if (!confirm(`¿Eliminar "${item.nombre}" del inventario?`)) return;
      items = items.filter(i => i.id !== id);
      await persistAndRender('Producto eliminado.');
    }

    async function changeStock(id, type) {
      const qtyInput = document.getElementById(`qty-${id}`);
      const noteInput = document.getElementById(`note-${id}`);
      const sizeInput = document.getElementById(`size-${id}`);
      const qty = Number(qtyInput.value || 0);
      const note = noteInput.value.trim();
      const selectedSize = sizeInput ? sizeInput.value : null;

      if (qty <= 0) {
        alert('La cantidad debe ser mayor que 0.');
        return;
      }

      items = items.map(item => {
        if (item.id !== id) return item;

        let newStock = Number(item.stock || 0);
        let tallasStock = { ...(item.tallasStock || {}) };

        if (item.usaTallas && selectedSize) {
          const currentSizeStock = Number(tallasStock[selectedSize] || 0);
          if (type === 'entrada') {
            tallasStock[selectedSize] = currentSizeStock + qty;
          } else {
            if (qty > currentSizeStock) {
              alert('No puedes descontar más unidades de esa talla de las que hay disponibles.');
              return item;
            }
            tallasStock[selectedSize] = currentSizeStock - qty;
          }
          newStock = Object.values(tallasStock).reduce((sum, value) => sum + Number(value || 0), 0);
        } else {
          if (type === 'entrada') {
            newStock += qty;
          } else {
            if (qty > newStock) {
              alert('No puedes descontar más unidades de las que hay en stock.');
              return item;
            }
            newStock -= qty;
          }
        }

        const movement = {
          id: crypto.randomUUID(),
          tipo: type,
          cantidad: qty,
          nota: note,
          talla: selectedSize || null,
          fecha: new Date().toISOString(),
          stockResultante: newStock
        };

        return {
          ...item,
          stock: newStock,
          tallasStock,
          updatedAt: new Date().toISOString(),
          historial: [...(item.historial || []), movement]
        };
      });

      await persistAndRender(type === 'entrada' ? 'Stock añadido.' : 'Stock descontado.');
    }

    function exportData() {
      const blob = new Blob([JSON.stringify(items, null, 2)], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'inventario-fundacion.json';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    async function importData(event) {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = async function(e) {
        try {
          const parsed = JSON.parse(e.target.result);
          if (!Array.isArray(parsed)) throw new Error('Formato incorrecto');
          items = parsed;
          await persistAndRender('Datos importados correctamente.');
        } catch (err) {
          console.error(err);
          alert('El archivo no tiene un formato válido.');
        }
      };
      reader.readAsText(file);
      event.target.value = '';
    }

    async function resetData() {
      if (!confirm('Esto borrará el inventario guardado en el servidor.')) return;
      items = [];
      await persistAndRender('Inventario vaciado.');
    }

    async function seedDemoData() {
      if (!confirm('Esto reemplazará los datos actuales por datos de ejemplo.')) return;
      items = JSON.parse(JSON.stringify(demoItems));
      await persistAndRender('Datos demo cargados.');
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text || '';
      return div.innerHTML;
    }

    document.getElementById('searchInput').addEventListener('input', renderProducts);
    document.getElementById('categoryFilter').addEventListener('change', renderProducts);
    document.getElementById('sortFilter').addEventListener('change', renderProducts);
    document.getElementById('productModal').addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });

    loadItems();
  </script>
</body>
</html>
