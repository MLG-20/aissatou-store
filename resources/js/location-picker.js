/**
 * « Utiliser ma position actuelle » : point GPS du téléphone, confirmé sur
 * une petite carte où le repère se déplace au doigt (en intérieur le GPS
 * se trompe parfois de 50 à 100 m). Leaflet et les tuiles OpenStreetMap ne
 * sont chargés qu'au premier partage, jamais sur les autres pages.
 */

// Au-delà, on invite la cliente à vérifier le repère.
const IMPRECISE_METERS = 100;

const round = (value) => Math.round(value * 1e7) / 1e7;

export default ({ lat = null, lng = null, accuracy = null } = {}) => {
    // Hors de l'état Alpine : un proxy réactif casse les objets Leaflet.
    let map = null;
    let marker = null;

    return {
        lat,
        lng,
        accuracy,
        locating: false,
        error: '',

        init() {
            if (this.lat !== null) {
                this.$nextTick(() => this.showMap());
            }
        },

        get imprecise() {
            return this.accuracy !== null && this.accuracy > IMPRECISE_METERS;
        },

        locate() {
            if (!('geolocation' in navigator)) {
                this.error = 'Votre navigateur ne permet pas de partager la position.';
                return;
            }

            this.locating = true;
            this.error = '';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.locating = false;
                    this.lat = round(position.coords.latitude);
                    this.lng = round(position.coords.longitude);
                    this.accuracy = Math.round(position.coords.accuracy);
                    this.$nextTick(() => this.showMap());
                },
                (failure) => {
                    this.locating = false;
                    this.error = failure.code === failure.PERMISSION_DENIED
                        ? "Vous avez refusé le partage de position. Vous pouvez l'autoriser dans les réglages du navigateur."
                        : 'Position introuvable pour le moment. Réessayez près d\'une fenêtre ou à l\'extérieur.';
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
            );
        },

        async showMap() {
            const [{ default: L }] = await Promise.all([
                import('leaflet'),
                import('leaflet/dist/leaflet.css'),
            ]);

            if (map) {
                map.setView([this.lat, this.lng], 17);
                marker.setLatLng([this.lat, this.lng]);
                map.invalidateSize();
                return;
            }

            map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView([this.lat, this.lng], 17);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            marker = L.marker([this.lat, this.lng], {
                draggable: true,
                autoPan: true,
                icon: L.divIcon({
                    className: '',
                    html: '<span style="display:block;width:22px;height:22px;border-radius:9999px;background:#4A1833;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></span>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                }),
            }).addTo(map);

            // Repère posé à la main : la précision du GPS ne veut plus rien dire.
            marker.on('dragend', () => {
                const point = marker.getLatLng();
                this.lat = round(point.lat);
                this.lng = round(point.lng);
                this.accuracy = null;
            });
        },

        clear() {
            this.lat = null;
            this.lng = null;
            this.accuracy = null;
            this.error = '';
            map?.remove();
            map = null;
            marker = null;
        },
    };
};
