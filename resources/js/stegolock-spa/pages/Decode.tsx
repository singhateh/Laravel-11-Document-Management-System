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
    const [step, setStep] = useState<1 | 2>(1);
    const [selected, setSelected] = useState('');
    const [masterKey, setMasterKey] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        axios.get('/api/stego').then((r) => setDocs(r.data.data ?? r.data)).catch(console.error);
    }, []);

    const handleDecode = async () => {
        setError('');
        setLoading(true);
        try {
            const res = await axios.post('/api/stego/decode', { stego_document_id: selected, master_key: masterKey }, { responseType: 'blob' });
            const doc = docs.find((d) => String(d.id) === selected);
            const filename = (doc?.document?.name ?? 'decoded') + '.' + (doc?.document?.extension ?? 'bin');
            const url = URL.createObjectURL(new Blob([res.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        } catch (e: any) {
            if (e.response?.data instanceof Blob) {
                const text = await e.response.data.text();
                try { setError(JSON.parse(text).message ?? 'Decoding failed.'); } catch { setError('Decoding failed.'); }
            } else {
                setError(e.response?.data?.message ?? 'Decoding failed.');
            }
        } finally {
            setLoading(false);
        }
    };

    const selectedDoc = docs.find((d) => String(d.id) === selected);

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">🔓 Decode Document</h1>

            {/* Stepper */}
            <div className="mb-8 flex items-center max-w-xs">
                {[{ icon: '🔒', label: 'Select' }, { icon: '🔑', label: 'Key' }].map((s, i) => {
                    const n = (i + 1) as 1 | 2;
                    const done = step > n;
                    const active = step === n;
                    return (
                        <div key={n} className="flex flex-1 items-center">
                            <div className="flex flex-col items-center gap-1">
                                <div className={`flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold ${done ? 'bg-green-500 text-white' : active ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                    {done ? '✓' : s.icon}
                                </div>
                                <span className={`text-xs ${active ? 'font-medium text-indigo-700' : 'text-gray-400'}`}>{s.label}</span>
                            </div>
                            {i < 1 && <div className={`mx-1 flex-1 border-t-2 ${step > n ? 'border-green-400' : 'border-gray-200'}`} />}
                        </div>
                    );
                })}
            </div>

            <div className="rounded-xl bg-white p-6 shadow-sm">
                {step === 1 && (
                    <div>
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
                                        </div>
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <h2 className="mb-1 font-semibold text-gray-800">Enter master key</h2>
                        <p className="mb-4 text-sm text-gray-500">For <strong>{selectedDoc?.document?.name ?? '—'}</strong>. The file will be downloaded.</p>
                        {error && <p className="mb-3 text-sm text-red-600">{error}</p>}
                        <label className="block">
                            <span className="text-sm font-medium text-gray-700">Master Key</span>
                            <input type="password" value={masterKey} onChange={(e) => setMasterKey(e.target.value)} placeholder="Your secret passphrase" className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" autoFocus />
                        </label>
                    </div>
                )}

                <div className="mt-6 flex justify-between">
                    <button onClick={() => setStep(1)} disabled={step === 1} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">← Back</button>
                    {step === 1 ? (
                        <button onClick={() => setStep(2)} disabled={!selected} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">Next →</button>
                    ) : (
                        <button onClick={handleDecode} disabled={!masterKey || loading} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">{loading ? 'Decoding…' : '🔓 Decode & Download'}</button>
                    )}
                </div>
            </div>
        </SpaLayout>
    );
}
