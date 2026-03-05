import { useEffect, useState } from 'react';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    segments_count: number;
    created_at: string;
}

export default function Decode() {
    const [docs, setDocs] = useState<StegoDoc[]>([]);
    const [selected, setSelected] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [sessionExpired, setSessionExpired] = useState(false);

    useEffect(() => {
        axios.get('/api/stego').then((r) => setDocs(r.data.data ?? r.data)).catch(console.error);
    }, []);

    const handleDecode = async () => {
        setError('');
        setSessionExpired(false);
        setLoading(true);
        try {
            const res = await axios.post('/api/stego/decode', { stego_document_id: selected }, { responseType: 'blob' });
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
                try { setError(JSON.parse(text).message ?? 'Decoding failed.'); } catch { setError('Decoding failed.'); }
            } else {
                setError(e.response?.data?.message ?? 'Decoding failed.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">ðŸ”“ Decode Document</h1>

            {/* Session Master Key banner */}
            <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span className="text-lg">ðŸ”‘</span>
                <p><strong>Session Master Key active.</strong> Decryption uses the key derived from your password at login â€” no passphrase required.</p>
            </div>

            {sessionExpired && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    âš ï¸ Your session has expired. Please <a href="/login" className="underline font-medium">log in again</a> to refresh your Master Key.
                </div>
            )}
            {error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    âš ï¸ {error}
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
                                <span className="text-xl">ðŸ”’</span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-gray-800">{d.document?.name ?? 'â€”'}</p>
                                    <p className="text-xs text-gray-400">{d.segments_count} carrier(s) Â· {new Date(d.created_at).toLocaleDateString()}</p>
                                </div>
                            </label>
                        ))}
                    </div>
                )}

                <div className="mt-6 flex justify-end">
                    <button onClick={handleDecode} disabled={!selected || loading} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">
                        {loading ? 'Decodingâ€¦' : 'ðŸ”“ Decode & Download'}
                    </button>
                </div>
            </div>
        </SpaLayout>
    );
}
