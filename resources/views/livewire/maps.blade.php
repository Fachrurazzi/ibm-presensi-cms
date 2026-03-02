<div class="p-6 min-h-screen" style="background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif;">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        /* Map Container */
        .map-container {
            height: 750px;
            width: 100%;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            z-index: 1;
        }

        /* Sidebar Styling */
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
            padding: 16px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #f1f5f9;
            margin-bottom: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .emp-item:hover {
            border-color: #fbbf24;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 10px;
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

        /* Fix Marker Hilang & Pulse Effect */
        .custom-div-icon {
            background: transparent !important;
            border: none !important;
        }

        .marker-pin {
            width: 16px !important;
            height: 16px !important;
            border-radius: 50% !important;
            border: 3px solid white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3) !important;
            position: relative !important;
            display: block !important;
        }

        .marker-pulse {
            position: absolute !important;
            top: -3px !important;
            left: -3px !important;
            width: 16px !important;
            height: 16px !important;
            border-radius: 50% !important;
            animation: pulse 2s infinite !important;
            opacity: 0.6 !important;
            pointer-events: none !important;
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

        .leaflet-popup-content-wrapper {
            border-radius: 12px !important;
            padding: 5px;
        }
    </style>

    <div style="max-width: 1800px; margin: 0 auto;">
        <div
            style="background: white; padding: 24px; border-radius: 24px; border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a;">Monitoring Presensi</h1>
                <p
                    style="margin: 4px 0 0 0; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">
                    Intiboga Real-time Tracking</p>
            </div>

            <div style="display: flex; gap: 12px; flex-grow: 1; justify-content: flex-end; max-width: 600px;">
                <input type="text" id="empSearch" placeholder="Cari nama pegawai..."
                    style="flex-grow: 1; padding: 12px 20px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 14px; outline: none;">

                <select wire:model.live="selectedOffice"
                    style="padding: 12px 20px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; font-weight: 700; font-size: 14px; cursor: pointer;">
                    <option value="">🌏 Seluruh Area</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}">{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px;">
            <div style="grid-column: span 4; display: flex; flex-direction: column; gap: 24px;">

                <div style="background: white; padding: 24px; border-radius: 24px; border: 1px solid #e2e8f0;">
                    <div class="stat-row" style="background: #f8fafc;">
                        <span
                            style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total
                            Hadir</span>
                        <span style="font-size: 20px; font-weight: 800; color: #0f172a;">{{ $stats['total'] }}</span>
                    </div>
                    <div class="stat-row" style="background: #ecfdf5; border-color: #d1fae5;">
                        <span
                            style="font-size: 12px; font-weight: 700; color: #059669; text-transform: uppercase;">Tepat
                            Waktu</span>
                        <span style="font-size: 20px; font-weight: 800; color: #047857;">{{ $stats['on_time'] }}</span>
                    </div>
                    <div class="stat-row" style="background: #fff1f2; border-color: #ffe4e6;">
                        <span
                            style="font-size: 12px; font-weight: 700; color: #e11d48; text-transform: uppercase;">Terlambat</span>
                        <span style="font-size: 20px; font-weight: 800; color: #be123c;">{{ $stats['late'] }}</span>
                    </div>
                </div>

                <div class="sidebar-card" style="height: 480px;">
                    <div
                        style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <span
                            style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Daftar
                            Aktivitas</span>
                        <span
                            style="font-size: 9px; font-weight: 800; color: #f59e0b; background: #fffbeb; padding: 4px 8px; border-radius: 6px;">●
                            LIVE</span>
                    </div>

                    <div class="custom-scroll" style="flex: 1; overflow-y: auto; padding: 20px;" id="listArea"
                        wire:poll.30s>
                        @forelse ($groupedAttendances as $officeName => $items)
                            <div style="margin-bottom: 24px;">
                                <p
                                    style="font-size: 10px; font-weight: 800; color: #cbd5e1; text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    {{ $officeName }} <span
                                        style="flex-grow: 1; height: 1px; background: #f1f5f9;"></span>
                                </p>
                                @foreach ($items as $at)
                                    <div class="emp-item"
                                        onclick="window.focusUser({{ $at->start_latitude }}, {{ $at->start_longitude }}, {{ $at->user_id }})"
                                        data-name="{{ strtolower($at->user->name) }}">
                                        <div style="min-width: 0; flex: 1;">
                                            <div
                                                style="font-size: 14px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $at->user->name }}</div>
                                            <div
                                                style="font-size: 10px; font-weight: 600; color: #94a3b8; margin-top: 2px;">
                                                {{ $at->display_location }}</div>
                                        </div>
                                        <div style="text-align: right; margin-left: 12px;">
                                            <div
                                                style="font-size: 11px; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                                                {{ $at->jam_menit }}</div>
                                            <span class="badge-status"
                                                style="background-color: {{ $at->is_late ? '#ef4444' : '#10b981' }};">
                                                {{ $at->is_late ? 'Terlambat' : 'Hadir' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <div
                                style="text-align: center; color: #94a3b8; font-size: 12px; margin-top: 40px; font-style: italic;">
                                Belum ada data hadir hari ini</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div style="grid-column: span 8;" wire:ignore>
                <div id="map" class="map-container"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map, markerLayer, officeLayers, userMarkers = {};

        function setupMap() {
            if (map) return;
            map = L.map('map', {
                zoomControl: false,
                attributionControl: false
            }).setView([-0.5000, 115.0333], 6);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);
            markerLayer = L.layerGroup().addTo(map);
            officeLayers = L.layerGroup().addTo(map);
            L.control.zoom({
                position: 'bottomright'
            }).addTo(map);
        }

        function updateMarkers(attendances, offices, selectedId) {
            if (!map) setupMap();
            markerLayer.clearLayers();
            officeLayers.clearLayers();
            userMarkers = {}; // Reset

            let focusPoints = [];

            // Office Radius
            offices.forEach(off => {
                let lat = parseFloat(off.latitude),
                    lng = parseFloat(off.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    L.circle([lat, lng], {
                        color: '#f59e0b',
                        weight: 1,
                        fillColor: '#f59e0b',
                        fillOpacity: 0.05,
                        radius: parseFloat(off.radius) || 100
                    }).addTo(officeLayers);
                    if (selectedId == off.id) focusPoints.push([lat, lng]);
                }
            });

            // Employee Markers
            attendances.forEach(at => {
                let lat = parseFloat(at.start_latitude),
                    lng = parseFloat(at.start_longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    let color = at.is_wfa ? '#a855f7' : (at.is_late ? '#ef4444' : '#10b981');

                    const icon = L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div class="marker-pin" style="background-color: ${color};">
                                <div class="marker-pulse" style="background-color: ${color};"></div>
                               </div>`,
                        iconSize: [16, 16],
                        iconAnchor: [8, 8],
                        popupAnchor: [0, -10]
                    });

                    const m = L.marker([lat, lng], {
                            icon: icon
                        })
                        .bindPopup(`
                            <div style="text-align:center; padding: 5px; font-family: sans-serif;">
                                <div style="font-size: 10px; font-weight: 800; color: ${color}; text-transform: uppercase;">
                                    ${at.is_late ? 'Terlambat' : 'Hadir'}
                                </div>
                                <b style="font-size: 13px;">${at.user.name}</b><br>
                                <small style="color: #64748b;">${at.display_location} • ${at.jam_menit}</small>
                            </div>
                        `)
                        .addTo(markerLayer);

                    userMarkers[at.user_id] = m;
                    focusPoints.push([lat, lng]);
                }
            });

            if (focusPoints.length > 0) {
                map.fitBounds(L.latLngBounds(focusPoints), {
                    padding: [80, 80],
                    maxZoom: 15
                });
            }
        }

        document.addEventListener('livewire:initialized', () => {
            setupMap();
            Livewire.on('updateMarkers', (data) => {
                const payload = Array.isArray(data) ? data[0] : data;
                updateMarkers(payload.attendances, payload.offices, payload.selectedId);
            });
            updateMarkers(@json($attendances->toArray()), @json($offices->toArray()), @json($selectedOffice));

            document.getElementById('empSearch').addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.emp-item').forEach(el => {
                    el.style.display = el.getAttribute('data-name').includes(term) ? 'flex' :
                    'none';
                });
            });
        });

        window.focusUser = (lat, lng, id) => {
            if (!map) return;
            map.flyTo([lat, lng], 17);
            if (userMarkers[id]) setTimeout(() => userMarkers[id].openPopup(), 1200);
        };
    </script>
</div>
