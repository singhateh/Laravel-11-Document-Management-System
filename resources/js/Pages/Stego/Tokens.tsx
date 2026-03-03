import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import axios from 'axios';

interface TokenInfo {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface TokensProps extends PageProps {
    tokens: TokenInfo[];
}

export default function Tokens({ auth, tokens: initialTokens }: TokensProps) {
    const [tokens, setTokens] = useState<TokenInfo[]>(initialTokens);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [newToken, setNewToken] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [copied, setCopied] = useState(false);

    const createToken = async () => {
        if (!name.trim()) { setError('Token name is required.'); return; }
        setError('');
        setLoading(true);
        try {
            const res = await axios.post('/api/auth/tokens', { name: name.trim() });
            setNewToken(res.data.token);
            setTokens((prev) => [res.data.token_info ?? { id: Date.now(), name: name.trim(), last_used_at: null, expires_at: null, created_at: new Date().toISOString() }, ...prev]);
            setName('');
            setCreating(false);
        } catch (e: any) {
            setError(e.response?.data?.message ?? 'Failed to create token.');
        } finally {
            setLoading(false);
        }
    };

    const revokeToken = async (id: number) => {
        if (!confirm('Revoke this token? Any application using it will lose access.')) return;
        try {
            await axios.delete(`/api/auth/tokens/${id}`);
            setTokens((prev) => prev.filter((t) => t.id !== id));
        } catch {
            alert('Failed to revoke token.');
        }
    };

    const copyToken = () => {
        if (!newToken) return;
        navigator.clipboard.writeText(newToken).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">API Tokens</h2>
                    <button
                        onClick={() => { setCreating(true); setNewToken(null); }}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        + New Token
                    </button>
                </div>
            }
        >
            <Head title="API Tokens" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Info */}
                    <div className="rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-800">
                        API tokens allow programmatic access to StegoLock via the REST API.
                        Tokens expire after <strong>365 days</strong>. Treat them like passwords — never share them publicly.
                    </div>

                    {/* New token revealed once */}
                    {newToken && (
                        <div className="rounded-xl border border-green-200 bg-green-50 p-5">
                            <p className="text-sm font-semibold text-green-800 mb-2">
                                ✅ Token created — copy it now, it won't be shown again
                            </p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 rounded bg-white border border-green-200 px-3 py-2 text-xs font-mono text-green-900 break-all">
                                    {newToken}
                                </code>
                                <button
                                    onClick={copyToken}
                                    className="shrink-0 rounded-md border border-green-300 bg-white px-3 py-2 text-xs font-medium text-green-700 hover:bg-green-100"
                                >
                                    {copied ? '✓ Copied' : 'Copy'}
                                </button>
                            </div>
                            <p className="mt-2 text-xs text-green-600">
                                Use this token in the <code>Authorization: Bearer &lt;token&gt;</code> header.
                            </p>
                        </div>
                    )}

                    {/* Create form */}
                    {creating && (
                        <div className="rounded-xl bg-white p-5 shadow-sm">
                            <h3 className="mb-3 text-sm font-medium text-gray-800">Create new token</h3>
                            {error && <p className="mb-2 text-xs text-red-600">{error}</p>}
                            <div className="flex gap-3">
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Token name (e.g. My Script)"
                                    className="flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    onKeyDown={(e) => e.key === 'Enter' && createToken()}
                                    autoFocus
                                />
                                <button
                                    onClick={createToken}
                                    disabled={loading}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {loading ? 'Creating…' : 'Create'}
                                </button>
                                <button
                                    onClick={() => { setCreating(false); setError(''); }}
                                    className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Token list */}
                    {tokens.length === 0 ? (
                        <div className="rounded-xl bg-white py-14 text-center shadow-sm">
                            <div className="text-4xl">🔑</div>
                            <h3 className="mt-3 text-sm font-medium text-gray-900">No tokens yet</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Create a token to use the StegoLock REST API.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Name
                                        </th>
                                        <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Created
                                        </th>
                                        <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Last Used
                                        </th>
                                        <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Expires
                                        </th>
                                        <th className="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {tokens.map((t) => {
                                        const expired = t.expires_at && new Date(t.expires_at) < new Date();
                                        return (
                                            <tr key={t.id} className="hover:bg-gray-50">
                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-base">🔑</span>
                                                        <span className="text-sm font-medium text-gray-800">{t.name}</span>
                                                        {expired && (
                                                            <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                                                Expired
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-500">
                                                    {new Date(t.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-400">
                                                    {t.last_used_at
                                                        ? new Date(t.last_used_at).toLocaleString()
                                                        : 'Never'}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-400">
                                                    {t.expires_at
                                                        ? new Date(t.expires_at).toLocaleDateString()
                                                        : '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-5 py-4 text-right">
                                                    <button
                                                        onClick={() => revokeToken(t.id)}
                                                        className="rounded px-2 py-1 text-sm text-red-500 hover:bg-red-50 hover:text-red-700"
                                                    >
                                                        Revoke
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* API usage guide */}
                    <div className="rounded-xl bg-gray-900 p-5 text-sm text-gray-300">
                        <p className="mb-3 font-semibold text-white">Quick API reference</p>
                        <pre className="text-xs leading-relaxed overflow-x-auto whitespace-pre-wrap">{`# Encode a document
POST /api/stego/encode
Authorization: Bearer <token>
Content-Type: multipart/form-data
  document_id=<id>
  master_key=<key>
  carriers[]=<file.png>

# Decode a document
POST /api/stego/decode
Authorization: Bearer <token>
Content-Type: application/json
  { "stego_document_id": <id>, "master_key": "<key>" }
  → returns file download

# List tokens
GET /api/auth/tokens
Authorization: Bearer <token>`}</pre>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
