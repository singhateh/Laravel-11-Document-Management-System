import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import axios from 'axios';

interface Tag {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    tags?: Tag[];
}

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    categories?: Category[];
    subfolders?: Folder[];
}

interface TagsIndexPageProps extends PageProps {
    folders: Folder[];
}

export default function Index({ auth, folders }: TagsIndexPageProps) {
    const [showModal, setShowModal] = useState(false);
    const [folderName, setFolderName] = useState('');
    const [parentId, setParentId] = useState('');
    const [categoryName, setCategoryName] = useState('');
    const [tagsInput, setTagsInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});

    const toggle = (id: number) =>
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

    const allFolders = (list: Folder[], depth = 0): { folder: Folder; depth: number }[] =>
        list.flatMap((f) => [
            { folder: f, depth },
            ...allFolders(f.subfolders ?? [], depth + 1),
        ]);

    const handleCreate = async () => {
        setErrors({});
        if (!folderName.trim()) { setErrors({ folder_name: 'Folder name is required.' }); return; }
        if (!categoryName.trim()) { setErrors({ category_name: 'Category name is required.' }); return; }

        const tags = tagsInput
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);

        setLoading(true);
        try {
            await axios.post(route('tags.store'), {
                folder_name: folderName.trim(),
                parent_id: parentId || undefined,
                category_name: categoryName.trim(),
                tags: tags.length ? tags : undefined,
            });
            setShowModal(false);
            setFolderName(''); setParentId(''); setCategoryName(''); setTagsInput('');
            router.reload();
        } catch (e: any) {
            const errs = e.response?.data?.errors ?? {};
            setErrors(errs);
        } finally {
            setLoading(false);
        }
    };

    const renderFolder = (f: Folder, depth = 0) => {
        const isOpen = expanded[f.id] ?? false;
        const hasChildren = (f.subfolders?.length ?? 0) > 0 || (f.categories?.length ?? 0) > 0;
        return (
            <div key={f.id} style={{ paddingLeft: depth * 20 }}>
                <div
                    className={`flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 ${depth === 0 ? 'font-medium' : 'text-sm text-gray-700'}`}
                    onClick={() => toggle(f.id)}
                >
                    <span className="text-yellow-500">📁</span>
                    <span>{f.name}</span>
                    {hasChildren && (
                        <span className="ml-1 text-xs text-gray-400">{isOpen ? '▾' : '▸'}</span>
                    )}
                    {(f.categories?.length ?? 0) > 0 && (
                        <span className="ml-auto text-xs text-gray-400">{f.categories!.length} cat.</span>
                    )}
                </div>
                {isOpen && (
                    <div>
                        {f.categories?.map((cat) => (
                            <div key={cat.id} style={{ paddingLeft: (depth + 1) * 20 }} className="mt-1">
                                <div className="flex items-center gap-2 rounded px-3 py-1 text-sm text-gray-600">
                                    <span>🏷️</span>
                                    <span className="font-medium">{cat.name}</span>
                                </div>
                                {(cat.tags?.length ?? 0) > 0 && (
                                    <div className="flex flex-wrap gap-1 px-3 pb-1" style={{ paddingLeft: (depth + 2) * 20 }}>
                                        {cat.tags!.map((tag) => (
                                            <span
                                                key={tag.id}
                                                className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                                            >
                                                # {tag.name}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                        {f.subfolders?.map((sub) => renderFolder(sub, depth + 1))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Tags</h2>
                    <button
                        onClick={() => setShowModal(true)}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    >
                        + Add Tag Set
                    </button>
                </div>
            }
        >
            <Head title="Tags" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Info */}
                    <div className="rounded-xl border border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-800">
                        Tags are organised as <strong>Folder → Category → Tags</strong>.
                        Click a folder to expand its categories and tag labels.
                    </div>

                    {folders.length === 0 ? (
                        <div className="rounded-xl bg-white py-16 text-center shadow-sm">
                            <div className="text-5xl">🏷️</div>
                            <h3 className="mt-3 text-sm font-medium text-gray-900">No tag sets yet</h3>
                            <p className="mt-1 text-sm text-gray-500">Create your first folder → category → tag set.</p>
                            <div className="mt-6">
                                <button
                                    onClick={() => setShowModal(true)}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    + Add Tag Set
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-xl bg-white p-4 shadow-sm">
                            <div className="divide-y divide-gray-100">
                                {folders.map((f) => renderFolder(f))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-800">Add Tag Set</h3>
                            <button
                                onClick={() => { setShowModal(false); setErrors({}); }}
                                className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="space-y-4">
                            {/* Folder name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Folder Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={folderName}
                                    onChange={(e) => setFolderName(e.target.value)}
                                    placeholder="e.g. Projects"
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.folder_name && <p className="mt-1 text-xs text-red-600">{errors.folder_name}</p>}
                            </div>

                            {/* Parent folder (optional) */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Parent Folder <span className="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <select
                                    value={parentId}
                                    onChange={(e) => setParentId(e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">— none (root) —</option>
                                    {allFolders(folders).map(({ folder, depth }) => (
                                        <option key={folder.id} value={folder.id}>
                                            {'  '.repeat(depth)}{folder.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Category name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Category Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={categoryName}
                                    onChange={(e) => setCategoryName(e.target.value)}
                                    placeholder="e.g. Design"
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.category_name && <p className="mt-1 text-xs text-red-600">{errors.category_name}</p>}
                            </div>

                            {/* Tags */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Tags <span className="text-gray-400 font-normal">(comma-separated, optional)</span>
                                </label>
                                <input
                                    type="text"
                                    value={tagsInput}
                                    onChange={(e) => setTagsInput(e.target.value)}
                                    placeholder="ui, ux, prototype"
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {tagsInput.trim() && (
                                    <div className="mt-2 flex flex-wrap gap-1">
                                        {tagsInput.split(',').map((t) => t.trim()).filter(Boolean).map((t, i) => (
                                            <span key={i} className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">
                                                # {t}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                onClick={() => { setShowModal(false); setErrors({}); }}
                                className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleCreate}
                                disabled={loading}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {loading ? 'Creating…' : 'Create Tag Set'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
