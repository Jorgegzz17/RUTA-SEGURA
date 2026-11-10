/* ============================================================
   Rutas Seguras CBTis 258 - Lógica de la aplicación
   ============================================================ */

'use strict';

/* ---------------- Estado global ---------------- */
let map;
let currentTypeFilter = '';
let searchQuery = '';
let searchDebounce = null;
let reports = [];
let selectedSeverity = 'media';
let pickedCoords = null;
let tempMarker = null;
let pickingLocation = false;
let reportsLayerGroup;
let campusLayerGroup;
let safePointsLayerGroup;
let safeRoutesLayerGroup;

const API_URL = 'api/reports.php';
const CBTIS_COORDS = [25.7877647, -100.3296582];
let campusCoords = CBTIS_COORDS;
let campusMarker = null;

const SEVERITY_COLORS = { baja: '#10b981', media: '#f59e0b', alta: '#ef4444' };
const SEVERITY_LABELS = { baja: 'Baja', media: 'Media', alta: 'Alta' };

/* ============================================================
   Inicialización
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initMap();
    loadReports();
    loadMapLocations();
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
   Mapa y ubicaciones de reportes
   ============================================================ */
function initMap() {
    map = L.map('map', { zoomControl: true }).setView(CBTIS_COORDS, 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a> | CBTis 258 Rutas Seguras'
    }).addTo(map);

    reportsLayerGroup = L.layerGroup().addTo(map);
    campusLayerGroup = L.layerGroup().addTo(map);
    safePointsLayerGroup = L.layerGroup().addTo(map);
    safeRoutesLayerGroup = L.layerGroup().addTo(map);
    map.on('click', event => {
        if (!pickingLocation) return;
        setPickedCoords(event.latlng.lat, event.latlng.lng);
        stopMapPick();
        openReportModal();
    });
}

async function loadMapLocations() {
    try {
        const response = await fetch('api/locations.php?action=list');
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudieron cargar las ubicaciones');

        campusLayerGroup.clearLayers();
        safePointsLayerGroup.clearLayers();
        safeRoutesLayerGroup.clearLayers();

        const campus = result.data.campus;
        if (campus) {
            const coords = [Number(campus.latitude), Number(campus.longitude)];
            campusCoords = coords;
            campusMarker = L.marker(coords, { icon: createCustomIcon('fa-school', '#6b1426') }).addTo(campusLayerGroup);
            campusMarker.bindPopup(`
                <div class="p-2">
                    <strong>${escapeHtml(campus.name)}</strong>
                    <p class="text-xs mt-1">${escapeHtml(campus.address)}</p>
                    ${campus.description ? `<p class="text-xs mt-1">${escapeHtml(campus.description)}</p>` : ''}
                </div>
            `);
            map.setView(coords, 16);
        }

        result.data.safePoints.forEach(point => {
            const marker = L.marker([Number(point.latitude), Number(point.longitude)], {
                icon: createCustomIcon('fa-shield-halved', '#15803d')
            }).addTo(safePointsLayerGroup);
            marker.bindPopup(`
                <div class="p-2">
                    <strong>${escapeHtml(point.name)}</strong>
                    <p class="text-xs mt-1">${escapeHtml(point.address)}</p>
                    ${point.description ? `<p class="text-xs mt-1">${escapeHtml(point.description)}</p>` : ''}
                </div>
            `);
        });

        result.data.routes.forEach(route => {
            const line = L.polyline(
                route.points.map(point => [Number(point.latitude), Number(point.longitude)]),
                { color: route.color, weight: 5, opacity: 0.85 }
            ).addTo(safeRoutesLayerGroup);
            line.bindPopup(`
                <div class="p-2">
                    <strong>${escapeHtml(route.name)}</strong>
                    ${route.description ? `<p class="text-xs mt-1">${escapeHtml(route.description)}</p>` : ''}
                    <p class="text-xs mt-1">Ruta segura al plantel</p>
                </div>
            `);
        });
    } catch (error) {
        showToast(error.message, 'error');
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
        showToast('Selecciona una ubicación con GPS o marcando el mapa', 'error');
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
        moveMapTo([json.data.latitude, json.data.longitude], 17);
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
            <p class="text-xs text-slate-400">Todavía no hay reportes. Puedes agregar una ubicación verificada.</p>
            </div>`;
        return;
    }

    reports.forEach(report => {
        const sevColor = SEVERITY_COLORS[report.severity] || '#f59e0b';
        const isResolved = report.status === 'resuelto';

        const card = document.createElement('div');
        card.className = `p-3 bg-white dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition cursor-pointer ${isResolved ? 'opacity-60' : ''}`;
        card.onclick = () => {
            moveMapTo([report.latitude, report.longitude], 18);
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

function toggleMapLayer(layer) {
    const groups = {
        reports: reportsLayerGroup,
        routes: safeRoutesLayerGroup,
        campus: campusLayerGroup,
        safePoints: safePointsLayerGroup
    };
    const ids = { reports: 'layer-reports', routes: 'layer-routes', campus: 'layer-campus', safePoints: 'layer-safe-points' };
    const checked = document.getElementById(ids[layer]).checked;
    checked ? map.addLayer(groups[layer]) : map.removeLayer(groups[layer]);
}

function moveMapTo(coords, zoom) {
    map.flyTo(coords, zoom);
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
            moveMapTo(pickedCoords, 17);
            showToast('Ubicación detectada', 'success');
        },
        () => showToast('No se pudo obtener tu ubicación', 'error'),
        { enableHighAccuracy: true, timeout: 8000 }
    );
}

function goToCampus() {
    moveMapTo(campusCoords, 17);
    if (campusMarker) campusMarker.openPopup();
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
