import './bootstrap';
import './receipt-printer';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const hideCommercialPoiLayers = (map) => {
    const patterns = /(poi|shop|retail|restaurant|commercial|business)/i;
    for (const layer of map.getStyle()?.layers ?? []) {
        const sourceLayer = layer['source-layer'] ?? '';
        if (patterns.test(`${layer.id} ${sourceLayer}`)) {
            map.setLayoutProperty(layer.id, 'visibility', 'none');
        }
    }
};

window.coordinatePicker = (config) => ({
    map: null,
    marker: null,
    locating: false,
    message: '',
    init() {
        if (!config.styleUrl) {
            this.$refs.map.innerHTML = '<div class="p-6 text-sm">MAP_STYLE_URL belum dikonfigurasi. Koordinat tetap dapat diisi manual atau melalui GPS.</div>';
            return;
        }
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.styleUrl,
            center: [config.longitude, config.latitude],
            zoom: config.zoom,
            attributionControl: true,
        });
        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        this.marker = new maplibregl.Marker({ draggable: true })
            .setLngLat([config.longitude, config.latitude]).addTo(this.map);
        this.map.on('load', () => hideCommercialPoiLayers(this.map));
        this.map.on('click', event => this.setPosition(event.lngLat.lat, event.lngLat.lng));
        this.marker.on('dragend', () => {
            const point = this.marker.getLngLat();
            this.setPosition(point.lat, point.lng);
        });
    },
    setPosition(latitude, longitude, accuracy = null) {
        this.marker?.setLngLat([longitude, latitude]);
        this.map?.flyTo({ center: [longitude, latitude], zoom: Math.max(this.map.getZoom(), 16) });
        config.setLatitude(Number(latitude.toFixed(7)));
        config.setLongitude(Number(longitude.toFixed(7)));
        if (accuracy !== null) config.setAccuracy(Number(accuracy.toFixed(2)));
    },
    async applyAddress(latitude, longitude) {
        if (!config.reverseUrl) return;
        try {
            const url = new URL(config.reverseUrl);
            url.searchParams.set('latitude', latitude);
            url.searchParams.set('longitude', longitude);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Alamat tidak ditemukan');
            const result = await response.json();
            if (result.province_code) await config.setProvince(result.province_code);
            if (result.city_code) await config.setCity(result.city_code);
            if (result.district_code) await config.setDistrict(result.district_code);
            if (result.village_code) await config.setVillage(result.village_code);
            if (result.display_name) await config.setAddress(result.display_name);
            this.message = result.display_name
                ? `Alamat ditemukan: ${result.display_name}`
                : 'Koordinat ditemukan, tetapi alamat belum tersedia.';
        } catch (error) {
            this.message = `Koordinat berhasil diambil, tetapi alamat otomatis gagal: ${error.message}`;
        }
    },
    useMyLocation() {
        this.locating = true;
        this.message = '';
        navigator.geolocation.getCurrentPosition(
            async position => {
                this.setPosition(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
                await this.applyAddress(position.coords.latitude, position.coords.longitude);
                this.locating = false;
            },
            error => {
                this.locating = false;
                this.message = `Lokasi tidak dapat diambil: ${error.message}`;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
        );
    },
});

