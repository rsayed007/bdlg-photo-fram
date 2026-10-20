/** frames.config — see docs/FRAME_CONFIG.md */
export interface FrameFont {
    family: string;
    weight?: number;
    src: string;
}

export interface PhotoSlot {
    /** Top-left corner and size in native frame pixels. Rotation is around the slot centre. */
    x: number;
    y: number;
    w: number;
    h: number;
    radius?: number;
    rotate?: number;
    required?: boolean;
}

export type TextField =
    | 'name'
    | 'level'
    | 'points'
    | 'reviews'
    | 'photos'
    | 'videos'
    | 'photo_views'
    | 'places_added'
    | 'roads_added'
    | 'views_per_photo'
    | 'static';

export interface TextSlot {
    field: TextField;
    /** Anchor x (left edge / centre / right edge depending on align) and top y of the line. */
    x: number;
    y: number;
    size: number;
    color?: string;
    align?: 'left' | 'center' | 'right';
    font?: string;
    weight?: number;
    template?: string;
    format?: 'number' | 'compact' | 'raw';
    optional?: boolean;
    max_width?: number;
}

export interface FrameConfig {
    fonts: FrameFont[];
    photo_slots: PhotoSlot[];
    text_slots: TextSlot[];
    qr?: { enabled?: boolean; x?: number; y?: number; size?: number };
}

export interface EditorFrame {
    id: number;
    slug: string;
    title: string;
    event: string | null;
    size_w: number;
    size_h: number;
    background_url: string;
    overlay_url: string | null;
    thumbnail_url: string | null;
    config: FrameConfig;
}

export type FrameStats = Partial<Record<Exclude<TextField, 'static'>, number | string | null>> & { profile_url?: string | null };

/** Per-slot photo placement. dx/dy = image centre offset from slot centre; zoom ≥ 1. */
export interface PlacedPhoto {
    url: string;
    path?: string;
    image: HTMLImageElement;
    dx: number;
    dy: number;
    zoom: number;
}
