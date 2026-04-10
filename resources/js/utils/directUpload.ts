import axios from 'axios';

export type UploadVisibility = 'public' | 'private';

export const DIRECT_UPLOAD_MAX_FILE_BYTES = 50 * 1024 * 1024;

const SUPPORTED_MIME_TYPES = new Set([
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain',
]);

const EXTENSION_TO_MIME: Record<string, string> = {
    pdf: 'application/pdf',
    doc: 'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    txt: 'text/plain',
};

export interface UploadProgressSnapshot {
    loadedBytes: number;
    totalBytes: number;
    percent: number;
}

export interface DirectUploadResult {
    sessionToken: string;
    documentId: number;
    ingestStatus: string;
}

export interface UploadFileDirectOptions {
    file: File;
    folderId: number;
    visibility: UploadVisibility;
    signal?: AbortSignal;
    retries?: number;
    idempotencyToken?: string;
    onProgress?: (progress: UploadProgressSnapshot) => void;
}

export function resolveSupportedMimeType(file: File): string {
    const normalizedType = (file.type || '').toLowerCase();
    if (SUPPORTED_MIME_TYPES.has(normalizedType)) {
        return normalizedType;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    const mappedType = EXTENSION_TO_MIME[extension];
    if (mappedType) {
        return mappedType;
    }

    throw new Error(`Unsupported file type: ${file.name}. Allowed types are PDF, DOC, DOCX, and TXT.`);
}

export async function uploadFileDirect(options: UploadFileDirectOptions): Promise<DirectUploadResult> {
    const {
        file,
        folderId,
        visibility,
        signal,
        retries = 1,
        idempotencyToken,
        onProgress,
    } = options;

    if (file.size > DIRECT_UPLOAD_MAX_FILE_BYTES) {
        throw new Error(`File exceeds 50 MB limit: ${file.name}`);
    }

    resolveSupportedMimeType(file);

    const maxAttempts = Math.max(1, retries + 1);
    let lastError: unknown = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const formData = new FormData();
            formData.append('folder_id', String(folderId));
            formData.append('visibility', visibility);
            formData.append('files', file);

            await axios.post('/upload', formData, {
                signal,
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (event) => {
                    const loadedBytes = Math.max(0, event.loaded ?? 0);
                    const totalBytes = Math.max(file.size, event.total ?? file.size);
                    const percent = totalBytes > 0
                        ? Math.min(100, Math.round((loadedBytes / totalBytes) * 100))
                        : 0;

                    onProgress?.({ loadedBytes, totalBytes, percent });
                },
            });

            return {
                sessionToken: idempotencyToken ?? '',
                documentId: 0,
                ingestStatus: 'completed',
            };
        } catch (error: unknown) {
            lastError = error;

            if (signal?.aborted) {
                throw new Error('Upload canceled.');
            }

            if (attempt === maxAttempts) {
                break;
            }
        }
    }

    if (lastError instanceof Error) {
        throw lastError;
    }

    throw new Error('Upload failed.');
}