window.storeMap = (config) => ({
    map: null,
    data: null,
    count: 0,
    statistics: {},
    search: '',
    areaId: '',
    verificationStatus: '',
    activeStatus: '',
    needsReschedule: false,
    config,
    init() {
        if (!config.styleUrl) {
            this.$refs.map.innerHTML = '<div class="p-6">MAP_STYLE_URL belum dikonfigurasi.</div>';
            return;
        }
        this.map = new maplibregl.Map({
            container: this.$refs.map, style: config.styleUrl,
            center: [config.longitude, config.latitude], zoom: config.zoom, attributionControl: true,
        });
        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        this.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        this.map.on('load', async () => {
            hideCommercialPoiLayers(this.map);
            this.map.addSource('pancor-mas-stores', {
                type: 'geojson', data: { type: 'FeatureCollection', features: [] },
                cluster: false,
            });
            this.map.addLayer({ id: 'system-stores', type: 'circle', source: 'pancor-mas-stores',
                paint: { 'circle-color': ['case', ['==', ['get', 'needs_reschedule'], true], '#9333ea',
                    ['match', ['get', 'verification_status'], 'verified', '#15803d', 'pending', '#d97706', '#dc2626']],
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 7, 2, 10, 3, 12, 5, 15, 10],
                    'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 7, 0.75, 12, 1.25, 15, 2],
                    'circle-stroke-color': '#fff' } });
            this.map.addLayer({
                id: 'system-store-names',
                type: 'symbol',
                source: 'pancor-mas-stores',
                minzoom: 14,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 14, 10, 17, 14],
                    'text-offset': [1.1, 0],
                    'text-anchor': 'left',
                    'text-max-width': 14,
                    'text-optional': true,
                },
                paint: {
                    'text-color': '#1f2937',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                    'text-halo-blur': 0.5,
                },
            });
            this.map.on('click', 'system-stores', event => {
                const item = event.features[0];
                const p = item.properties;
                new maplibregl.Popup().setLngLat(item.geometry.coordinates)
                    .setHTML(`
                        <div class="store-popup">
                            <strong>${p.name}</strong>
                            <div class="store-popup-code">${p.code}</div>
                            <p>${p.address ?? ''}</p>
                            <dl>
                                <dt>Area</dt><dd>${p.areas || '-'}</dd>
                                <dt>Kunjungan terakhir</dt><dd>${p.last_visit || 'Belum pernah'}</dd>
                                <dt>Hasil terakhir</dt><dd>${p.last_visit_status_label || '-'}</dd>
                            </dl>
                            ${p.needs_reschedule ? '<div class="store-popup-warning">Perlu dijadwalkan ulang</div>' : ''}
                            <a class="store-popup-link store-popup-link-primary" href="${p.transaction_history_url}">Riwayat transaksi</a>
                            <a class="store-popup-link" href="${p.detail_url}">Buka detail toko →</a>
                        </div>`)
                    .addTo(this.map);
            });
            this.map.on('mouseenter', 'system-stores', () => { this.map.getCanvas().style.cursor = 'pointer'; });
            this.map.on('mouseleave', 'system-stores', () => { this.map.getCanvas().style.cursor = ''; });
            await this.loadStores();
        });
    },
    async loadStores() {
        if (!this.map?.getSource('pancor-mas-stores')) return;
        const url = new URL(config.dataUrl);
        if (this.search) url.searchParams.set('search', this.search);
        if (this.areaId) url.searchParams.set('area_id', this.areaId);
        if (this.verificationStatus) url.searchParams.set('verification_status', this.verificationStatus);
        if (this.activeStatus !== '') url.searchParams.set('is_active', this.activeStatus);
        if (this.needsReschedule) url.searchParams.set('needs_reschedule', '1');
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        this.data = await response.json();
        this.count = this.data.count;
        this.statistics = this.data.statistics ?? {};
        this.map.getSource('pancor-mas-stores').setData(this.data);
        if (this.data.features.length) this.fitBounds();
    },
    fitBounds() {
        if (!this.data?.features?.length) return;
        const bounds = new maplibregl.LngLatBounds();
        this.data.features.forEach(feature => bounds.extend(feature.geometry.coordinates));
        this.map.fitBounds(bounds, { padding: 50, maxZoom: 16 });
    },
    statisticCards() {
        return [
            { label: 'Toko tampil', value: this.count },
            { label: 'Total toko', value: this.statistics.total ?? 0 },
            { label: 'Toko aktif', value: this.statistics.active ?? 0 },
            { label: 'Menunggu verifikasi', value: this.statistics.pending_verification ?? 0 },
            { label: 'Tanpa koordinat', value: this.statistics.without_coordinates ?? 0 },
            { label: 'Perlu dijadwalkan ulang', value: this.statistics.needs_reschedule ?? 0 },
        ];
    },
});

