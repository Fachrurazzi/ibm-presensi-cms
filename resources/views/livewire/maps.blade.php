<div>
    <div class="p-6 min-h-screen" style="background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif;">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

        <style>
            .map-container {
                height: 750px;
                width: 100%;
                border-radius: 24px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                z-index: 1;
                position: relative;
            }

            .sidebar-card {
                background: #ffffff;
                border-radius: 24px;
                border: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .stat-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-radius: 16px;
                margin-bottom: 12px;
                border: 1px solid #f1f5f9;
            }

            .emp-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px;
                border-radius: 16px;
                background: #ffffff;
                border: 1px solid #f1f5f9;
                margin-bottom: 8px;
                transition: all 0.2s;
                cursor: pointer;
            }

            .emp-item:hover {
                border-color: #fbbf24;
                box-shadow: 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                transform: translateY(-1px);
            }

            .badge-status {
                padding: 2px 8px;
                border-radius: 8px;
                font-size: 9px;
                font-weight: 800;
                text-transform: uppercase;
                color: white;
                display: inline-block;
            }

            .custom-scroll::-webkit-scrollbar {
                width: 4px;
            }

            .custom-scroll::-webkit-scrollbar-thumb {
                background: #e2e8f0;
                border-radius: 10px;
            }

            .loading-spinner {
                position: absolute;
                top: 16px;
                right: 16px;
                z-index: 10;
                background: white;
                border-radius: 12px;
                padding: 8px 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 600;
                color: #f59e0b;
            }

            .animate-spin {
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }

                to {
                    transform: rotate(360deg);
                }
            }

            .custom-div-icon {
                background: transparent !important;
                border: none !important;
            }

            .marker-pin {
                width: 18px !important;
                height: 18px !important;
                border-radius: 50% !important;
                border: 3px solid white !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3) !important;
                position: relative !important;
            }

            .marker-pulse {
                position: absolute !important;
                top: -3px !important;
                left: -3px !important;
                width: 18px !important;
                height: 18px !important;
                border-radius: 50% !important;
                animation: pulse 2s infinite !important;
                opacity: 0.6 !important;
            }

            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 0.6;
                }

                100% {
                    transform: scale(3.5);
                    opacity: 0;
                }
            }

            .legend {
                display: flex;
                gap: 16px;
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #f1f5f9;
                flex-wrap: wrap;
            }

            .legend-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 10px;
                color: #64748b;
                font-weight: 600;
            }

            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 50%;
            }

            .reset-map-btn {
                padding: 12px 16px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                background: white;
                cursor: pointer;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.2s;
            }

            .reset-map-btn:hover {
                background: #f8fafc;
                border-color: #f59e0b;
            }

            .refresh-indicator {
                position: absolute;
                bottom: 20px;
                right: 20px;
                background: white;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                font-family: monospace;
                color: #64748b;
            }

            @media (max-width: 1024px) {
                .map-container {
                    height: 500px;
                }

                [style*="grid-template-columns: repeat(12, 1fr)"] {
                    grid-template-columns: 1fr !important;
                }
            }

            @media (max-width: 640px) {
                .map-container {
                    height: 400px;
                }

                .stat-row {
                    padding: 12px;
                }

                .emp-item {
                    padding: 10px;
                }
            }
        </style>

        <div style="max-width: 1800px; margin: 0 auto;">
            <!-- Header -->
            <div
                style="background: white; padding: 20px 24px; border-radius: 24px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a;">Monitoring Presensi
                        </h1>
                        <p
                            style="margin: 4px 0 0 0; font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                            Intiboga Real-time Tracking</p>
                    </div>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                        <input type="text" id="empSearch" placeholder="Cari nama..."
                            style="flex-grow: 1; padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 13px; outline: none;">
                        <select wire:model.live="filterType"
                            style="padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; font-weight: 600; font-size: 13px;">
                            <option value="today">📅 Hari Ini</option>
                            <option value="yesterday">📅 Kemarin</option>
                            <option value="week">📅 Minggu Ini</option>
                            <option value="month">📅 Bulan Ini</option>
                            <option value="custom">📅 Custom Range</option>
                        </select>
                        @if ($filterType === 'custom')
                            <div style="display: flex; gap: 8px;">
                                <input type="date" wire:model.live="startDate"
                                    value="{{ $startDate ?? now()->toDateString() }}"
                                    style="padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                                <span style="align-self: center;">→</span>
                                <input type="date" wire:model.live="endDate"
                                    value="{{ $endDate ?? now()->toDateString() }}"
                                    style="padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            </div>
                        @endif
                        <select wire:model.live="selectedOffice"
                            style="padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; font-weight: 600; font-size: 13px;">
                            <option value="">🌏 Semua Area</option>
                            @foreach ($offices as $office)
                                <option value="{{ $office->id }}">{{ $office->name }}</option>
                            @endforeach
                        </select>
                        <button id="resetMap" class="reset-map-btn" style="padding: 10px 16px;">🗺️ Reset</button>
                    </div>
                </div>
                <div style="margin-top: 12px; font-size: 11px; color: #64748b;">
                    Menampilkan data: <span id="periodInfo">{{ $stats['period'] ?? 'Hari Ini' }}</span> | Total:
                    {{ $stats['total'] ?? 0 }} karyawan
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px;">
                <!-- Sidebar Kiri -->
                <div style="grid-column: span 4; display: flex; flex-direction: column; gap: 20px;">
                    <div style="background: white; padding: 20px; border-radius: 24px; border: 1px solid #e2e8f0;">
                        <div class="stat-row" style="background: #f8fafc; padding: 12px 16px;">
                            <span style="font-size: 11px; font-weight: 700;">Total Hadir</span>
                            <span style="font-size: 18px; font-weight: 800;">{{ number_format($stats['total']) }}</span>
                        </div>
                        <div class="stat-row" style="background: #ecfdf5; padding: 12px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #059669;">Tepat Waktu</span>
                            <span
                                style="font-size: 18px; font-weight: 800; color: #047857;">{{ number_format($stats['on_time']) }}</span>
                        </div>
                        <div class="stat-row" style="background: #fff1f2; padding: 12px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #e11d48;">Terlambat</span>
                            <span
                                style="font-size: 18px; font-weight: 800; color: #be123c;">{{ number_format($stats['late']) }}</span>
                        </div>
                        <div class="stat-row" style="background: #fffbeb; padding: 12px 16px;">
                            <span style="font-size: 11px; font-weight: 700; color: #d97706;">Belum Absen</span>
                            <span
                                style="font-size: 18px; font-weight: 800; color: #d97706;">{{ number_format($stats['not_yet']) }}</span>
                        </div>
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: #10b981;"></div><span>Tepat</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #ef4444;"></div><span>Terlambat</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #a855f7;"></div><span>WFA</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #f59e0b;"></div><span>Kantor</span>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Aktivitas -->
                    <div class="sidebar-card" style="height: 580px;">
                        <div
                            style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between;">
                            <span
                                style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Aktivitas</span>
                            <span
                                style="font-size: 8px; font-weight: 800; color: #f59e0b; background: #fffbeb; padding: 3px 8px; border-radius: 6px;">●
                                LIVE</span>
                        </div>

                        <div class="custom-scroll" style="flex: 1; overflow-y: auto; padding: 16px;" id="listArea"
                            wire:poll.45s>
                            <!-- KARYAWAN SUDAH ABSEN -->
                            <div style="margin-bottom: 24px;">
                                <p
                                    style="font-size: 10px; font-weight: 800; color: #10b981; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <span>✅ SUDAH ABSEN</span>
                                    <span style="flex:1; height: 1px; background: #e2e8f0;"></span>
                                    <span
                                        style="font-size: 9px; background: #d1fae5; padding: 2px 8px; border-radius: 20px;">{{ $stats['total'] ?? 0 }}
                                        orang</span>
                                </p>
                                @forelse ($groupedAttendances->take(10) as $officeName => $items)
                                    <div style="margin-bottom: 16px;">
                                        <p
                                            style="font-size: 9px; font-weight: 800; color: #cbd5e1; margin-bottom: 6px;">
                                            📍 {{ $officeName }}</p>
                                        @foreach ($items->take(8) as $at)
                                            <div class="emp-item"
                                                onclick="window.focusUser({{ $at->start_latitude }}, {{ $at->start_longitude }}, {{ $at->user_id }})"
                                                data-name="{{ strtolower($at->user->name) }}"
                                                style="padding: 10px; margin-bottom: 6px; cursor: pointer;">
                                                <div style="flex:1; min-width:0;">
                                                    <div
                                                        style="font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                                        {{ $at->user->name }}
                                                        @if ($at->is_wfa)
                                                            <span
                                                                style="font-size: 8px; background: #f3e8ff; color: #a855f7; padding: 2px 6px; border-radius: 10px;">WFA</span>
                                                        @endif
                                                    </div>
                                                    <div
                                                        style="font-size: 9px; font-weight: 600; color: #94a3b8; margin-top: 2px;">
                                                        {{ $at->display_location }}</div>
                                                    <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                                                        📥 Masuk: {{ $at->jam_menit }}
                                                        @if ($at->end_time_formatted)
                                                            | 📤 Pulang: {{ $at->end_time_formatted }}
                                                        @else
                                                            | ⏳ Belum absen pulang
                                                        @endif
                                                    </div>
                                                </div>
                                                <div style="text-align: right; margin-left: 8px;">
                                                    <span class="badge-status"
                                                        style="background: {{ $at->is_late ? '#ef4444' : '#10b981' }}; font-size: 8px;">{{ $at->is_late ? 'Terlambat' : 'Hadir' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                        @if (count($items) > 8)
                                            <div
                                                style="text-align: center; font-size: 8px; color: #94a3b8; margin-top: 4px;">
                                                +{{ count($items) - 8 }} lainnya</div>
                                        @endif
                                    </div>
                                @empty
                                    <div style="text-align: center; color: #94a3b8; font-size: 11px; padding: 20px;">
                                        Belum ada karyawan yang absen</div>
                                @endforelse
                            </div>

                            <!-- KARYAWAN BELUM ABSEN -->
                            <div style="margin-bottom: 16px;">
                                <p
                                    style="font-size: 10px; font-weight: 800; color: #f59e0b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <span>⏰ BELUM ABSEN</span>
                                    <span style="flex:1; height: 1px; background: #e2e8f0;"></span>
                                    <span
                                        style="font-size: 9px; background: #fef3c7; padding: 2px 8px; border-radius: 20px;">{{ $stats['not_yet'] ?? 0 }}
                                        orang</span>
                                </p>
                                @forelse ($groupedNotYet->take(10) as $officeName => $items)
                                    <div style="margin-bottom: 16px;">
                                        <p
                                            style="font-size: 9px; font-weight: 800; color: #cbd5e1; margin-bottom: 6px;">
                                            📍 {{ $officeName }}</p>
                                        @foreach ($items->take(8) as $emp)
                                            <div class="emp-item"
                                                style="padding: 10px; margin-bottom: 6px; opacity: 0.7; background: #fffbeb; border-color: #fef3c7; cursor: default;">
                                                <div style="flex:1; min-width:0;">
                                                    <div style="font-size: 12px; font-weight: 700;">
                                                        {{ $emp->name }}</div>
                                                    <div
                                                        style="font-size: 9px; font-weight: 600; color: #94a3b8; margin-top: 2px;">
                                                        {{ $emp->display_location }}</div>
                                                </div>
                                                <div style="text-align: right;"><span class="badge-status"
                                                        style="background: #f59e0b; font-size: 8px;">Belum</span></div>
                                            </div>
                                        @endforeach
                                        @if (count($items) > 8)
                                            <div
                                                style="text-align: center; font-size: 8px; color: #94a3b8; margin-top: 4px;">
                                                +{{ count($items) - 8 }} lainnya</div>
                                        @endif
                                    </div>
                                @empty
                                    <div style="text-align: center; color: #94a3b8; font-size: 11px; padding: 20px;">
                                        Semua karyawan sudah absen ✅</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Container -->
                <div style="grid-column: span 8; position: relative;" wire:ignore>
                    <div id="map" class="map-container"></div>
                    <div wire:loading wire:target="selectedOffice, filterType, startDate, endDate"
                        class="loading-spinner">
                        <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Memuat...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map, markerLayer, officeLayers, userMarkers = {};
        var isMapReady = false;
        var pendingData = null;
        var isUserInteracting = false;
        var userInteractTimer = null;
        var savedPositions = {
            'default': {
                center: [-0.5000, 115.0333],
                zoom: 5
            }
        };
        var currentArea = 'default';

        function setupMap() {
            if (isMapReady) return true;
            var mapContainer = document.getElementById('map');
            if (!mapContainer) return false;
            try {
                var saved = savedPositions[currentArea] || savedPositions['default'];
                map = L.map('map', {
                    zoomControl: false,
                    attributionControl: false
                }).setView(saved.center, saved.zoom);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);
                markerLayer = L.layerGroup().addTo(map);
                officeLayers = L.layerGroup().addTo(map);
                L.control.zoom({
                    position: 'bottomright'
                }).addTo(map);

                map.on('zoomstart', function() {
                    isUserInteracting = true;
                    if (userInteractTimer) clearTimeout(userInteractTimer);
                });
                map.on('zoomend', function() {
                    savedPositions[currentArea] = {
                        center: map.getCenter(),
                        zoom: map.getZoom()
                    };
                    userInteractTimer = setTimeout(function() {
                        isUserInteracting = false;
                    }, 300);
                });
                map.on('movestart', function() {
                    isUserInteracting = true;
                    if (userInteractTimer) clearTimeout(userInteractTimer);
                });
                map.on('moveend', function() {
                    savedPositions[currentArea] = {
                        center: map.getCenter(),
                        zoom: map.getZoom()
                    };
                    userInteractTimer = setTimeout(function() {
                        isUserInteracting = false;
                    }, 300);
                });

                isMapReady = true;
                return true;
            } catch (e) {
                return false;
            }
        }

        function updateMarkers(attendances, offices, selectedId, shouldZoom = false) {
            if (!setupMap()) {
                pendingData = {
                    attendances,
                    offices,
                    selectedId,
                    shouldZoom
                };
                setTimeout(function() {
                    if (pendingData) {
                        updateMarkers(pendingData.attendances, pendingData.offices, pendingData.selectedId,
                            pendingData.shouldZoom);
                        pendingData = null;
                    }
                }, 200);
                return;
            }
            if (!map) return;
            if (isUserInteracting) shouldZoom = false;

            var newArea = selectedId ? 'area_' + selectedId : 'default';
            if (currentArea !== newArea && !isUserInteracting) {
                savedPositions[currentArea] = {
                    center: map.getCenter(),
                    zoom: map.getZoom()
                };
                currentArea = newArea;
                if (savedPositions[currentArea]) {
                    map.setView(savedPositions[currentArea].center, savedPositions[currentArea].zoom);
                    shouldZoom = false;
                } else {
                    shouldZoom = true;
                }
            }

            markerLayer.clearLayers();
            officeLayers.clearLayers();
            userMarkers = {};
            var focusPoints = [];

            if (offices && Array.isArray(offices)) {
                offices.forEach(function(off) {
                    var lat = parseFloat(off.latitude),
                        lng = parseFloat(off.longitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        L.circle([lat, lng], {
                            color: '#f59e0b',
                            weight: 1,
                            fillColor: '#f59e0b',
                            fillOpacity: 0.08,
                            radius: parseFloat(off.radius) || 100
                        }).addTo(officeLayers);
                        if (!selectedId || selectedId == off.id) focusPoints.push([lat, lng]);
                    }
                });
            }

            if (attendances && Array.isArray(attendances)) {
                attendances.forEach(function(at) {
                    var lat = parseFloat(at.start_latitude),
                        lng = parseFloat(at.start_longitude);
                    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                        var color = at.is_wfa ? '#a855f7' : (at.is_late ? '#ef4444' : '#10b981');
                        var icon = L.divIcon({
                            className: 'custom-div-icon',
                            html: '<div class="marker-pin" style="background-color: ' + color +
                                ';"><div class="marker-pulse" style="background-color: ' + color +
                                ';"></div></div>',
                            iconSize: [18, 18],
                            iconAnchor: [9, 9],
                            popupAnchor: [0, -10]
                        });
                        var m = L.marker([lat, lng], {
                                icon: icon
                            })
                            .bindPopup('<div class="popup-header" style="background: ' + color +
                                '; padding: 8px; text-align: center;"><strong style="color:white;">' + (at.is_late ?
                                    'TERLAMBAT' : 'HADIR') +
                                '</strong></div><div class="popup-body" style="padding: 8px;"><strong>' +
                                escapeHtml(at.user.name) + '</strong><br>📍 ' + escapeHtml(at.display_location) +
                                '<br>🕒 ' + at.jam_menit + ' WIB</div>')
                            .addTo(markerLayer);
                        userMarkers[at.user_id] = m;
                        focusPoints.push([lat, lng]);
                    }
                });
            }

            if (shouldZoom && focusPoints.length > 0 && !isUserInteracting) {
                try {
                    map.fitBounds(L.latLngBounds(focusPoints), {
                        padding: [80, 80],
                        maxZoom: 15
                    });
                    savedPositions[currentArea] = {
                        center: map.getCenter(),
                        zoom: map.getZoom()
                    };
                } catch (e) {
                    map.setView([-0.5000, 115.0333], 5);
                }
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function waitForLivewire() {
            if (typeof Livewire !== 'undefined') {
                setupMap();
                Livewire.on('updateMarkers', function(data) {
                    var payload = Array.isArray(data) ? data[0] : data;
                    updateMarkers(payload.attendances, payload.offices, payload.selectedId, false);
                });
                setTimeout(function() {
                    try {
                        updateMarkers(@json($attendances->toArray()), @json($offices->toArray()),
                            @json($selectedOffice), true);
                    } catch (e) {
                        console.log('Initial markers error:', e);
                    }
                }, 500);

                var searchInput = document.getElementById('empSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function(e) {
                        var term = e.target.value.toLowerCase();
                        document.querySelectorAll('.emp-item').forEach(function(el) {
                            var name = el.getAttribute('data-name');
                            el.style.display = (name && name.includes(term)) ? 'flex' : 'none';
                        });
                    });
                }
                var resetBtn = document.getElementById('resetMap');
                if (resetBtn) {
                    resetBtn.addEventListener('click', function() {
                        if (map) {
                            map.setView([-0.5000, 115.0333], 5);
                            savedPositions[currentArea] = {
                                center: [-0.5000, 115.0333],
                                zoom: 5
                            };
                        }
                    });
                }
            } else {
                setTimeout(waitForLivewire, 100);
            }
        }

        window.focusUser = function(lat, lng, id) {
            if (!map) return;
            map.flyTo([lat, lng], 17, {
                duration: 1.2
            });
            setTimeout(function() {
                if (userMarkers[id]) userMarkers[id].openPopup();
            }, 800);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', waitForLivewire);
        } else {
            waitForLivewire();
        }
    </script>
</div>
