/* ============================================================
   Rutas Seguras CBTis 258 - Lógica de la aplicación
   ============================================================ */

'use strict';

/* ---------------- Estado global ---------------- */
let map;
let currentShift = 'matutino';        // 'matutino' | 'vespertino'
let currentTransport = 'all';         // 'all' | 'walk' | 'bus'
let currentTypeFilter = '';
let searchQuery = '';
let searchDebounce = null;
let reports = [];
let selectedSeverity = 'media';
let pickedCoords = null;              // Coordenadas elegidas en el mapa / GPS
let tempMarker = null;
let pickingLocation = false;
let userMarker = null;

let routesLayerGroup;
let safePointsLayerGroup;
let reportsLayerGroup;

const API_URL = 'api/reports.php';
const CBTIS_COORDS = [25.7952, -100.3348];

/* ---------------- Datos estáticos ---------------- */
const safePoints = [
    { id: 1, name: 'CBTis 258 - Acceso Principal', type: 'school', coords: [25.7952, -100.3348], desc: 'Vigilancia en acceso principal y caseta del plantel.', icon: 'fa-school', color: '#6b1426' },
    { id: 2, name: 'Comercio Seguro - Abarrotes El Topo', type: 'store', coords: [25.7946, -100.3365], desc: 'Local iluminado con teléfono y auxilio vecinal.', icon: 'fa-store', color: '#2563eb' },
    { id: 3, name: 'Parada Iluminada Transmetro / Camión', type: 'bus', coords: [25.7965, -100.3315], desc: 'Parada sobre Av. Raúl Salinas con constante flujo de alumnos.', icon: 'fa-bus-simple', color: '#059669' },
    { id: 4, name: 'Caseta Auxiliar de Vigilancia', type: 'police', coords: [25.7938, -100.3380], desc: 'Módulo con presencia periódica de Policía Municipal de Escobedo.', icon: 'fa-shield-halved', color: '#7c3aed' }
];

const routePeatonal = [
    [25.7964, -100.3315],
    [25.7958, -100.3330],
    [25.7952, -100.3348]
];

const routeTransmetro = [
    [25.7985, -100.3305],
    [25.7964, -100.3315],
    [25.7940, -100.3325]
];

const SEVERITY_COLORS = { baja: '#10b981', media: '#f59e0b', alta: '#ef4444' };
const SEVERITY_LABELS = { baja: 'Baja', media: 'Media', alta: 'Alta' };

/* ============================================================
   Inicialización
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initMap();
    loadReports();
    loadStats();
});

/* ============================================================
   Tema oscuro / claro
   ============================================================ */
function initTheme() {
    const saved = localStorage.getItem('rs-theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
    }
    updateThemeIcon();
}

function toggleDarkMode() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('rs-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    updateThemeIcon();
}

function updateThemeIcon() {
    const icon = document.getElementById('theme-icon');
    const isDark = document.documentElement.classList.contains('dark');
    icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}

/* ============================================================
   Mapa
   ============================================================ */
function initMap() {
    map = L.map('map', { zoomControl: true }).setView(CBTIS_COORDS, 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors | CBTis 258 Rutas Seguras'
    }).addTo(map);

    routesLayerGroup = L.layerGroup().addTo(map);
    safePointsLayerGroup = L.layerGroup().addTo(map);
    reportsLayerGroup = L.layerGroup().addTo(map);

    drawRoutes();
    drawSafePoints();

    map.on('click', (e) => {
        if (!pickingLocation) return;
        setPickedCoords(e.latlng.lat, e.latlng.lng);
        stopMapPick();
        openReportModal();
    });
}

function drawRoutes() {
    routesLayerGroup.clearLayers();

    if (currentTransport === 'all' || currentTransport === 'walk') {
        const color = currentShift === 'vespertino' ? '#f59e0b' : '#10b981';
        const walkPolyline = L.polyline(routePeatonal, {
            color, weight: 6, opacity: 0.85,
            dashArray: currentShift === 'vespertino' ? '8, 8' : null
        }).addTo(routesLayerGroup);

        walkPolyline.bindPopup(`
            <div class="p-2">
                <span class="bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase px-2 py-0.5 rounded">Ruta Peatonal Principal</span>
                <h4 class="font-bold text-xs text-slate-800 mt-1">Av. Raúl Salinas ➔ Entrada CBTis 258</h4>
                <p class="text-[11px] text-slate-600 mt-1">Vía recomendada con mayor iluminación y paso de compañeros.</p>
            </div>
        `);
    }

    if (currentTransport === 'all' || currentTransport === 'bus') {
        const busPolyline = L.polyline(routeTransmetro, {
            color: '#2563eb', weight: 4, opacity: 0.7
        }).addTo(routesLayerGroup);

        busPolyline.bindPopup(`
            <div class="p-2">
                <span class="bg-blue-100 text-blue-800 font-bold text-[10px] uppercase px-2 py-0.5 rounded">Corredor Transmetro / Camión</span>
                <h4 class="font-bold text-xs text-slate-800 mt-1">Ruta 207 / Ruta 1 / Transmetro</h4>
                <p class="text-[11px] text-slate-600 mt-1">Conexión directa con la avenida principal.</p>
            </div>
        `);
    }
}