window.routeStorePicker = (config) => ({
    map: null,
    data: { type: 'FeatureCollection', features: [] },
    selected: (config.initialSelected ?? []).map(Number),
    selectionInterval: null,
    init() {
        if (!config.styleUrl) {
            this.$refs.map.innerHTML = '<div class="p-6">MAP_STYLE_URL belum dikonfigurasi.</div>';
            return;
        }
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.styleUrl,
            center: [config.longitude, config.latitude],
            zoom: config.zoom,
            attributionControl: true,
        });
        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        this.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        this.map.on('load', async () => {
            hideCommercialPoiLayers(this.map);
            this.map.addSource('route-stores', {
                type: 'geojson',
                data: this.data,
                cluster: false,
            });
            if (config.showLine !== false) {
                this.map.addSource('route-line', {
                    type: 'geojson',
                    data: { type: 'Feature', geometry: { type: 'LineString', coordinates: [] }, properties: {} },
                });
                this.map.addLayer({
                    id: 'route-preview-line',
                    type: 'line',
                    source: 'route-line',
                    paint: { 'line-color': '#d97706', 'line-width': 5, 'line-opacity': 0.8 },
                });
            }
            this.map.addLayer({
                id: 'route-picker-store-names',
                type: 'symbol',
                source: 'route-stores',
                minzoom: 12.5,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 12.5, 10, 16, 14],
                    'text-offset': [1.1, 0],
                    'text-anchor': 'left',
                    'text-max-width': 14,
                    'text-optional': true,
                },
                paint: {
                    'text-color': '#14532d',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                    'text-halo-blur': 0.5,
                },
            });
            this.map.addLayer({
                id: 'route-picker-stores',
                type: 'circle',
                source: 'route-stores',
                paint: {
                    'circle-color': '#15803d',
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 7, 2, 10, 3, 12, 5, 15, 9],
                    'circle-opacity': 1,
                    'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 7, 0.75, 12, 1.25, 15, 2],
                    'circle-stroke-color': '#ffffff',
                },
            });
            this.map.addLayer({
                id: 'route-picker-selected-stores',
                type: 'circle',
                source: 'route-stores',
                filter: ['==', ['get', 'selected'], true],
                paint: {
                    'circle-color': '#d97706',
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 7, 3, 10, 4, 12, 7, 15, 13],
                    'circle-opacity': 1,
                    'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 7, 0.75, 12, 1.5, 15, 2.5],
                    'circle-stroke-color': '#ffffff',
                },
            });
            this.map.addLayer({
                id: 'route-picker-order',
                type: 'symbol',
                source: 'route-stores',
                filter: ['==', ['get', 'selected'], true],
                layout: {
                    'text-field': ['to-string', ['get', 'order']],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 7, 6, 10, 7, 12, 9, 15, 12],
                },
                paint: { 'text-color': '#ffffff' },
            });
            this.map.on('click', 'route-picker-stores', event => this.toggle(Number(event.features[0].properties.id)));
            this.map.on('mouseenter', 'route-picker-stores', () => { this.map.getCanvas().style.cursor = 'pointer'; });
            this.map.on('mouseleave', 'route-picker-stores', () => { this.map.getCanvas().style.cursor = ''; });
            await this.loadStores();
            if (config.getSelected) {
                this.selectionInterval = setInterval(() => this.refreshSelection(), 500);
            }
        });
    },
    destroy() {
        if (this.selectionInterval) clearInterval(this.selectionInterval);
        this.map?.remove();
    },
    async refreshSelection() {
        const nextSelected = (await config.getSelected() ?? []).map(Number);
        if (JSON.stringify(nextSelected) === JSON.stringify(this.selected)) return;
        this.selected = nextSelected;
        this.renderSelection();
    },
    async loadStores() {
        const response = await fetch(config.dataUrl, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        this.data = { type: 'FeatureCollection', features: payload.features };
        this.renderSelection();
        if (this.data.features.length) {
            const bounds = new maplibregl.LngLatBounds();
            this.data.features.forEach(feature => bounds.extend(feature.geometry.coordinates));
            this.map.fitBounds(bounds, { padding: 45, maxZoom: 14 });
        }
    },
    toggle(id) {
        const index = this.selected.indexOf(id);
        if (index >= 0) this.selected.splice(index, 1);
        else this.selected.push(id);
        this.selected = [...this.selected];
        config.setSelected(this.selected);
        this.renderSelection();
    },
    remove(id) {
        this.selected = this.selected.filter(item => item !== Number(id));
        config.setSelected(this.selected);
        this.renderSelection();
    },
    selectedStores() {
        return this.selected
            .map(id => this.data.features.find(feature => Number(feature.properties.id) === Number(id))?.properties)
            .filter(Boolean);
    },
    renderSelection() {
        this.data.features = this.data.features.map(feature => {
            const order = this.selected.indexOf(Number(feature.properties.id));
            return {
                ...feature,
                properties: { ...feature.properties, selected: order >= 0, order: order + 1 },
            };
        });
        this.map?.getSource('route-stores')?.setData(this.data);
        const coordinates = this.selected
            .map(id => this.data.features.find(feature => Number(feature.properties.id) === Number(id))?.geometry.coordinates)
            .filter(Boolean);
        if (config.showLine !== false) {
            this.map?.getSource('route-line')?.setData({
                type: 'Feature',
                geometry: { type: 'LineString', coordinates },
                properties: {},
            });
        }
    },
});

