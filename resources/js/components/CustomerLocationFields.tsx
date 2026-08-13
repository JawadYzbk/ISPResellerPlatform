import { LocateFixed, MapPinned } from 'lucide-react';
import { useState } from 'react';

import MapPicker from '@/components/MapPicker';

type Props = {
    latitude: string;
    longitude: string;
    onLatitudeChange: (value: string) => void;
    onLongitudeChange: (value: string) => void;
    title?: string;
    description?: string;
};

export default function CustomerLocationFields({
    latitude,
    longitude,
    onLatitudeChange,
    onLongitudeChange,
    title = 'Service location',
    description = 'Optional GPS coordinates for field work and dispatch.',
}: Props) {
    const [locating, setLocating] = useState(false);
    const [locationError, setLocationError] = useState<string | null>(null);
    const mapUrl =
        latitude && longitude
            ? 'https://www.openstreetmap.org/?mlat=' +
              encodeURIComponent(latitude) +
              '&mlon=' +
              encodeURIComponent(longitude)
            : null;

    const useCurrentLocation = () => {
        if (!navigator.geolocation) {
            setLocationError('This browser does not provide location access.');
            return;
        }

        setLocating(true);
        setLocationError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                onLatitudeChange(position.coords.latitude.toFixed(7));
                onLongitudeChange(position.coords.longitude.toFixed(7));
                setLocating(false);
            },
            () => {
                setLocationError(
                    'Location access was unavailable. Enter coordinates manually or allow browser access.',
                );
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    };

    return (
        <fieldset className="space-y-4 border-t border-line pt-5">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div className="flex items-center gap-2">
                    <MapPinned size={17} className="text-brand" />
                    <div>
                        <legend className="text-sm font-semibold">{title}</legend>
                        <p className="mt-1 text-xs text-muted">{description}</p>
                    </div>
                </div>
                <button type="button" className="button-secondary" onClick={useCurrentLocation} disabled={locating}>
                    <LocateFixed size={15} /> {locating ? 'Locating…' : 'Use current location'}
                </button>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <label>
                    <span className="field-label">Latitude</span>
                    <input
                        type="number"
                        step="0.0000001"
                        min="-90"
                        max="90"
                        className="field"
                        value={latitude}
                        onChange={(event) => onLatitudeChange(event.target.value)}
                        placeholder="33.8938"
                    />
                </label>
                <label>
                    <span className="field-label">Longitude</span>
                    <input
                        type="number"
                        step="0.0000001"
                        min="-180"
                        max="180"
                        className="field"
                        value={longitude}
                        onChange={(event) => onLongitudeChange(event.target.value)}
                        placeholder="35.5018"
                    />
                </label>
            </div>
            <MapPicker
                latitude={latitude}
                longitude={longitude}
                onLatitudeChange={onLatitudeChange}
                onLongitudeChange={onLongitudeChange}
            />
            {locationError && <p className="field-error">{locationError}</p>}
            {mapUrl && (
                <a
                    href={mapUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex text-sm font-semibold text-brand hover:underline"
                >
                    Open coordinates in OpenStreetMap
                </a>
            )}
        </fieldset>
    );
}