function createCustomIcon(iconClass, bgColor, extraClass = '') {
    return L.divIcon({
        html: `
            <div style="background-color: ${bgColor};" class="w-8 h-8 rounded-full flex items-center justify-center text-white shadow-lg border-2 border-white ${extraClass}">
                <i class="fa-solid ${iconClass} text-xs"></i>
            </div>
        `,
        className: 'custom-leaflet-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -28]
    });
}

function drawSafePoints() {
    safePointsLayerGroup.clearLayers();

    safePoints.forEach(point => {
        const marker = L.marker(point.coords, { icon: createCustomIcon(point.icon, point.color) })
            .addTo(safePointsLayerGroup);

        marker.bindPopup(`
            <div class="p-2 max-w-xs">
                <div class="flex items-center space-x-2 mb-1">
                    <span class="w-2 h-2 rounded-full" style="background-color: ${point.color}"></span>
                    <h4 class="font-bold text-xs text-slate-900">${point.name}</h4>
                </div>
                <p class="text-[11px] text-slate-600 leading-snug">${point.desc}</p>
            </div>
        `);
    });
}

function drawReportMarkers() {
    reportsLayerGroup.clearLayers();

    reports.forEach(report => {
        const color = report.status === 'resuelto' ? '#94a3b8' : (SEVERITY_COLORS[report.severity] || '#f59e0b');
        const marker = L.marker([report.latitude, report.longitude], {
            icon: createCustomIcon(report.status === 'resuelto' ? 'fa-check' : 'fa-triangle-exclamation', color)
        }).addTo(reportsLayerGroup);

        marker.bindPopup(`
            <div class="p-2">
                <span class="bg-amber-100 text-amber-800 font-bold text-[10px] uppercase px-1.5 py-0.5 rounded">${escapeHtml(report.type)}</span>
                <h4 class="font-bold text-xs text-slate-900 mt-1">${escapeHtml(report.location)}</h4>
                <p class="text-[11px] text-slate-600 mt-1">${escapeHtml(report.description)}</p>
                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
                    <span>${timeAgo(report.created_at)}</span>
                    <span class="text-amber-600 font-bold"><i class="fa-solid fa-thumbs-up mr-1"></i>${report.likes} apoyo</span>
                </div>
            </div>
        `);
    });
}

/* ============================================================
   API - Reportes
   ============================================================ */
async function loadReports() {
    const showResolved = document.getElementById('show-resolved').checked;
    const params = new URLSearchParams({ action: 'list' });
    if (!showResolved) params.set('status', 'activo');
    if (currentTypeFilter) params.set('type', currentTypeFilter);
    if (searchQuery) params.set('q', searchQuery);

    const container = document.getElementById('reports-container');
    container.innerHTML = `
        <div class="flex justify-center py-8 text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-xl"></i>
        </div>`;

    try {
        const res = await fetch(`${API_URL}?${params}`);
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'Error al cargar');
        reports = json.data;
        renderReports();
        drawReportMarkers();
    } catch (err) {
        container.innerHTML = `
            <div class="text-center py-6">
                <i class="fa-solid fa-plug-circle-xmark text-slate-300 text-2xl mb-2"></i>
                <p class="text-xs text-slate-400">No se pudieron cargar los reportes.<br>Verifica la conexión con la base de datos.</p>
            </div>`;
        showToast('Error al cargar reportes', 'error');
    }
}

async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=stats`);
        const json = await res.json();
        if (!json.ok) return;
        document.getElementById('stat-total').textContent = json.data.total;
        document.getElementById('stat-activos').textContent = json.data.activos;
        document.getElementById('stat-resueltos').textContent = json.data.resueltos;
    } catch (err) { /* silencioso */ }
}

async function handleReportSubmit(e) {
    e.preventDefault();

    if (!pickedCoords) {
        showToast('Indica la ubicación exacta con GPS o marcándola en el mapa', 'error');
        return;
    }

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Publicando...';

    const payload = {
        type: document.getElementById('report-type').value,
        location: document.getElementById('report-location').value.trim(),
        description: document.getElementById('report-description').value.trim(),
        severity: selectedSeverity,
        latitude: pickedCoords[0],
        longitude: pickedCoords[1]
    };

    try {
        const res = await fetch(`${API_URL}?action=create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'Error al guardar');

        showToast('Reporte publicado. ¡Gracias por ayudar!', 'success');
        closeReportModal();
        document.getElementById('report-form').reset();
        setSeverity('media');
        clearPickedCoords();
        await loadReports();
        loadStats();
        map.flyTo([json.data.latitude, json.data.longitude], 17);
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Publicar Reporte';
    }
}

