import type { LatLngExpression } from 'leaflet';
import { useEffect, useMemo } from 'react';
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet';

import L, { DEFAULT_MAP_CENTER, DEFAULT_MAP_ZOOM } from '@/lib/leaflet';

import 'leaflet/dist/leaflet.css';

type Props = {
    latitude: string;
    longitude: string;
    onLatitudeChange: (value: string) => void;
    onLongitudeChange: (value: string) => void;
};

const formatCoordinate = (value: number) => value.toFixed(7);

const parseCoordinates = (latitude: string, longitude: string): [number, number] | null => {
    const parsedLatitude = Number(latitude);
    const parsedLongitude = Number(longitude);

    if (!latitude || !longitude || !Number.isFinite(parsedLatitude) || !Number.isFinite(parsedLongitude)) {
        return null;
    }

    return [parsedLatitude, parsedLongitude];
};

function MapViewport({ center }: { center: LatLngExpression }) {
    const map = useMap();

    useEffect(() => {
        map.setView(center);
    }, [center, map]);

    return null;
}

function MapClickHandler({ onSelect }: { onSelect: (latitude: number, longitude: number) => void }) {
    useMapEvents({
        click: (event) => onSelect(event.latlng.lat, event.latlng.lng),
    });

    return null;
}

export default function MapPicker({ latitude, longitude, onLatitudeChange, onLongitudeChange }: Props) {
    const coordinates = useMemo(() => parseCoordinates(latitude, longitude), [latitude, longitude]);
    const center = coordinates ?? DEFAULT_MAP_CENTER;

    const selectLocation = (selectedLatitude: number, selectedLongitude: number) => {
        onLatitudeChange(formatCoordinate(selectedLatitude));
        onLongitudeChange(formatCoordinate(selectedLongitude));
    };

    return (
        <div className="space-y-2">
            <div className="overflow-hidden rounded-xl border border-line bg-sand">
                <MapContainer center={center} zoom={DEFAULT_MAP_ZOOM} scrollWheelZoom className="h-64 w-full">
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <MapViewport center={center} />
                    <MapClickHandler onSelect={selectLocation} />
                    {coordinates && (
                        <Marker
                            position={coordinates}
                            draggable
                            eventHandlers={{
                                dragend: (event) => {
                                    const marker = event.target as L.Marker;
                                    const position = marker.getLatLng();
                                    selectLocation(position.lat, position.lng);
                                },
                            }}
                        />
                    )}
                </MapContainer>
            </div>
            <p className="text-xs text-muted">Click the map to place a pin, or drag the existing pin to refine the service location.</p>
        </div>
    );
}
