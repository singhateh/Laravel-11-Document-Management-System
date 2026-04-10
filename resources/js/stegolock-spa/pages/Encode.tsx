import { useEffect, useRef, useState, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface Document { id: number; name: string; extension: string; size: number }
interface QualityMetric { carrier: string; psnr: number | null; threshold_40db: boolean }
type Step = 1 | 2 | 3;  // 3 = results

export default function Encode() {
    const navigate = useNavigate();
    const [documents, setDocuments] = useState<Document[]>([]);
    const [step, setStep] = useState<Step>(1);
    const [docId, setDocId] = useState('');
    const [carriers, setCarriers] = useState<File[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [qualityMetrics, setQualityMetrics] = useState<QualityMetric[]>([]);
    const fileRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        axios.get('/api/documents').then((r) => setDocuments(r.data.data ?? r.data)).catch(console.error);
    }, []);

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        const valid = Array.from(files).filter((f) =>
            /\.(png|bmp|jpe?g)$/i.test(f.name) && f.size <= 100 * 1024 * 1024
        );
        const invalid = Array.from(files).filter((f) =>
            !/\.(png|bmp|jpe?g)$/i.test(f.name) || f.size > 100 * 1024 * 1024
        );
        if (invalid.length > 0) {
            const messages = [];
            const invalidTypes = invalid.filter(f => !/\.(png|bmp|jpe?g)$/i.test(f.name));
            const oversized = invalid.filter(f => f.size > 100 * 1024 * 1024);
            if (invalidTypes.length > 0) {
                messages.push(`Invalid type(s): ${invalidTypes.map(f => f.name).join(', ')} (only PNG/BMP/JPEG)`);
            }
            if (oversized.length > 0) {
                messages.push(`Too large: ${oversized.map(f => f.name).join(', ')} (max 100 MB)`);
            }
            setErrors({ carriers: messages.join('. ') });
        }
        setCarriers((prev) => [...prev, ...valid]);
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLoading(true);
        const fd = new FormData();
        fd.append('document_id', docId);
        carriers.forEach((f) => fd.append('carriers[]', f));
        try {
            const res = await axios.post('/api/stego/encode', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setQualityMetrics(res.data.quality_metrics ?? []);
            setStep(3);
        } catch (e: any) {
            if (e.response?.status === 401) {
                setErrors({ session: e.response.data?.message ?? 'Session expired. Please log in again.' });
            } else {
                setErrors(e.response?.data?.errors ?? { encode: e.response?.data?.message ?? 'Encoding failed.' });
            }
            setStep(2);
        } finally {
            setLoading(false);
        }
    };

    const steps = [
        { icon: 'ðŸ“„', label: 'Document' },
        { icon: 'ðŸ–¼ï¸', label: 'Carriers' },
        { icon: 'âœ…', label: 'Results' },
    ];

    return (
        <SpaLayout>
            <h1 className="mb-6 text-xl font-bold text-gray-900">ðŸ”’ Encode Document</h1>

            {/* Session Master Key banner */}
            <div className="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span className="text-lg">ðŸ”‘</span>
                <p><strong>Session Master Key active.</strong> Your encryption key was derived from your password at login â€” no passphrase entry is needed.</p>
            </div>

            {errors.session && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    âš ï¸ {errors.session}
                </div>
            )}

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
                                    {done ? 'âœ“' : s.icon}
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
                                        <span className="text-xl">{d.extension === 'pdf' ? 'ðŸ“•' : 'ðŸ“„'}</span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-gray-800">{d.name}</p>
                                            <p className="text-xs text-gray-400">{d.extension?.toUpperCase()} Â· {(d.size / 1024).toFixed(1)} KB</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div>
                            <h2 className="mb-1 font-semibold text-gray-800">Upload carrier images (PNG / BMP / JPEG)</h2>
                            <p className="mb-4 text-sm text-gray-500">PNG, BMP, or JPEG only (max 100 MB per file). Data is dynamically distributed across all carriers using LSB steganography for optimal PSNR. Each carrier must meet PSNR ≥ 40 dB.</p>

                            {/* Encoding summary */}
                            <div className="mb-4 rounded-lg bg-gray-50 p-3 text-sm">
                                <p className="text-gray-500">Document: <strong className="text-gray-700">{documents.find(d => String(d.id) === docId)?.name}</strong></p>
                                <p className="text-gray-500">Carriers: <strong className="text-gray-700">{carriers.length} file(s) selected</strong></p>
                            </div>

                            {Object.entries(errors).filter(([k]) => k !== 'session').map(([k, v]) => (
                                <p key={k} className="mb-2 text-sm text-red-600">{v}</p>
                            ))}

                            <div
                                onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                onDragLeave={() => setDragOver(false)}
                                onDrop={(e) => { e.preventDefault(); setDragOver(false); addFiles(e.dataTransfer.files); }}
                                onClick={() => fileRef.current?.click()}
                                className={`cursor-pointer rounded-xl border-2 border-dashed p-8 text-center ${dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400'}`}
                            >
                                <div className="text-4xl mb-2">ðŸ–¼ï¸</div>
                                <p className="text-sm text-gray-600">Drag & drop or <span className="text-indigo-600 underline">browse</span></p>
                                <input ref={fileRef} type="file" accept=".png,.bmp,.jpg,.jpeg" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
                            </div>
                            {carriers.length > 0 && (
                                <ul className="mt-3 space-y-1">
                                    {carriers.map((f, i) => (
                                        <li key={i} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                            <span className="truncate text-gray-700">{f.name}</span>
                                            <button type="button" onClick={() => setCarriers((p) => p.filter((_, j) => j !== i))} className="ml-2 text-gray-400 hover:text-red-500">âœ•</button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    {step === 3 && (
                        <div>
                            <div className="mb-4 flex items-center gap-2 text-green-700">
                                <span className="text-2xl">âœ…</span>
                                <h2 className="font-semibold text-lg">Document encoded successfully!</h2>
                            </div>
                            <p className="mb-4 text-sm text-gray-500">The document has been encrypted with AES-256-GCM and hidden across your carrier images using LSB steganography.</p>

                            {qualityMetrics.length > 0 && (
                                <div className="mb-4">
                                    <h3 className="mb-2 text-sm font-medium text-gray-700">PSNR Quality Metrics</h3>
                                    <div className="overflow-hidden rounded-lg border border-gray-200">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-gray-50 text-xs text-gray-500">
                                                <tr>
                                                    <th className="px-4 py-2 text-left">Carrier</th>
                                                    <th className="px-4 py-2 text-right">PSNR (dB)</th>
                                                    <th className="px-4 py-2 text-center">â‰¥ 40 dB</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {qualityMetrics.map((m, i) => (
                                                    <tr key={i}>
                                                        <td className="px-4 py-2 text-gray-700 truncate max-w-xs">{m.carrier}</td>
                                                        <td className="px-4 py-2 text-right font-mono text-gray-700">
                                                            {m.psnr !== null ? m.psnr.toFixed(2) : 'â€”'}
                                                        </td>
                                                        <td className="px-4 py-2 text-center">
                                                            {m.threshold_40db
                                                                ? <span className="text-green-600 font-medium">âœ“ Pass</span>
                                                                : <span className="text-red-500 font-medium">âœ• Fail</span>}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="mt-6 flex justify-between">
                        {step < 3 ? (
                            <>
                                <button type="button" onClick={() => setStep((s) => Math.max(1, s - 1) as Step)} disabled={step === 1} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">â† Back</button>
                                {step < 2 ? (
                                    <button type="button" onClick={() => setStep((s) => (s + 1) as Step)} disabled={step === 1 && !docId} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">Next â†’</button>
                                ) : (
                                    <button type="submit" disabled={carriers.length === 0 || loading} className="rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40">{loading ? 'Encodingâ€¦' : 'ðŸ”’ Encode & Save'}</button>
                                )}
                            </>
                        ) : (
                            <button type="button" onClick={() => navigate('/stego')} className="ml-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">View All Stego Docs â†’</button>
                        )}
                    </div>
                </div>
            </form>
        </SpaLayout>
    );
}