async function likeReport(e, id) {
    e.stopPropagation();
    try {
        const res = await fetch(`${API_URL}?action=like`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error);

        const report = reports.find(r => Number(r.id) === Number(id));
        if (report) report.likes = json.likes;
        renderReports();
        drawReportMarkers();
    } catch (err) {
        showToast('No se pudo registrar el apoyo', 'error');
    }
}

/* ============================================================
   Renderizado de la lista
   ============================================================ */
function renderReports() {
    const container = document.getElementById('reports-container');
    container.innerHTML = '';

    if (reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fa-solid fa-shield-heart text-emerald-300 text-3xl mb-2"></i>
                <p class="text-xs text-slate-400">No hay reportes con estos filtros.<br>¡La zona está tranquila!</p>
            </div>`;
        return;
    }

    reports.forEach(report => {
        const sevColor = SEVERITY_COLORS[report.severity] || '#f59e0b';
        const isResolved = report.status === 'resuelto';

        const card = document.createElement('div');
        card.className = `p-3 bg-white dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition cursor-pointer ${isResolved ? 'opacity-60' : ''}`;
        card.onclick = () => {
            map.flyTo([report.latitude, report.longitude], 18);
            if (window.innerWidth < 768) closeSidebar();
        };

        card.innerHTML = `
            <div class="flex items-start justify-between gap-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border" style="color: ${sevColor}; background-color: ${sevColor}15; border-color: ${sevColor}40">
                    ${escapeHtml(report.type)}
                </span>
                <span class="text-[10px] text-slate-400 shrink-0">${timeAgo(report.created_at)}</span>
            </div>
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100 mt-1.5">${escapeHtml(report.location)}</h4>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">${escapeHtml(report.description)}</p>
            <div class="mt-2 flex items-center justify-between text-xs pt-1">
                <div class="flex items-center space-x-3">
                    <button onclick="likeReport(event, ${report.id})" class="text-[11px] text-slate-500 hover:text-cbtis-wine font-medium flex items-center space-x-1">
                        <i class="fa-regular fa-thumbs-up"></i><span>Útil (${report.likes})</span>
                    </button>
                    ${isResolved ? '<span class="text-[11px] text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i>Resuelto</span>' : ''}
                </div>
                <span class="text-[10px] font-bold flex items-center" style="color: ${sevColor}">
                    Riesgo ${SEVERITY_LABELS[report.severity] || 'Media'}
                </span>
            </div>
        `;
        container.appendChild(card);
    });
}

/* ============================================================
   Filtros y búsqueda
   ============================================================ */
function onSearchInput(value) {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        searchQuery = value.trim();
        loadReports();
    }, 350);
}

function setTypeFilter(type) {
    currentTypeFilter = type;
    document.querySelectorAll('.type-filter-btn').forEach(btn => {
        const active = btn.dataset.type === type;
        btn.className = `type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full ${active
            ? 'bg-cbtis-wine text-white'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'}`;
    });
    loadReports();
}

function setShift(shift) {
    currentShift = shift;
    const btnMat = document.getElementById('btn-matutino');
    const btnVesp = document.getElementById('btn-vespertino');
    const nightBanner = document.getElementById('night-warning-banner');

    const activeCls = 'py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center space-x-2 transition shadow';
    const inactiveCls = 'py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center space-x-2 transition text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white';

    if (shift === 'matutino') {
        btnMat.className = activeCls + ' bg-amber-500 text-slate-950';
        btnVesp.className = inactiveCls;
        nightBanner.classList.add('hidden');
    } else {
        btnVesp.className = activeCls + ' bg-indigo-600 text-white';
        btnMat.className = inactiveCls;
        nightBanner.classList.remove('hidden');
    }

    drawRoutes();
}

function filterTransport(type) {
    currentTransport = type;
    document.querySelectorAll('.transport-tab').forEach(tab => {
        tab.className = 'transport-tab py-2 rounded-lg text-center text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-medium';
    });
    const activeTab = document.getElementById(`tab-${type}`);
    if (activeTab) {
        activeTab.className = 'transport-tab py-2 rounded-lg text-center bg-white dark:bg-slate-700 shadow-sm text-slate-800 dark:text-slate-100 font-semibold text-xs';
    }
    drawRoutes();
}

