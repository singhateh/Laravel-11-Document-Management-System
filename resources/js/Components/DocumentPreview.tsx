    import { useState } from 'react';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';

interface DocumentPreviewProps {
    show: boolean;
    onClose: () => void;
    document: {
        id: number;
        name: string;
        file_path: string;
        extension: string;
        size: number;
    } | null;
}

export default function DocumentPreview({ show, onClose, document }: DocumentPreviewProps) {
    if (!document) return null;

    const getFileUrl = () => {
        if (document.file_path.startsWith('http')) {
            return document.file_path;
        }
        // Use the server-side view endpoint so encrypted files are decrypted
        // before being sent to the browser (inline Content-Disposition).
        return `/documents/${document.id}/view`;
    };

    const getDownloadUrl = () => {
        if (document.file_path.startsWith('http')) {
            return document.file_path;
        }
        return `/documents/${document.id}/download`;
    };

    const isImage = () => {
        return ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'svg', 'webp'].includes(
            document.extension?.toLowerCase() || ''
        );
    };

    const isPDF = () => {
        return document.extension?.toLowerCase() === 'pdf';
    };

    const isVideo = () => {
        return ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'wmv'].includes(
            document.extension?.toLowerCase() || ''
        );
    };

    const isAudio = () => {
        return ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'].includes(
            document.extension?.toLowerCase() || ''
        );
    };

    const isOfficeDoc = () => {
        return ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(
            document.extension?.toLowerCase() || ''
        );
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const renderPreview = () => {
        const fileUrl = getFileUrl();

        if (isImage()) {
            return (
                <div className="flex items-center justify-center bg-gray-100 p-4">
                    <img
                        src={fileUrl}
                        alt={document.name}
                        className="max-h-[70vh] max-w-full rounded object-contain"
                    />
                </div>
            );
        }

        if (isPDF()) {
            return (
                <div className="h-[70vh]">
                    <iframe
                        src={fileUrl}
                        className="h-full w-full rounded border-0"
                        title={document.name}
                    />
                </div>
            );
        }

        if (isVideo()) {
            return (
                <div className="flex items-center justify-center bg-black p-4">
                    <video
                        controls
                        className="max-h-[70vh] max-w-full rounded"
                        src={fileUrl}
                    >
                        Your browser does not support the video tag.
                    </video>
                </div>
            );
        }

        if (isAudio()) {
            return (
                <div className="flex flex-col items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 p-12">
                    <svg
                        className="mb-8 h-24 w-24 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
                        />
                    </svg>
                    <audio controls className="w-full max-w-md" src={fileUrl}>
                        Your browser does not support the audio tag.
                    </audio>
                </div>
            );
        }

        if (isOfficeDoc()) {
            return (
                <div className="flex flex-col items-center justify-center bg-gray-50 p-12">
                    <svg
                        className="h-24 w-24 text-gray-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fillRule="evenodd"
                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                            clipRule="evenodd"
                        />
                    </svg>
                    <p className="mt-4 text-sm text-gray-600">
                        Office documents cannot be previewed directly.
                    </p>
                    <a
                        href={fileUrl}
                        download
                        className="mt-4 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Download to view
                    </a>
                </div>
            );
        }

        // Default preview for unknown file types
        return (
            <div className="flex flex-col items-center justify-center bg-gray-50 p-12">
                <svg
                    className="h-24 w-24 text-gray-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={1.5}
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                    />
                </svg>
                <p className="mt-4 text-sm font-medium text-gray-900">{document.name}</p>
                <p className="mt-1 text-sm text-gray-600">
                    Preview not available for this file type
                </p>
                <a
                    href={fileUrl}
                    download
                    className="mt-4 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Download file
                </a>
            </div>
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="6xl">
            <div className="p-6">
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">{document.name}</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {document.extension?.toUpperCase()} • {formatFileSize(document.size)}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a
                            href={getDownloadUrl()}
                            download
                            className="rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-100"
                        >
                            Download
                        </a>
                        <SecondaryButton onClick={onClose}>Close</SecondaryButton>
                    </div>
                </div>

                {renderPreview()}
            </div>
        </Modal>
    );
}
