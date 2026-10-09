/**
 * Backup feature wire shapes — the `BackupListPageProps` page props, the
 * encryption-key JSON list, the generated-key response and the status
 * polling envelope.
 */

export type BackupStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface BackupEntry {
    id: number;
    filename: string;
    size_bytes: number;
    status: BackupStatus;
    created_at: string;
}

export interface AdminListMeta {
    current_page: number;
    from: number | null;
    to: number | null;
    last_page: number;
    per_page: number;
    total: number;
}

export interface EncryptionKeyEntry {
    id: number;
    name: string;
    created_at: string;
    is_active: boolean;
    backups_count: number;
}

export interface EncryptionKeyListResponse {
    data: EncryptionKeyEntry[];
}

export interface GeneratedKeyResponse {
    data: {
        id: number;
        name: string;
        key_value: string;
        is_active: boolean;
        created_at: string;
    };
}

export interface BackupStatusPayload {
    data: {
        id: number;
        status: BackupStatus;
        progress: number | null;
        completed_at: string | null;
        error_message: string | null;
    };
}