function toggleMapLayer(layer) {
    const groups = { routes: routesLayerGroup, safePoints: safePointsLayerGroup, reports: reportsLayerGroup };
    const ids = { routes: 'layer-routes', safePoints: 'layer-safe-points', reports: 'layer-reports' };
    const checked = document.getElementById(ids[layer]).checked;
    checked ? map.addLayer(groups[layer]) : map.removeLayer(groups[layer]);
}

/* ============================================================
   Selección de ubicación (GPS / mapa)
   ============================================================ */
function setSeverity(level) {
    selectedSeverity = level;
    document.querySelectorAll('.severity-btn').forEach(btn => {
        const active = btn.dataset.severity === level;
        const colors = {
            baja: 'border-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600',
            media: 'border-amber-400 bg-amber-50 dark:bg-amber-500/10 text-amber-600',
            alta: 'border-red-400 bg-red-50 dark:bg-red-500/10 text-red-600'
        };
        btn.className = `severity-btn py-2 rounded-xl text-xs font-bold border-2 transition ${active ? colors[level] : 'border-slate-200 dark:border-slate-700 text-slate-500'}`;
    });
}

function setPickedCoords(lat, lng) {
    pickedCoords = [lat, lng];
    document.getElementById('coords-label').textContent = `📍 ${lat.toFixed(5)}, ${lng.toFixed(5)}`;

    if (tempMarker) map.removeLayer(tempMarker);
    tempMarker = L.marker(pickedCoords, {
        icon: createCustomIcon('fa-location-dot', '#6b1426', 'temp-marker-pulse')
    }).addTo(map);
}

function clearPickedCoords() {
    pickedCoords = null;
    document.getElementById('coords-label').textContent = 'Ubicación requerida: selecciona GPS o marca el mapa';
    if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
}

function startMapPick() {
    pickingLocation = true;
    document.body.classList.add('picking-location');
    closeReportModal();
    showToast('Toca el mapa para marcar la ubicación del reporte', 'info');
    if (window.innerWidth < 768) closeSidebar();
}

function stopMapPick() {
    pickingLocation = false;
    document.body.classList.remove('picking-location');
}

function useMyLocation() {
    if (!navigator.geolocation) {
        showToast('Tu navegador no soporta geolocalización', 'error');
        return;
    }
    showToast('Obteniendo tu ubicación...', 'info');
    navigator.geolocation.getCurrentPosition(
        pos => {
            setPickedCoords(pos.coords.latitude, pos.coords.longitude);
            map.flyTo(pickedCoords, 17);
            showToast('Ubicación detectada', 'success');
        },
        () => showToast('No se pudo obtener tu ubicación', 'error'),
        { enableHighAccuracy: true, timeout: 8000 }
    );
}

function locateMe() {
    if (!navigator.geolocation) {
        showToast('Tu navegador no soporta geolocalización', 'error');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            const coords = [pos.coords.latitude, pos.coords.longitude];
            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.marker(coords, { icon: createCustomIcon('fa-person', '#2563eb') }).addTo(map);
            userMarker.bindPopup('<p class="text-xs font-bold p-1">📍 Estás aquí</p>').openPopup();
            map.flyTo(coords, 17);
        },
        () => showToast('No se pudo obtener tu ubicación', 'error'),
        { enableHighAccuracy: true, timeout: 8000 }
    );
}

/* ============================================================
   Modales y panel lateral
   ============================================================ */
function openReportModal() {
    document.getElementById('report-modal').classList.remove('hidden');
}

function closeReportModal() {
    document.getElementById('report-modal').classList.add('hidden');
}

function toggleSOSModal() {
    document.getElementById('sos-modal').classList.toggle('hidden');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const icon = document.getElementById('sidebar-handle-icon');
    sidebar.classList.toggle('open');
    icon.className = sidebar.classList.contains('open') ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-up';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.remove('open');
    document.getElementById('sidebar-handle-icon').className = 'fa-solid fa-chevron-up';
}

// Cerrar modales con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeReportModal();
        document.getElementById('sos-modal').classList.add('hidden');
        stopMapPick();
    }
});

/* ============================================================
   Utilidades
   ============================================================ */
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const styles = {
        success: { icon: 'fa-circle-check', cls: 'bg-emerald-600' },
        error:   { icon: 'fa-circle-xmark', cls: 'bg-red-600' },
        info:    { icon: 'fa-circle-info',  cls: 'bg-slate-800' }
    };
    const s = styles[type] || styles.info;

    const toast = document.createElement('div');
    toast.className = `toast ${s.cls} text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `<i class="fa-solid ${s.icon}"></i><span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity .3s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr.replace(' ', 'T'));
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) return 'Justo ahora';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `Hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours} h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `Hace ${days} d`;
    return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
