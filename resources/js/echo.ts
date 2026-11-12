export interface ScanProgressPayload {
    event?: string;
    sourceId: number;
    scanned: number;
    total: number;
    isCompleted: boolean;
}

export interface MediaProcessedPayload {
    event?: string;
    mediaFileUuid: string;
    thumbnailPath: string | null;
    previewPath: string | null;
    dominantColor: string | null;
    capturedAt: string | null;
}

export interface MercureCallbacks {
    onProgress?: (data: ScanProgressPayload) => void;
    onMediaProcessed?: (data: MediaProcessedPayload) => void;
}

export function subscribeToMercure(
    hubUrl: string,
    callbacks: MercureCallbacks,
): EventSource | null {
    if (!hubUrl) return null;

    let url: URL;
    try {
        if (typeof window !== 'undefined') {
            const parsed = new URL(hubUrl, window.location.origin);
            const isLocal =
                ['localhost', '127.0.0.1'].includes(parsed.hostname) &&
                ['localhost', '127.0.0.1'].includes(window.location.hostname);

            if (isLocal) {
                url = new URL(
                    `${parsed.pathname}${parsed.search}`,
                    window.location.origin,
                );
            } else {
                url = parsed;
            }
        } else {
            url = new URL(hubUrl);
        }
    } catch {
        return null;
    }

    url.searchParams.set('topic', 'gallery');

    const eventSource = new EventSource(url);

    eventSource.onmessage = (event: MessageEvent<string>) => {
        try {
            const data = JSON.parse(event.data);

            if (
                data.event === 'MediaScanProgress' ||
                data.total !== undefined
            ) {
                callbacks.onProgress?.(data as ScanProgressPayload);
            }
            if (
                data.event === 'MediaProcessed' ||
                data.mediaFileId !== undefined
            ) {
                callbacks.onMediaProcessed?.(data as MediaProcessedPayload);
            }
        } catch (e) {
            console.error('Error parsing Mercure SSE event:', e);
        }
    };

    return eventSource;
}
