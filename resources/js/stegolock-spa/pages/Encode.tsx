import { useEffect, useRef, useState, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface Document { id: number; name: string; extension: string; size: number }
type Step = 1 | 2 | 3;

export default function Encode() {
    const navigate = useNavigate();
    const [documents, setDocuments] = useState<Document[]>([]);
    const [step, setStep] = useState<Step>(1);
    const [docId, setDocId] = useState('');
    const [carriers, setCarriers] = useState<File[]>([]);
    const [masterKey, setMasterKey] = useState('');
    const [dragOver, setDragOver] = useState(false);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const fileRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        axios.get('/api/documents').then((r) => setDocuments(r.data.data ?? r.data)).catch(console.error);
    }, []);

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        const valid = Array.from(files).filter((f) => /\.(png|bmp)$/i.test(f.name));
        setCarriers((prev) => [...prev, ...valid]);
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);
        const fd = new FormData();
        fd.append('document_id', docId);
        fd.append('master_key', masterKey);
        carriers.forEach((f) => fd.append('carriers[]', f));
        try {
            await axios.post('/api/stego/encode', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            navigate('/stego');
        } catch (e: any) {
            setErrors(e.response?.data?.errors ?? { encode: e.response?.data?.message ?? 'Encoding failed.' });
            setStep(3);
        } finally {
            setLoading(false);
        }
    };

    const steps = [
        { icon: '📄', label: 'Document' },
        { icon: '🖼️', label: 'Carriers' },
        { icon: '🔑', label: 'Master Key' },
    ];

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">🔒 Encode Document</h1>

            {/* Stepper */}
            <div className="mb-8 flex items-center justify-between max-w-sm">
                {steps.map((s, i) => {
                    const n = (i + 1) as Step;
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
                            {i < 2 && <div className={`mx-1 flex-1 border-t-2 ${step > n ? 'border-green-400' : 'border-gray-200'}`} />}
                        </div>
                    );
                })}
            </div>

            <form onSubmit={handleSubmit}>
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    {step === 1 && (
                        <div>
                            <h2 className="mb-4 font-semibold text-gray-800">Choose document to encode</h2>
                            <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                                {documents.map((d) => (
                                    <label key={d.id} className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-3 ${docId === String(d.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'}`}>
                                        <input type="radio" name="docId" value={d.id} checked={docId === String(d.id)} onChange={(e) => setDocId(e.target.value)} className="h-4 w-4 text-indigo-600" />
                                        <span className="text-xl">{d.extension === 'pdf' ? '📕' : '📄'}</span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-800">{d.name}</p>
                                            <p className="text-xs text-gray-400">{d.extension?.toUpperCase()} · {(d.size / 1024).toFixed(1)} KB</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div>
                            <h2 className="mb-1 font-semibold text-gray-800">Upload carrier images (PNG / BMP)</h2>
                            <p className="mb-4 text-sm text-gray-500">Data will be distributed across all carriers using LSB steganography.</p>
                            <div
                                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                onDragLeave={() => setDragOver(false)}
                                onDrop={(e) => { e.preventDefault(); setDragOver(false); addFiles(e.dataTransfer.files); }}
                                onClick={() => fileRef.current?.click()}
                                className={`cursor-pointer rounded-xl border-2 border-dashed p-8 text-center ${dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'}`}
                            >
                                <div className="text-4xl mb-2">🖼️</div>
                                <p className="text-sm text-gray-600">Drag & drop or <span className="text-indigo-600 underline">browse</span></p>
                                <input ref={fileRef} type="file" accept=".png,.bmp" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
                            </div>
                            {carriers.length > 0 && (
                                <ul className="mt-3 space-y-1">
                                    {carriers.map((f, i) => (
                                        <li key={i} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                            <span className="truncate text-gray-700">{f.name}</span>
                                            <button type="button" onClick={() => setCarriers((p) => p.filter((_, j) => j !== i))} className="ml-2 text-gray-400 hover:text-red-500">✕</button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {step === 3 && (
                        <div>
                            <h2 className="mb-1 font-semibold text-gray-800">Set master key</h2>
                            <p className="mb-4 text-sm text-gray-500">Used for AES-256-GCM encryption. Never stored — keep it safe.</p>
                            <div className="mb-4 rounded-lg bg-gray-50 p-3 text-sm">
                                <p className="text-gray-500">Document: <strong className="text-gray-700">{documents.find(d => String(d.id) === docId)?.name}</strong></p>
                                <p className="text-gray-500">Carriers: <strong className="text-gray-700">{carriers.length} file(s)</strong></p>
                            </div>
                            {Object.values(errors).map((e, i) => <p key={i} className="mb-2 text-sm text-red-600">{e}</p>)}
                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Master Key</span>
                                <input type="password" value={masterKey} onChange={(e) => setMasterKey(e.target.value)} placeholder="Min 8 characters" className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" autoFocus />
                            </label>
                        </div>
                    )}

                    <div className="mt-6 flex justify-between">
                        <button type="button" onClick={() => setStep((s) => Math.max(1, s - 1) as Step)} disabled={step === 1} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">← Back</button>
                        {step < 3 ? (
                            <button type="button" onClick={() => setStep((s) => (s + 1) as Step)} disabled={(step === 1 && !docId) || (step === 2 && carriers.length === 0)} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">Next →</button>
                        ) : (
                            <button type="submit" disabled={masterKey.length < 8 || loading} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">{loading ? 'Encoding…' : '🔒 Encode & Save'}</button>
                        )}
                    </div>
                </div>
            </form>
        </SpaLayout>
    );
}
