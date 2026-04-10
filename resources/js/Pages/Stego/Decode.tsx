import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEvent, useState, useEffect } from 'react';
import axios from 'axios';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    status?: 'pending' | 'ready' | 'failed';
    segments_count: number;
    created_at: string;
    decoding_status?: string;
    decoding_error?: string;
    download_path?: string;
}

interface DecodeProps extends PageProps {
    stegoDocs: StegoDoc[];
    errors?: Record<string, string>;
}

export default function Decode({ auth, stegoDocs, errors = {} }: DecodeProps) {
    const [selected, setSelected] = useState('');
    const [submitError, setSubmitError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [decodingStatus, setDecodingStatus] = useState<string | null>(null);
    const [isDownloading, setIsDownloading] = useState(false);

    const [docs, setDocs] = useState<StegoDoc[]>(stegoDocs);

    useEffect(() => {
        let interval: NodeJS.Timeout;
        if (decodingStatus && ['pending', 'in_progress'].includes(decodingStatus)) {
            interval = setInterval(() => {
                checkDecodingStatus();
            }, 2000);
        }
        return () => clearInterval(interval);
    }, [decodingStatus]);

    const checkDecodingStatus = async () => {
        if (!selected) return;

        try {
            const response = await axios.get(`/api/stego/documents/${selected}/status`);
            const statusData = response.data;

            if (!statusData || typeof statusData !== 'object' || !statusData.status) {
                throw new Error('Invalid status response from server.');
            }

            const nextStatus = statusData.status as string;
            
            setDocs(prevDocs => prevDocs.map(doc => 
                doc.id === parseInt(selected) ? { 
                    ...doc, 
                    decoding_status: nextStatus,
                    decoding_error: statusData.error,
                    download_path: statusData.download_path
                } : doc
            ));

            setDecodingStatus(nextStatus);

            if (nextStatus === 'completed') {
                // Decoding is complete, show download button
                setDecodingStatus('completed');
            } else if (nextStatus === 'failed') {
                // Decoding failed
                setSubmitError(statusData.error || 'Decoding failed.');
            }
        } catch (error: unknown) {
            console.error('Error checking decoding status:', error);
            if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Failed to check decoding status.');
            }
        }
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!selected || isSubmitting) return;

        setSubmitError('');
        setIsSubmitting(true);

        try {
            const response = await axios.post('/api/stego/decode', {
                stego_document_id: Number(selected),
            });

            if (!response || (response.status !== 200 && response.status !== 202)) {
                throw new Error('Decode request was not accepted by the server.');
            }
            
            // Set initial decoding status
            setDecodingStatus('pending');
            
            // Update docs with initial status
            setDocs(prevDocs => prevDocs.map(doc => 
                doc.id === parseInt(selected) ? { ...doc, decoding_status: 'pending' } : doc
            ));

            setSubmitError('');
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 419) {
                setSubmitError('Session expired or CSRF token mismatch. Please refresh and log in again.');
            } else if (axios.isAxiosError(error) && error.response?.data?.message) {
                setSubmitError(error.response.data.message);
            } else if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Decode failed. Please try again.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDownload = async () => {
        if (!selected || isDownloading) return;

        setIsDownloading(true);

        try {
            const response = await axios.get(`/api/stego/decode/${selected}`, {
                responseType: 'blob',
            });

            const contentType = response.headers['content-type'] || '';
            if (contentType.includes('application/json')) {
                const text = await response.data.text();
                const parsed = JSON.parse(text) as { message?: string };
                throw new Error(parsed.message || 'Download failed.');
            }

            const filename = parseDownloadFilename(response.headers['content-disposition']);
            const blobUrl = window.URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(blobUrl);
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 419) {
                setSubmitError('Session expired or CSRF token mismatch. Please refresh and log in again.');
            } else if (axios.isAxiosError(error) && error.response?.data?.message) {
                setSubmitError(error.response.data.message);
            } else if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Download failed. Please try again.');
            }
        } finally {
            setIsDownloading(false);
        }
    };

    const parseDownloadFilename = (contentDisposition?: string): string => {
        if (!contentDisposition) return 'decoded_file';

        const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8Match?.[1]) {
            return decodeURIComponent(utf8Match[1]);
        }

        const quotedMatch = contentDisposition.match(/filename="([^"]+)"/i);
        if (quotedMatch?.[1]) {
            return quotedMatch[1];
        }

        const plainMatch = contentDisposition.match(/filename=([^;]+)/i);
        return plainMatch?.[1]?.trim() || 'decoded_file';
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    🔓 Decode Document
                </h2>
            }
        >
            <Head title="Decode Document" />

            <div className="py-8">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

                    {/* Session Master Key banner */}
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <span className="text-lg">🔑</span>
                        <p>
                            <strong>Session Master Key active.</strong> Decryption uses the key
                            derived from your password at login — no passphrase entry is needed.
                        </p>
                    </div>

                    {errors.session && (
                        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            ⚠️ {errors.session}{' '}
                            <a href={route('login')} className="underline font-medium">Log in again</a>.
                        </div>
                    )}
                    {errors.decode && (
                        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            ⚠️ {errors.decode}
                        </div>
                    )}
                    {submitError && (
                        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            ⚠️ {submitError}
                        </div>
                    )}

                    {decodingStatus && (
                        <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            {decodingStatus === 'pending' && '⏳ Decoding pending...'}
                            {decodingStatus === 'in_progress' && '🔄 Decoding in progress...'}
                            {decodingStatus === 'completed' && '✅ Decoding completed! Click download to get your file.'}
                            {decodingStatus === 'failed' && '❌ Decoding failed. Please try again.'}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <div className="rounded-xl bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-lg font-semibold text-gray-800">
                                🔒 Select a stego document to decode
                            </h3>
                            {errors.stego_document_id && (
                                <p className="mb-3 text-sm text-red-600">{errors.stego_document_id}</p>
                            )}

                            {docs.length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    No stego documents found. Encode a document first.
                                </p>
                            ) : (
                                <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                    {docs.map((doc) => (
                                        <label
                                            key={doc.id}
                                            className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 transition-all ${
                                                selected === String(doc.id)
                                                    ? 'border-indigo-500 bg-indigo-50'
                                                    : 'border-gray-200 hover:border-indigo-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="stego_document_id"
                                                value={String(doc.id)}
                                                checked={selected === String(doc.id)}
                                                onChange={(e) => setSelected(e.target.value)}
                                                className="h-4 w-4 text-indigo-600"
                                            />
                                            <span className="text-2xl">🔒</span>
                                            <div className="flex-1 min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-800">
                                                    {doc.document?.name ?? '(unknown)'}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {doc.segments_count} carrier(s) ·{' '}
                                                    {new Date(doc.created_at).toLocaleDateString()}
                                                </p>
                                                {doc.decoding_status && (
                                                    <p className="text-xs text-blue-600">
                                                        Status: {doc.decoding_status}
                                                    </p>
                                                )}
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            )}

                            <div className="mt-6 flex justify-end gap-2">
                                {decodingStatus === 'completed' ? (
                                    <button
                                        type="button"
                                        onClick={handleDownload}
                                        disabled={!selected || isDownloading}
                                        className="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                                    >
                                        {isDownloading ? 'Downloading...' : '📥 Download'}
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={!selected || isSubmitting || decodingStatus === 'in_progress'}
                                        className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                                    >
                                        {isSubmitting ? 'Decoding...' : '🔓 Decode'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
