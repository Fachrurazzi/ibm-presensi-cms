<div>
    <div class="min-h-screen bg-slate-50 pb-12 font-jakarta">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet">

        @if (!$schedule)
            <div class="container mx-auto max-w-md px-4 py-20 text-center">
                <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100">
                    <div class="bg-amber-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">🗓️</span>
                    </div>
                    <h2 class="text-xl font-extrabold text-slate-800 mb-2">Jadwal Belum Diatur</h2>
                    <p class="text-slate-500 text-sm leading-relaxed">Sistem tidak menemukan jadwal kerja Anda hari ini.
                        Silakan hubungi admin HRD.</p>
                </div>
            </div>
        @else
            <div class="container mx-auto max-w-md px-4 py-6">
                <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-white">

                    {{-- User Header --}}
                    <div class="p-8 bg-gradient-to-br from-orange-500 to-rose-500 text-white relative overflow-hidden">
                        <div class="relative z-10 flex items-center gap-5">
                            <div
                                class="h-16 w-16 rounded-2xl border-2 border-white/40 overflow-hidden bg-white/20 backdrop-blur-md shadow-inner">
                                <img src="{{ Auth::user()->image ? asset('storage/' . Auth::user()->image) : 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->name) . '&background=fff&color=f97316' }}"
                                    class="h-full w-full object-cover">
                            </div>
                            <div>
                                <p class="text-orange-100 text-[10px] font-bold uppercase tracking-[0.2em] mb-1">Selamat
                                    Datang,</p>
                                <h2 class="text-xl font-black leading-tight tracking-tight">{{ Auth::user()->name }}
                                </h2>
                                <div
                                    class="mt-1 inline-flex items-center px-2.5 py-0.5 bg-black/10 backdrop-blur-sm rounded-lg border border-white/10 text-[9px] font-black uppercase tracking-wider">
                                    {{ Auth::user()->position?->name ?? 'Staff' }}
                                </div>
                            </div>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
                    </div>

                    <div class="p-6 space-y-6">
                        {{-- Stats Card --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-slate-50 p-4 rounded-3xl border border-slate-100 shadow-sm transition-all">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-emerald-500 text-xs">●</span>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Absen
                                        Masuk</p>
                                </div>
                                <p class="text-2xl font-black text-slate-800 tracking-tighter">
                                    {{ $attendance?->start_time ? $attendance->start_time->format('H:i') : '--:--' }}
                                </p>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-3xl border border-slate-100 shadow-sm">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-rose-500 text-xs">●</span>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Absen
                                        Keluar</p>
                                </div>
                                <p class="text-2xl font-black text-slate-800 tracking-tighter">
                                    {{ $attendance?->end_time ? $attendance->end_time->format('H:i') : '--:--' }}
                                </p>
                            </div>
                        </div>

                        {{-- Map Area --}}
                        <div class="space-y-4">
                            <div class="flex items-center justify-between px-1">
                                <h3 class="font-black text-sm text-slate-800 uppercase tracking-tight">Lokasi Presensi
                                </h3>
                                @if ($schedule->is_wfa)
                                    <span
                                        class="text-[9px] font-black text-indigo-600 px-3 py-1 bg-indigo-50 rounded-full border border-indigo-100 shadow-sm animate-pulse">✨
                                        MODE WFA</span>
                                @else
                                    <span
                                        class="text-[9px] font-black text-orange-600 px-3 py-1 bg-orange-50 rounded-full border border-orange-100 shadow-sm">🏢
                                        {{ $schedule->office->name }}</span>
                                @endif
                            </div>

                            <div class="relative group">
                                <div id="map"
                                    class="h-64 w-full rounded-[2rem] border-4 border-white shadow-xl z-0 ring-1 ring-slate-100"
                                    wire:ignore></div>
                                <div
                                    class="absolute bottom-4 right-4 z-[1000] bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-lg border border-slate-100">
                                    <span
                                        class="text-[9px] font-black {{ $accuracy <= 50 ? 'text-emerald-600' : 'text-amber-600' }} uppercase italic">
                                        Accuracy: {{ $accuracy ? '±' . round($accuracy) . 'm' : 'Signal Searching...' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Status Banner --}}
                        @if ($isLeave)
                            <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-3xl flex items-center gap-4">
                                <div
                                    class="bg-indigo-500 w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-lg shadow-indigo-200">
                                    🏖️</div>
                                <div>
                                    <h4 class="text-xs font-black text-indigo-900 uppercase tracking-tight">Status:
                                        Sedang Cuti</h4>
                                    <p class="text-[9px] text-indigo-600 font-bold leading-tight">Absensi dinonaktifkan
                                        sementara.</p>
                                </div>
                            </div>
                        @elseif ($schedule->is_banned)
                            <div class="bg-rose-50 border border-rose-100 p-4 rounded-3xl flex items-center gap-4">
                                <div
                                    class="bg-rose-500 w-10 h-10 rounded-2xl flex items-center justify-center text-lg shadow-lg shadow-rose-200">
                                    🚫</div>
                                <div>
                                    <h4 class="text-xs font-black text-rose-900 uppercase tracking-tight">Akun
                                        Ditangguhkan</h4>
                                    <p class="text-[9px] text-rose-600 font-bold leading-tight">Hubungi Admin untuk
                                        akses kembali.</p>
                                </div>
                            </div>
                        @endif

                        {{-- Action Buttons --}}
                        <div class="space-y-3 pt-2">
                            <button type="button" onclick="tagLocation()" id="tagBtn"
                                @if ($isLeave || $schedule->is_banned) disabled @endif
                                class="w-full py-4 bg-slate-900 hover:bg-black text-white rounded-3xl font-bold flex items-center justify-center gap-3 shadow-lg active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="text-xl text-amber-400">📍</span>
                                <span class="tracking-wide">Tag Lokasi Saya</span>
                            </button>

                            <button wire:click="store" wire:loading.attr="disabled"
                                @if (!$insideRadius || !$latitude || $isLeave || $schedule->is_banned) disabled @endif
                                class="w-full py-5 rounded-3xl font-black text-lg shadow-2xl uppercase tracking-wider transition-all flex items-center justify-center
                                {{ !$insideRadius || !$latitude || $isLeave || $schedule->is_banned ? 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none' : 'bg-gradient-to-r from-orange-500 to-rose-500 text-white hover:brightness-110' }}">

                                <span wire:loading.remove>
                                    @if ($isLeave)
                                        🏖️ Sedang Libur Cuti
                                    @elseif($schedule->is_banned)
                                        🚫 Akun Diblokir
                                    @elseif (!$latitude)
                                        📍 Pindai Lokasi Dulu
                                    @elseif(!$insideRadius)
                                        🚩 Luar Radius Kantor
                                    @else
                                        🚀 Kirim Presensi {{ $attendance ? 'Keluar' : 'Masuk' }}
                                    @endif
                                </span>

                                <span wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Memproses...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Assets & Scripts --}}
            <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

            <script>
                let map, marker, circle;
                let watchId = null;

                @if ($schedule && $schedule->office)
                    const officePos = [{{ $schedule->office->latitude }}, {{ $schedule->office->longitude }}];
                    const radiusSize = {{ $schedule->office->radius }};
                    const isWfa = {{ $schedule->is_wfa ? 'true' : 'false' }};
                @else
                    const officePos = [-0.5000, 115.0333];
                    const radiusSize = 100;
                    const isWfa = false;
                @endif

                function initMap() {
                    const mapContainer = document.getElementById('map');
                    if (!mapContainer) return;

                    try {
                        if (typeof L === 'undefined') {
                            setTimeout(initMap, 500);
                            return;
                        }
                        if (map) return;

                        map = L.map('map', {
                            zoomControl: false,
                            attributionControl: false
                        }).setView(officePos, 16);
                        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);

                        @if ($schedule && $schedule->office)
                            circle = L.circle(officePos, {
                                color: isWfa ? '#6366f1' : '#f97316',
                                fillColor: isWfa ? '#6366f1' : '#f97316',
                                fillOpacity: 0.1,
                                radius: radiusSize,
                                weight: 2
                            }).addTo(map);
                        @endif

                        L.control.zoom({
                            position: 'bottomright'
                        }).addTo(map);
                    } catch (error) {
                        console.error('Map error:', error);
                        if (mapContainer) mapContainer.innerHTML =
                            '<div class="flex items-center justify-center h-full"><p class="text-red-500">Gagal memuat peta</p></div>';
                    }
                }

                function checkLocationPermission() {
                    if (navigator.permissions) {
                        navigator.permissions.query({
                            name: 'geolocation'
                        }).then(function(result) {
                            if (result.state === 'denied') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Izin Lokasi Diperlukan',
                                    text: 'Aktifkan izin lokasi untuk presensi.',
                                    confirmButtonColor: '#f97316'
                                });
                            }
                        }).catch(function(err) {
                            console.log('Permission API not supported');
                        });
                    }
                }

                document.addEventListener('livewire:initialized', function() {
                    initMap();
                    checkLocationPermission();

                    Livewire.on('alert', (event) => {
                        const data = event[0];
                        Swal.fire({
                            icon: data.type,
                            title: data.type === 'error' ? 'Oops...' : 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#f97316',
                            timer: data.type === 'success' ? 2000 : undefined,
                            showConfirmButton: data.type !== 'success',
                            customClass: {
                                popup: 'rounded-[2rem]'
                            }
                        });
                    });

                    Livewire.on('presensi-success', () => {
                        if (circle) {
                            circle.setStyle({
                                color: isWfa ? '#6366f1' : '#f97316',
                                fillColor: isWfa ? '#6366f1' : '#f97316'
                            });
                        }
                        if (marker && map) {
                            map.removeLayer(marker);
                            marker = null;
                        }
                    });
                });

                function tagLocation() {
                    const btn = document.getElementById('tagBtn');
                    if (!btn) return;

                    btn.disabled = true;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML =
                        '<svg class="animate-spin h-5 w-5 text-amber-400 inline mx-auto" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

                    if (!navigator.geolocation) {
                        Swal.fire('Error', 'Browser tidak mendukung GPS', 'error');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        return;
                    }

                    if (watchId) navigator.geolocation.clearWatch(watchId);

                    watchId = navigator.geolocation.watchPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const acc = position.coords.accuracy;

                        if (marker && map) map.removeLayer(marker);

                        const customIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: '<div style="background:#f97316; width:12px; height:12px; border-radius:50%; border:2px solid white; box-shadow:0 0 10px rgba(0,0,0,0.3);"></div>',
                            iconSize: [12, 12],
                            iconAnchor: [6, 6]
                        });

                        marker = L.marker([lat, lng], {
                            icon: customIcon
                        }).addTo(map);
                        map.flyTo([lat, lng], 17);

                        @this.set('latitude', lat);
                        @this.set('longitude', lng);
                        @this.set('accuracy', acc);

                        if (map && typeof map.distance === 'function') {
                            const distance = map.distance([lat, lng], officePos);
                            const isInside = isWfa || (distance <= radiusSize);
                            @this.set('insideRadius', isInside);

                            if (isInside) {
                                if (circle) circle.setStyle({
                                    color: '#10b981',
                                    fillColor: '#10b981'
                                });
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Lokasi Valid',
                                    text: `GPS Terverifikasi (${Math.round(distance)}m)`,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else {
                                if (circle) circle.setStyle({
                                    color: '#ef4444',
                                    fillColor: '#ef4444'
                                });
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Di Luar Radius!',
                                    text: `Jarak ${Math.round(distance)}m (Maks: ${radiusSize}m)`
                                });
                            }
                        }

                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        if (watchId) {
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;
                        }
                    }, function(error) {
                        let errorMessage = error.code === 1 ? 'Izin lokasi ditolak.' : (error.code === 2 ?
                            'Posisi tidak tersedia.' : 'Timeout mengambil lokasi.');
                        Swal.fire('GPS Error', errorMessage, 'error');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        if (watchId) {
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;
                        }
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    });
                }
            </script>
        @endif
    </div>
</div>
