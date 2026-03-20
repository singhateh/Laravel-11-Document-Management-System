import { useEffect, useState } from 'react';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    segments_count: number;
    created_at: string;
    decoding_status?: string;
    decoding_error?: string;
    download_path?: string;
}

export default function Decode() {
    const [docs, setDocs] = useState<StegoDoc[]>([]);
    const [selected, setSelected] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [sessionExpired, setSessionExpired] = useState(false);
    const [decodingStatus, setDecodingStatus] = useState<string | null>(null);
    const [isDownloading, setIsDownloading] = useState(false);

    useEffect(() => {
        axios.get('/api/stego').then((r) => setDocs(r.data.data ?? r.data)).catch(console.error);
    }, []);

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
                setError(statusData.error || 'Decoding failed.');
            }
        } catch (error: unknown) {
            console.error('Error checking decoding status:', error);
            if (error instanceof Error) {
                setError(error.message);
            } else {
                setError('Failed to check decoding status.');
            }
        }
    };

    const handleDecode = async () => {
        setError('');
        setSessionExpired(false);
        setLoading(true);
        try {
            const res = await axios.post('/api/stego/decode', {
                stego_document_id: Number(selected),
            });

            if (!res || (res.status !== 200 && res.status !== 202)) {
                throw new Error('Decode request was not accepted by the server.');
            }
            
            // Set initial decoding status
            setDecodingStatus('pending');
            
            // Update docs with initial status
            setDocs(prevDocs => prevDocs.map(doc => 
                doc.id === parseInt(selected) ? { ...doc, decoding_status: 'pending' } : doc
            ));
        } catch (e: any) {
            if (e.response?.status === 401) {
                setSessionExpired(true);
            } else if (e.response?.data?.message) {
                setError(e.response.data.message);
            } else {
                setError('Decoding failed.');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = async () => {
        if (!selected || isDownloading) return;

        setIsDownloading(true);

        try {
            const res = await axios.get(`/api/stego/decode/${selected}`, { responseType: 'blob' });
            const doc = docs.find((d) => String(d.id) === selected);
            const filename = (doc?.document?.name ?? 'decoded') + '.' + (doc?.document?.extension ?? 'bin');
            const url = URL.createObjectURL(new Blob([res.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        } catch (e: any) {
            if (e.response?.status === 401) {
                setSessionExpired(true);
            } else if (e.response?.data instanceof Blob) {
                const text = await e.response.data.text();
                try { setError(JSON.parse(text).message ?? 'Download failed.'); } catch { setError('Download failed.'); }
            } else {
                setError(e.response?.data?.message ?? 'Download failed.');
            }
        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">🔓 Decode Document</h1>

            {/* Session Master Key banner */}
            <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span className="text-lg">🔑</span>
                <p><strong>Session Master Key active.</strong> Decryption uses the key derived from your password at login — no passphrase required.</p>
            </div>

            {sessionExpired && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    ⚠️ Your session has expired. Please <a href="/login" className="underline font-medium">log in again</a> to refresh your Master Key.
                </div>
            )}
            {error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    ⚠️ {error}
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

            <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="mb-4 font-semibold text-gray-800">Select stego document to decode</h2>
                {docs.length === 0 ? (
                    <p className="text-sm text-gray-500">No encoded documents found. Encode one first.</p>
                ) : (
                    <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                        {docs.map((d) => (
                            <label key={d.id} className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 ${selected === String(d.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'}`}>
                                <input type="radio" name="stego" value={d.id} checked={selected === String(d.id)} onChange={(e) => setSelected(e.target.value)} className="h-4 w-4 text-indigo-600" />
                                <span className="text-xl">🔒</span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-800">{d.document?.name ?? '—'}</p>
                                    <p className="text-xs text-gray-400">{d.segments_count} carrier(s) · {new Date(d.created_at).toLocaleDateString()}</p>
                                    {d.decoding_status && (
                                        <p className="text-xs text-blue-600">
                                            Status: {d.decoding_status}
                                        </p>
                                    )}
                                </div>
                            </label>
                        ))}
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-2">
                    {decodingStatus === 'completed' ? (
                        <button onClick={handleDownload} disabled={!selected || isDownloading} className="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40">
                            {isDownloading ? 'Downloading…' : '📥 Download'}
                        </button>
                    ) : (
                        <button onClick={handleDecode} disabled={!selected || loading || decodingStatus === 'in_progress'} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">
                            {loading ? 'Decoding…' : '🔓 Decode'}
                        </button>
                    )}
                </div>
            </div>
        </SpaLayout>
    );
}
