import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface QueuedFlash {
    contrib_id: string;
    created: boolean;
    queued: boolean;
    position: number | null;
}

export interface SharedData {
    name: string;
    auth: Auth;
    locale: 'bn' | 'en';
    translations: Record<string, string>;
    flash: { success?: string | null; error?: string | null; queued?: QueuedFlash | null };
    ziggy?: unknown;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export type GuideStatus = 'pending' | 'active' | 'restricted' | 'failed' | 'inactive';

export interface GuideStats {
    level: number | null;
    points: number | null;
    reviews: number | null;
    photos: number | null;
    videos: number | null;
    photo_views: number | null;
    places_added: number | null;
    roads_added: number | null;
}

export interface Guide extends GuideStats {
    id: number;
    contrib_id: string;
    name: string | null;
    avatar_url: string | null;
    profile_url: string;
    status: GuideStatus;
    views_per_photo: number | null;
    last_synced_at: string | null;
    rank?: number;
}

export interface HistoryPoint {
    date: string;
    points: number | null;
    reviews: number | null;
    photos: number | null;
    photo_views: number | null;
}

export interface FrameCard {
    id: number;
    slug: string;
    title: string;
    event: string | null;
    thumbnail_url: string | null;
    size_w: number;
    size_h: number;
    create_url: string;
}

export interface GenerationCard {
    id: string;
    image_url: string;
    share_url: string;
    frame_title: string;
    guide_name?: string | null;
    created_at: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
