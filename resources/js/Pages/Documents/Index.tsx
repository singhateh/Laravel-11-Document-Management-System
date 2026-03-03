import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import DragDropUploadModal from '@/Components/DragDropUploadModal';
import DocumentPreview from '@/Components/DocumentPreview';
import PrimaryButton from '@/Components/PrimaryButton';
import axios from 'axios';

interface Document {
    id: number;
    name: string;
    file_path: string;
    extension: string;
    size: number;
    visibility: string;
    folder_id: number | null;
    created_at: string;
    updated_at: string;
    is_stegoed?: boolean;
    tags?: Array<{
        id: number;
        name: string;
    }>;
}

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    children?: Folder[];
}

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface DocumentsPageProps extends PageProps {
    documents: Document[];
    folders: Folder[];
    owners: Owner[];
    rightFolders: Folder[];
}

export default function Index({
    auth,
    documents,
    folders,
    owners,
    rightFolders,
}: DocumentsPageProps) {
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [selectedDocument, setSelectedDocument] = useState<Document | null>(null);
    const [showPreview, setShowPreview] = useState(false);
    const [editingDocument, setEditingDocument] = useState<number | null>(null);
    const [editName, setEditName] = useState('');

    const handleUploadSuccess = () => {
        router.reload();
    };

    const handlePreview = (document: Document) => {
        setSelectedDocument(document);
        setShowPreview(true);
    };

    const handleEdit = (document: Document) => {
        setEditingDocument(document.id);
        setEditName(document.name);
    };

    const handleSaveEdit = async (documentId: number) => {
        try {
            await axios.put(`/documents/${documentId}`, {
                name: editName,
            });
            setEditingDocument(null);
            router.reload();
        } catch (error) {
            console.error('Failed to update document:', error);
        }
    };

    const handleDelete = async (documentId: number, documentName: string) => {
        if (!confirm(`Are you sure you want to delete "${documentName}"?`)) return;

        try {
            await axios.delete(`/documents/${documentId}`);
            router.reload();
        } catch (error) {
            console.error('Failed to delete document:', error);
        }
    };

    const handleDownload = (document: Document) => {
        const fileUrl = document.file_path.startsWith('http') 
            ? document.file_path 
            : `/${document.file_path}`;
        
        const link = window.document.createElement('a');
        link.href = fileUrl;
        link.download = document.name;
        link.click();
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const getFileIcon = (extension: string) => {
        const iconMap: Record<string, string> = {
            pdf: '📄',
            doc: '📝',
            docx: '📝',
            xls: '📊',
            xlsx: '📊',
            ppt: '📈',
            pptx: '📈',
            jpg: '🖼️',
            jpeg: '🖼️',
            png: '🖼️',
            gif: '🖼️',
            mp4: '🎥',
            mp3: '🎵',
            zip: '🗜️',
        };
        return iconMap[extension?.toLowerCase()] || '📎';
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Documents
                    </h2>
                    <PrimaryButton onClick={() => setShowUploadModal(true)}>
                        <svg
                            className="mr-2 h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                            />
                        </svg>
                        Upload Files
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Documents" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {documents.length === 0 ? (
                                <div className="py-12 text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">
                                        No documents
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by uploading a document.
                                    </p>
                                    <div className="mt-6">
                                        <PrimaryButton onClick={() => setShowUploadModal(true)}>
                                            Upload your first document
                                        </PrimaryButton>
                                    </div>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Document
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Size
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Tags
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {documents.map((document) => (
                                                <tr key={document.id} className="hover:bg-gray-50">
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <div className="flex items-center">
                                                            <div className="text-2xl mr-3">
                                                                {getFileIcon(document.extension)}
                                                            </div>
                                                            <div>
                                                                {editingDocument === document.id ? (
                                                                    <div className="flex items-center gap-2">
                                                                        <input
                                                                            type="text"
                                                                            value={editName}
                                                                            onChange={(e) => setEditName(e.target.value)}
                                                                            className="rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                        />
                                                                        <button
                                                                            onClick={() => handleSaveEdit(document.id)}
                                                                            className="text-green-600 hover:text-green-800"
                                                                        >
                                                                            ✓
                                                                        </button>
                                                                        <button
                                                                            onClick={() => setEditingDocument(null)}
                                                                            className="text-red-600 hover:text-red-800"
                                                                        >
                                                                            ✗
                                                                        </button>
                                                                    </div>
                                                                ) : (
                                                                    <>
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-sm font-medium text-gray-900">
                                                                                {document.name}
                                                                            </span>
                                                                            {document.is_stegoed && (
                                                                                <span
                                                                                    title="This document has been encoded with StegoLock"
                                                                                    className="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700"
                                                                                >
                                                                                    🔒
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        <div className="text-xs text-gray-500">
                                                                            {document.extension?.toUpperCase()}
                                                                        </div>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                        {formatFileSize(document.size)}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="flex flex-wrap gap-1">
                                                            {document.tags?.map((tag) => (
                                                                <span
                                                                    key={tag.id}
                                                                    className="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                                                >
                                                                    {tag.name}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                        {new Date(document.created_at).toLocaleDateString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                onClick={() => handlePreview(document)}
                                                                className="text-indigo-600 hover:text-indigo-900"
                                                                title="Preview"
                                                            >
                                                                👁️
                                                            </button>
                                                            <button
                                                                onClick={() => handleDownload(document)}
                                                                className="text-green-600 hover:text-green-900"
                                                                title="Download"
                                                            >
                                                                ⬇️
                                                            </button>
                                                            <button
                                                                onClick={() => handleEdit(document)}
                                                                className="text-yellow-600 hover:text-yellow-900"
                                                                title="Edit"
                                                            >
                                                                ✏️
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(document.id, document.name)}
                                                                className="text-red-600 hover:text-red-900"
                                                                title="Delete"
                                                            >
                                                                🗑️
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <DragDropUploadModal
                show={showUploadModal}
                onClose={() => setShowUploadModal(false)}
                folders={rightFolders}
                onSuccess={handleUploadSuccess}
            />

            <DocumentPreview
                show={showPreview}
                onClose={() => setShowPreview(false)}
                document={selectedDocument}
            />
        </AuthenticatedLayout>
    );
}
