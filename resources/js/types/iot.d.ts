export interface TelemetryData {
    temperature: number;
    humidity: number;
    noise_db: number;
    measured_at: string;
}

export interface TelemetryRecord extends TelemetryData {
    id: number;
}

export interface DeviceSummary {
    id: number;
    name: string;
    location: string | null;
    description: string | null;
    is_active: boolean;
    status: 'online' | 'idle' | 'offline' | 'never_connected';
    last_seen_at: string | null;
    latest_telemetry: TelemetryData | null;
}

export interface DeviceDetail extends DeviceSummary {
    description: string | null;
}

export interface ChartDataPoint {
    measured_at: string;
    temperature: number;
    humidity: number;
    noise_db: number;
}

export interface PaginatedTelemetry {
    data: TelemetryRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

export interface TelemetryFilters {
    from: string | null;
    to: string | null;
}
