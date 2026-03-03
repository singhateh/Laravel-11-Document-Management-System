import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import DocumentPreview from '@/Components/DocumentPreview';
import AdvancedSearchModal from '@/Components/AdvancedSearchModal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

interface Document {
    id: number;
    name: string;
    file_path: string;
    extension: string;
    size: number;
    visibility: string;
    created_at: string;
    tags?: Array<{
        id: number;
        name: string;
    }>;
}

interface SearchPageProps extends PageProps {
    results: Document[];
    query: string;
    filters: Record<string, string>;
}

export default function Index({ auth, results, query, filters }: SearchPageProps) {
    const [showSearchModal, setShowSearchModal] = useState(false);
    const [selectedDocument, setSelectedDocument] = useState<Document | null>(null);
    const [showPreview, setShowPreview] = useState(false);

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

    const handlePreview = (document: Document) => {
        setSelectedDocument(document);
        setShowPreview(true);
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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Search Results
                        </h2>
                        {query && (
                            <p className="mt-1 text-sm text-gray-600">
                                Showing results for "{query}" ({results.length} found)
                            </p>
                        )}
                    </div>
                    <PrimaryButton onClick={() => setShowSearchModal(true)}>
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
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        Advanced Search
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Search Results" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {results.length === 0 ? (
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
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">
                                        No results found
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Try adjusting your search criteria
                                    </p>
                                    <div className="mt-6">
                                        <SecondaryButton onClick={() => router.visit('/documents')}>
                                            Back to Documents
                                        </SecondaryButton>
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {results.map((document) => (
                                        <div
                                            key={document.id}
                                            className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-2xl">
                                                            {getFileIcon(document.extension)}
                                                        </span>
                                                        <div className="flex-1">
                                                            <h3 className="text-sm font-semibold text-gray-900 line-clamp-2">
                                                                {document.name}
                                                            </h3>
                                                            <p className="mt-1 text-xs text-gray-500">
                                                                {document.extension?.toUpperCase()} • {formatFileSize(document.size)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    {document.tags && document.tags.length > 0 && (
                                                        <div className="mt-3 flex flex-wrap gap-1">
                                                            {document.tags.map((tag) => (
                                                                <span
                                                                    key={tag.id}
                                                                    className="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                                                >
                                                                    {tag.name}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                    
                                                    <p className="mt-2 text-xs text-gray-500">
                                                        {new Date(document.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div className="mt-4 flex gap-2">
                                                <button
                                                    onClick={() => handlePreview(document)}
                                                    className="flex-1 rounded bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-100"
                                                >
                                                    Preview
                                                </button>
                                                <button
                                                    onClick={() => handleDownload(document)}
                                                    className="flex-1 rounded bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-600 hover:bg-green-100"
                                                >
                                                    Download
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <AdvancedSearchModal
                show={showSearchModal}
                onClose={() => setShowSearchModal(false)}
            />

            <DocumentPreview
                show={showPreview}
                onClose={() => setShowPreview(false)}
                document={selectedDocument}
            />
        </AuthenticatedLayout>
    );
}
