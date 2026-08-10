import type { LatLngExpression } from 'leaflet';
import { MapContainer, Marker, TileLayer } from 'react-leaflet';

import { DEFAULT_MAP_ZOOM } from '@/lib/leaflet';

import 'leaflet/dist/leaflet.css';

type Props = {
    latitude: number;
    longitude: number;
};

export default function MapView({ latitude, longitude }: Props) {
    const position: LatLngExpression = [latitude, longitude];

    return (
        <div className="overflow-hidden rounded-xl border border-line bg-sand">
            <MapContainer center={position} zoom={DEFAULT_MAP_ZOOM} scrollWheelZoom className="h-64 w-full">
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <Marker position={position} />
            </MapContainer>
        </div>
    );
}
