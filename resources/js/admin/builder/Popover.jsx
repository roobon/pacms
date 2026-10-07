import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const GAP = 4;
const MARGIN = 8;

/**
 * Floating panel attached to a button, rendered in a portal with fixed positioning so the
 * builder's scrolling columns never clip it. Closes on Escape and on clicks outside;
 * stays inside the viewport.
 *
 * @param {{anchor: {current: HTMLElement|null}, onClose: () => void, align?: 'start'|'end', className?: string, role?: string, label?: string, children: React.ReactNode}} props
 */
export default function Popover({ anchor, onClose, align = 'start', className = '', role = 'dialog', label, children }) {
    const panel = useRef(null);
    const [position, setPosition] = useState(null);

    useLayoutEffect(() => {
        function place() {
            const button = anchor.current?.getBoundingClientRect();
            const box = panel.current?.getBoundingClientRect();
            if (!button || !box) return;

            let left = align === 'end' ? button.right - box.width : button.left;
            left = Math.max(MARGIN, Math.min(left, window.innerWidth - box.width - MARGIN));

            // Open upwards when there is no room below.
            let top = button.bottom + GAP;
            if (top + box.height > window.innerHeight - MARGIN && button.top - box.height - GAP > MARGIN) {
                top = button.top - box.height - GAP;
            }
            setPosition({ top: Math.max(MARGIN, top), left });
        }

        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [anchor, align]);

    useEffect(() => {
        function onPointerDown(event) {
            if (panel.current?.contains(event.target) || anchor.current?.contains(event.target)) return;
            // Clicks inside a dialog opened from this popover (e.g. "Save as template") don't close it.
            if (event.target.closest?.('dialog')) return;
            onClose();
        }
        function onKeyDown(event) {
            if (event.key === 'Escape') {
                onClose();
                anchor.current?.focus();
            }
        }
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [anchor, onClose]);

    return createPortal(
        <div
            ref={panel}
            role={role}
            aria-label={label}
            className={`pa-popover ${className}`}
            style={{ top: position?.top ?? 0, left: position?.left ?? 0, visibility: position ? 'visible' : 'hidden' }}
        >
            {children}
        </div>,
        document.body,
    );
}
