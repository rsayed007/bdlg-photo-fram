import Konva from 'konva';
import QRCode from 'qrcode';
import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { Group, Image as KImage, Layer, Rect, Stage, Text } from 'react-konva';
import { clampOffset, coverSize, fitText, fontStack, fontWeight, resolveText } from './layout';
import type { EditorFrame, FrameStats, PlacedPhoto } from './types';

export interface FrameStageHandle {
    /** PNG at the frame's exact native size. */
    exportBlob(): Promise<Blob>;
    exportDataUrl(): string;
}

interface Props {
    frame: EditorFrame;
    background: HTMLImageElement | null;
    overlay: HTMLImageElement | null;
    stats: FrameStats;
    photos: (PlacedPhoto | null)[];
    /** Display scale (container width / native width). Export is always native. */
    scale: number;
    interactive?: boolean;
    selected?: number | null;
    onSelect?: (slot: number | null) => void;
    onPhotoChange?: (slot: number, photo: PlacedPhoto) => void;
    /** Draw empty-slot placeholders (editor only, never exported). */
    showPlaceholders?: boolean;
}

/**
 * Render order (spec §7): background → photos (clipped, rotated) → overlay → text → QR.
 */
const FrameStage = forwardRef<FrameStageHandle, Props>(function FrameStage(
    { frame, background, overlay, stats, photos, scale, interactive = false, selected = null, onSelect, onPhotoChange, showPlaceholders = false },
    ref,
) {
    const stageRef = useRef<Konva.Stage>(null);
    const [qr, setQr] = useState<HTMLImageElement | null>(null);
    const { config, size_w: W, size_h: H } = frame;
    const pinch = useRef<{ slot: number; dist: number } | null>(null);

    // QR code to the member's Maps profile.
    useEffect(() => {
        if (!config.qr?.enabled || !stats.profile_url) {
            setQr(null);
            return;
        }
        QRCode.toDataURL(stats.profile_url, { margin: 1, width: config.qr.size ?? 140, errorCorrectionLevel: 'M' }).then((url) => {
            const img = new window.Image();
            img.onload = () => setQr(img);
            img.src = url;
        });
    }, [config.qr?.enabled, config.qr?.size, stats.profile_url]);

    const texts = useMemo(
        () =>
            config.text_slots
                .map((slot, i) => {
                    const text = resolveText(slot, stats);
                    if (text === null) return null; // optional/unknown → hidden entirely
                    return { i, slot, fitted: fitText(text, slot, config, W) };
                })
                .filter((t) => t !== null),
        [config, stats, W],
    );

    useImperativeHandle(ref, () => ({
        exportDataUrl() {
            return renderNative().toDataURL('image/png');
        },
        exportBlob() {
            return new Promise((resolve, reject) => renderNative().toBlob((b) => (b ? resolve(b) : reject(new Error('Export failed'))), 'image/png'));
        },
    }));

    /** Draw at pixelRatio 1/scale, then copy onto an exact W×H canvas (no off-by-one from rounding). */
    function renderNative(): HTMLCanvasElement {
        const stage = stageRef.current!;
        const hidden = stage.find('.editor-only');
        hidden.forEach((n) => n.visible(false));
        const src = stage.toCanvas({ pixelRatio: 1 / scale });
        hidden.forEach((n) => n.visible(true));

        const out = document.createElement('canvas');
        out.width = W;
        out.height = H;
        out.getContext('2d')!.drawImage(src, 0, 0, W, H);
        return out;
    }

    const updatePhoto = (slotIndex: number, patch: Partial<PlacedPhoto>) => {
        const current = photos[slotIndex];
        if (!current || !onPhotoChange) return;
        const next = { ...current, ...patch };
        onPhotoChange(slotIndex, { ...next, ...clampOffset(config.photo_slots[slotIndex], next) });
    };

    return (
        <Stage
            ref={stageRef}
            width={W * scale}
            height={H * scale}
            scaleX={scale}
            scaleY={scale}
            onMouseDown={(e) => {
                if (e.target === e.target.getStage() || e.target.name() === 'background') onSelect?.(null);
            }}
            onTouchStart={(e) => {
                if (e.target.name() === 'background') onSelect?.(null);
            }}
            style={{ touchAction: interactive ? 'none' : 'auto' }}
        >
            <Layer listening={interactive}>
                <Rect width={W} height={H} fill="#ffffff" name="background" />
                {background && <KImage image={background} width={W} height={H} name="background" />}

                {config.photo_slots.map((slot, i) => {
                    const photo = photos[i];
                    const r = Math.min(slot.radius ?? 0, slot.w / 2, slot.h / 2);
                    return (
                        <Group
                            key={i}
                            x={slot.x + slot.w / 2}
                            y={slot.y + slot.h / 2}
                            rotation={slot.rotate ?? 0}
                            clipFunc={(c) => {
                                c.beginPath();
                                c.roundRect(-slot.w / 2, -slot.h / 2, slot.w, slot.h, r);
                                c.closePath();
                            }}
                        >
                            {photo
                                ? (() => {
                                      const size = coverSize(
                                          slot,
                                          { width: photo.image.naturalWidth, height: photo.image.naturalHeight },
                                          photo.zoom,
                                      );
                                      return (
                                          <KImage
                                              image={photo.image}
                                              width={size.w}
                                              height={size.h}
                                              offsetX={size.w / 2}
                                              offsetY={size.h / 2}
                                              x={photo.dx}
                                              y={photo.dy}
                                              draggable={interactive}
                                              onMouseDown={() => onSelect?.(i)}
                                              onTouchStart={(e) => {
                                                  onSelect?.(i);
                                                  const t = e.evt.touches;
                                                  if (t.length === 2) {
                                                      e.target.stopDrag();
                                                      pinch.current = {
                                                          slot: i,
                                                          dist: Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY),
                                                      };
                                                  }
                                              }}
                                              onTouchMove={(e) => {
                                                  const t = e.evt.touches;
                                                  if (t.length !== 2 || !pinch.current || pinch.current.slot !== i) return;
                                                  e.evt.preventDefault();
                                                  e.target.stopDrag();
                                                  const dist = Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
                                                  const zoom = Math.min(4, Math.max(1, photo.zoom * (dist / pinch.current.dist)));
                                                  pinch.current.dist = dist;
                                                  updatePhoto(i, { zoom });
                                              }}
                                              onTouchEnd={() => (pinch.current = null)}
                                              onDragMove={(e) => {
                                                  const clamped = clampOffset(slot, { ...photo, dx: e.target.x(), dy: e.target.y() });
                                                  e.target.position({ x: clamped.dx, y: clamped.dy });
                                              }}
                                              onDragEnd={(e) => updatePhoto(i, { dx: e.target.x(), dy: e.target.y() })}
                                          />
                                      );
                                  })()
                                : showPlaceholders && (
                                      <Group name="editor-only" onMouseDown={() => onSelect?.(i)} onTouchStart={() => onSelect?.(i)}>
                                          <Rect x={-slot.w / 2} y={-slot.h / 2} width={slot.w} height={slot.h} fill="rgba(15,118,110,0.12)" />
                                          <Text
                                              x={-slot.w / 2}
                                              y={-20}
                                              width={slot.w}
                                              align="center"
                                              text={`＋ ${i + 1}`}
                                              fontSize={40}
                                              fontStyle="bold"
                                              fill="rgba(15,118,110,0.7)"
                                          />
                                      </Group>
                                  )}
                        </Group>
                    );
                })}

                {overlay && <KImage image={overlay} width={W} height={H} listening={false} />}

                {texts.map(({ i, slot, fitted }) => (
                    <Text
                        key={`t${i}`}
                        x={fitted.boxX}
                        y={slot.y}
                        width={fitted.boxWidth}
                        align={slot.align ?? 'left'}
                        text={fitted.text}
                        fontSize={fitted.size}
                        fontFamily={fontStack(config, slot)}
                        fontStyle={String(fontWeight(config, slot))}
                        fill={slot.color ?? '#111111'}
                        wrap="none"
                        listening={false}
                    />
                ))}

                {qr && config.qr?.enabled && (
                    <KImage
                        image={qr}
                        x={config.qr.x ?? 0}
                        y={config.qr.y ?? 0}
                        width={config.qr.size ?? 140}
                        height={config.qr.size ?? 140}
                        listening={false}
                    />
                )}

                {/* Selection outline (editor only) */}
                {interactive && selected !== null && config.photo_slots[selected] && (
                    <Rect
                        name="editor-only"
                        x={config.photo_slots[selected].x + config.photo_slots[selected].w / 2}
                        y={config.photo_slots[selected].y + config.photo_slots[selected].h / 2}
                        offsetX={config.photo_slots[selected].w / 2}
                        offsetY={config.photo_slots[selected].h / 2}
                        width={config.photo_slots[selected].w}
                        height={config.photo_slots[selected].h}
                        rotation={config.photo_slots[selected].rotate ?? 0}
                        cornerRadius={config.photo_slots[selected].radius ?? 0}
                        stroke="#10b981"
                        strokeWidth={6}
                        dash={[18, 10]}
                        listening={false}
                    />
                )}
            </Layer>
        </Stage>
    );
});

export default FrameStage;