window.dailyRoutePreview = (config) => ({
    map: null,
    allFeatures: [],
    areaIds: [],
    extraIds: [],
    interval: null,
    init() {
        if (!config.styleUrl) {
            this.$refs.map.innerHTML = '<div class="p-6">MAP_STYLE_URL belum dikonfigurasi.</div>';
            return;
        }
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.styleUrl,
            center: [config.longitude, config.latitude],
            zoom: config.zoom,
            attributionControl: true,
        });
        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        this.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        this.map.on('load', async () => {
            hideCommercialPoiLayers(this.map);
            this.map.addSource('daily-route-preview-stores', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });
            this.map.addSource('daily-route-preview-line', {
                type: 'geojson',
                data: { type: 'Feature', geometry: { type: 'LineString', coordinates: [] }, properties: {} },
            });
            this.map.addLayer({
                id: 'daily-route-preview-line',
                type: 'line',
                source: 'daily-route-preview-line',
                paint: { 'line-color': '#d97706', 'line-width': 4, 'line-opacity': 0.75 },
            });
            this.map.addLayer({
                id: 'daily-route-preview-stores',
                type: 'circle',
                source: 'daily-route-preview-stores',
                paint: {
                    'circle-color': ['case', ['get', 'is_extra'], '#9333ea', '#15803d'],
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 7, 3, 10, 4, 12, 7, 15, 11],
                    'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 7, 0.75, 12, 1.5, 15, 2],
                    'circle-stroke-color': '#ffffff',
                },
            });
            this.map.addLayer({
                id: 'daily-route-preview-order',
                type: 'symbol',
                source: 'daily-route-preview-stores',
                layout: {
                    'text-field': ['to-string', ['get', 'order']],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 7, 6, 10, 7, 12, 9, 15, 11],
                },
                paint: { 'text-color': '#ffffff' },
            });
            const response = await fetch(config.dataUrl, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            this.allFeatures = payload.features ?? [];
            await this.refresh();
            this.interval = setInterval(() => this.refresh(), 800);
        });
    },
    async refresh() {
        const nextAreaIds = (await config.getAreaStores()).map(Number);
        const nextExtraIds = (await config.getExtraStores()).map(Number);
        if (JSON.stringify(nextAreaIds) === JSON.stringify(this.areaIds)
            && JSON.stringify(nextExtraIds) === JSON.stringify(this.extraIds)) return;
        this.areaIds = nextAreaIds;
        this.extraIds = nextExtraIds;
        this.render();
    },
    selectedStores() {
        return [...this.areaIds, ...this.extraIds]
            .map(id => {
                const properties = this.allFeatures.find(feature => Number(feature.properties.id) === id)?.properties;
                return properties ? { ...properties, isExtra: this.extraIds.includes(id) } : null;
            })
            .filter(Boolean);
    },
    render() {
        const ids = [...this.areaIds, ...this.extraIds];
        const features = ids
            .map((id, index) => {
                const feature = this.allFeatures.find(item => Number(item.properties.id) === id);
                if (!feature) return null;
                return {
                    ...feature,
                    properties: {
                        ...feature.properties,
                        order: index + 1,
                        is_extra: this.extraIds.includes(id),
                    },
                };
            })
            .filter(Boolean);
        this.map?.getSource('daily-route-preview-stores')?.setData({
            type: 'FeatureCollection',
            features,
        });
        this.map?.getSource('daily-route-preview-line')?.setData({
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: features.map(feature => feature.geometry.coordinates) },
            properties: {},
        });
        if (features.length) {
            const bounds = new maplibregl.LngLatBounds();
            features.forEach(feature => bounds.extend(feature.geometry.coordinates));
            this.map.fitBounds(bounds, { padding: 45, maxZoom: 14 });
        }
    },
});

