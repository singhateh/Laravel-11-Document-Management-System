import { useState, useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';
import axios from 'axios';

interface Document {
    id: number;
    name: string;
    type: string;
    size: number;
    created_at: string;
}

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    documents?: Document[];
    subfolders?: Folder[];
}

interface Share {
    id: number;
    name: string;
    token: string;
    valid_until: string | null;
    visibility: string;
    permission_level: string;
    can_download: boolean;
    can_upload: boolean;
    can_edit: boolean;
    can_comment: boolean;
    can_share: boolean;
}

interface SharedFolderProps extends PageProps {
    share: Share;
    folder: Folder;
}

export default function Folder({ share, folder }: SharedFolderProps) {
    const [loading, setLoading] = useState(true);
    const [folderContents, setFolderContents] = useState<Folder | null>(null);

    useEffect(() => {
        setFolderContents(folder);
        setLoading(false);
    }, [folder]);

    const handleDownload = (documentId: number, documentName: string) => {
        if (!share.can_download) {
            alert('You do not have permission to download this file');
            return;
        }
        
        // Implement download logic here
    };

    const handleUpload = () => {
        if (!share.can_upload) {
            alert('You do not have permission to upload files');
            return;
        }
        
        // Implement upload logic here
    };

    const handleEdit = (documentId: number, documentName: string) => {
        if (!share.can_edit) {
            alert('You do not have permission to edit this document');
            return;
        }
        
        // Implement edit logic here
    };

    const handleComment = (documentId: number, documentName: string) => {
        if (!share.can_comment) {
            alert('You do not have permission to add comments');
            return;
        }
        
        // Implement comment logic here
    };

    const handleShare = () => {
        if (!share.can_share) {
            alert('You do not have permission to share this folder');
            return;
        }
        
        // Implement share logic here
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    if (loading) {
        return (
            <GuestLayout>
                <div className="min-h-screen flex items-center justify-center">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p className="text-gray-600">Loading folder contents...</p>
                    </div>
                </div>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <div className="min-h-screen bg-gray-50">
                <div className="bg-white border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between py-6">
                            <div className="flex items-center">
                                <h1 className="text-2xl font-bold text-gray-900">
                                    {folderContents?.name}
                                </h1>
                                <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Shared Folder
                                </span>
                            </div>
                            <div className="flex items-center space-x-3">
                                {share.can_share && (
                                    <button
                                        onClick={handleShare}
                                        className="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors"
                                    >
                                        Share
                                    </button>
                                )}
                                {share.can_upload && (
                                    <button
                                        onClick={handleUpload}
                                        className="bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-green-700 transition-colors"
                                    >
                                        Upload File
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        {folderContents?.subfolders && folderContents.subfolders.length > 0 && (
                            <div className="border-b border-gray-200">
                                <div className="px-6 py-4">
                                    <h3 className="text-sm font-medium text-gray-900">Folders</h3>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {folderContents.subfolders.map((subfolder) => (
                                        <div
                                            key={subfolder.id}
                                            className="px-6 py-4 hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <span className="text-lg mr-3">📁</span>
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900">
                                                            {subfolder.name}
                                                        </h4>
                                                        <p className="text-xs text-gray-500">
                                                            Subfolder
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {folderContents?.documents && folderContents.documents.length > 0 && (
                            <div className="border-b border-gray-200">
                                <div className="px-6 py-4">
                                    <h3 className="text-sm font-medium text-gray-900">Documents</h3>
                                </div>
                                <div className="divide-y divide-gray-100">
                                    {folderContents.documents.map((document) => (
                                        <div
                                            key={document.id}
                                            className="px-6 py-4 hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <span className="text-lg mr-3">📄</span>
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900">
                                                            {document.name}
                                                        </h4>
                                                        <p className="text-xs text-gray-500">
                                                            {formatFileSize(document.size)} • {formatDate(document.created_at)}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-2">
                                                    {share.can_comment && (
                                                        <button
                                                            onClick={() => handleComment(document.id, document.name)}
                                                            className="text-blue-600 hover:text-blue-900 text-sm font-medium px-2 py-1 rounded"
                                                        >
                                                            Comment
                                                        </button>
                                                    )}
                                                    {share.can_edit && (
                                                        <button
                                                            onClick={() => handleEdit(document.id, document.name)}
                                                            className="text-green-600 hover:text-green-900 text-sm font-medium px-2 py-1 rounded"
                                                        >
                                                            Edit
                                                        </button>
                                                    )}
                                                    {share.can_download && (
                                                        <button
                                                            onClick={() => handleDownload(document.id, document.name)}
                                                            className="text-indigo-600 hover:text-indigo-900 text-sm font-medium px-2 py-1 rounded"
                                                        >
                                                            Download
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {(!folderContents?.subfolders || folderContents.subfolders.length === 0) &&
                            (!folderContents?.documents || folderContents.documents.length === 0) && (
                                <div className="px-6 py-12 text-center">
                                    <span className="text-6xl mb-4 block">📂</span>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">Folder is empty</h3>
                                    <p className="text-gray-500">No documents or subfolders to display</p>
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
