import { Eraser } from 'lucide-react';
import { useEffect, useRef } from 'react';

type Props = {
    onChange: (file: File | null) => void;
};

export default function SignaturePad({ onChange }: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = canvas.clientWidth * ratio;
        canvas.height = canvas.clientHeight * ratio;
        const context = canvas.getContext('2d');
        if (!context) return;
        context.scale(ratio, ratio);
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#163b3a';
    }, []);

    const point = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const canvas = canvasRef.current;
        if (!canvas) return null;
        const bounds = canvas.getBoundingClientRect();

        return { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
    };

    const start = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const context = canvasRef.current?.getContext('2d');
        const position = point(event);
        if (!context || !position) return;
        drawing.current = true;
        event.currentTarget.setPointerCapture(event.pointerId);
        context.beginPath();
        context.moveTo(position.x, position.y);
    };

    const draw = (event: React.PointerEvent<HTMLCanvasElement>) => {
        if (!drawing.current) return;
        const context = canvasRef.current?.getContext('2d');
        const position = point(event);
        if (!context || !position) return;
        context.lineTo(position.x, position.y);
        context.stroke();
    };

    const finish = () => {
        if (!drawing.current) return;
        drawing.current = false;
        canvasRef.current?.toBlob((blob) => {
            if (blob) onChange(new File([blob], 'signature.png', { type: 'image/png' }));
        }, 'image/png');
    };

    const clear = () => {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');
        if (!canvas || !context) return;
        context.clearRect(0, 0, canvas.width, canvas.height);
        onChange(null);
    };

    return (
        <div className="space-y-2">
            <canvas ref={canvasRef} className="h-36 w-full touch-none rounded-lg border border-line bg-white" onPointerDown={start} onPointerMove={draw} onPointerUp={finish} onPointerCancel={finish} aria-label="Signature pad" />
            <button type="button" className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-brand" onClick={clear}><Eraser size={14} /> Clear signature</button>
        </div>
    );
}