window.salesGps = () => ({
    message: '',
    locate(wire) {
        this.message = 'Mengambil lokasi dengan akurasi tinggi...';
        navigator.geolocation.getCurrentPosition(
            async position => {
                this.message = `Akurasi GPS ±${Math.round(position.coords.accuracy)} meter`;
                await wire.loadNearby(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
            },
            error => { this.message = `Lokasi tidak dapat diambil: ${error.message}`; },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
        );
    },
    async visit(wire, storeId, status, needsReason) {
        const reason = needsReason ? window.prompt('Toko berada di luar rute. Tuliskan alasan kunjungan:') : null;
        if (needsReason && !reason) return;
        await wire.markVisit(storeId, status, reason);
    },
});

window.salesRouteWorkspace = (config) => ({
    map: null,
    userMarker: null,
    collection: { type: 'FeatureCollection', features: config.features ?? [] },
    message: '',
    locating: false,
    init() {
        if (!config.styleUrl) {
            this.$refs.map.innerHTML = '<div class="p-6 text-sm">Peta belum dikonfigurasi. Daftar toko tetap dapat digunakan.</div>';
            return;
        }
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.styleUrl,
            center: [config.longitude, config.latitude],
            zoom: config.zoom,
            attributionControl: true,
        });
        this.map.addControl(new maplibregl.NavigationControl(), 'top-right');
        this.map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        this.$el.addEventListener('store-visited', event => this.markVisited(Number(event.detail?.storeId)));
        this.map.on('load', () => {
            hideCommercialPoiLayers(this.map);
            this.map.addSource('sales-route-stores', { type: 'geojson', data: this.collection });
            this.map.addSource('sales-route-line', {
                type: 'geojson',
                data: (config.line ?? []).length >= 2
                    ? { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: config.line } }
                    : { type: 'FeatureCollection', features: [] },
            });
            this.map.addLayer({ id: 'sales-route-line', type: 'line', source: 'sales-route-line', paint: { 'line-color': '#d97706', 'line-width': 4, 'line-opacity': .72 } });
            this.map.addLayer({
                id: 'sales-route-stores', type: 'circle', source: 'sales-route-stores',
                paint: {
                    'circle-color': ['case', ['==', ['get', 'visited'], true], '#4b5563', '#d97706'],
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 9, 5, 13, 8, 16, 12],
                    'circle-opacity': ['case', ['==', ['get', 'visited'], true], .48, 1],
                    'circle-stroke-width': 2, 'circle-stroke-color': '#fff',
                },
            });
            this.map.addLayer({
                id: 'sales-route-order', type: 'symbol', source: 'sales-route-stores',
                layout: { 'text-field': ['to-string', ['get', 'order']], 'text-size': 11 },
                paint: { 'text-color': '#fff', 'text-opacity': ['case', ['==', ['get', 'visited'], true], .55, 1] },
            });
            this.map.addLayer({
                id: 'sales-route-names', type: 'symbol', source: 'sales-route-stores', minzoom: 13,
                layout: { 'text-field': ['get', 'name'], 'text-size': 11, 'text-offset': [1.2, 0], 'text-anchor': 'left', 'text-optional': true },
                paint: { 'text-color': '#1f2937', 'text-halo-color': '#fff', 'text-halo-width': 2 },
            });
            this.map.on('click', 'sales-route-stores', event => {
                const properties = event.features[0].properties;
                if (properties.visited === true || properties.visited === 'true') return;
                config.selectStore(Number(properties.id));
            });
            this.map.on('mouseenter', 'sales-route-stores', event => {
                const visited = event.features[0]?.properties?.visited;
                this.map.getCanvas().style.cursor = visited === true || visited === 'true' ? 'not-allowed' : 'pointer';
            });
            this.map.on('mouseleave', 'sales-route-stores', () => { this.map.getCanvas().style.cursor = ''; });
            if (this.collection.features.length) {
                const bounds = new maplibregl.LngLatBounds();
                this.collection.features.forEach(feature => bounds.extend(feature.geometry.coordinates));
                this.map.fitBounds(bounds, { padding: 48, maxZoom: 14 });
            }
        });
    },
    markVisited(storeId) {
        const feature = this.collection.features.find(item => Number(item.properties.id) === storeId);
        if (!feature) return;
        feature.properties.visited = true;
        this.map?.getSource('sales-route-stores')?.setData(this.collection);
    },
    locate(wire) {
        this.locating = true;
        this.message = 'Mengambil lokasi dengan akurasi tinggi...';
        navigator.geolocation.getCurrentPosition(async position => {
            const coordinates = [position.coords.longitude, position.coords.latitude];
            if (!this.userMarker) {
                this.userMarker = new maplibregl.Marker({ color: '#15803d' }).setLngLat(coordinates).addTo(this.map);
            } else this.userMarker.setLngLat(coordinates);
            this.map?.flyTo({ center: coordinates, zoom: 15 });
            this.message = `Lokasi ditemukan · akurasi ±${Math.round(position.coords.accuracy)} meter`;
            await wire.loadNearby(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
            this.locating = false;
        }, error => {
            this.locating = false;
            this.message = `Lokasi tidak dapat diambil: ${error.message}`;
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 15000 });
    },
    async visit(wire, storeId, status, needsReason = false) {
        const reason = needsReason ? window.prompt('Tuliskan alasan kunjungan di luar rute:') : null;
        if (needsReason && !reason) return;
        await wire.markVisit(storeId, status, reason);
    },
});
